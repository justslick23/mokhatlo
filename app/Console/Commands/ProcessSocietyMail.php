<?php

namespace App\Console\Commands;

use App\Mail\MokhatloStatementMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

final class ProcessSocietyMail extends Command
{
    protected $signature = 'mokhatlo:mail:process {--limit= : Maximum emails this run} {--dry-run : List pending emails without sending}';
    protected $description = 'Send one email per society member through the durable mail outbox.';

    public function handle(): int
    {
        if (! config('mokhatlo_mail.enabled')) {
            $this->warn('MOKHATLO_MAIL_ENABLED is disabled. No emails sent.');
            return self::FAILURE;
        }
        $max = max(1, (int) config('mokhatlo_mail.max_attempts', 5));
        $limit = max(1, min(500, (int) ($this->option('limit') ?: config('mokhatlo_mail.process_limit', 40))));
        if ($this->option('dry-run')) {
            $pending = DB::table('society_mail_deliveries')
                ->whereIn('status', ['pending', 'retry'])
                ->orderBy('id')->limit($limit)->get();
            foreach ($pending as $row) {
                $this->line("Would send #{$row->id} to {$row->recipient_email}");
            }
            $this->info('Dry run: no emails sent or delivery rows modified.');
            return self::SUCCESS;
        }
        // A crashed worker at max attempts must not remain 'processing' forever.
        DB::table('society_mail_deliveries')->where('status', 'processing')
            ->where('attempts', '>=', $max)
            ->where('claimed_at', '<', now()->subMinutes(20))
            ->update(['status' => 'exhausted', 'updated_at' => now()]);
        $processed = 0;

        while ($processed < $limit) {
            $delivery = DB::transaction(static function () use ($max) {
                $row = DB::table('society_mail_deliveries')
                    ->where('attempts', '<', $max)
                    ->where(static function ($q) {
                        $q->where(static function ($q) {
                            $q->whereIn('status', ['pending', 'retry'])
                                ->where(static fn ($q) => $q->whereNull('next_attempt_at')
                                    ->orWhere('next_attempt_at', '<=', now()));
                        })->orWhere(static function ($q) {
                            $q->where('status', 'processing')
                                ->where('claimed_at', '<', now()->subMinutes(20));
                        });
                    })
                    ->orderBy('id')->lockForUpdate()->first();
                if (! $row) {
                    return null;
                }
                DB::table('society_mail_deliveries')->where('id', $row->id)->update([
                    'status' => 'processing', 'attempts' => (int) $row->attempts + 1,
                    'claimed_at' => now(), 'updated_at' => now(),
                ]);
                return $row;
            }, 3);
            if (! $delivery) {
                break;
            }
            $processed++;
            try {
                // Re-verify recipient membership and destination AT SEND TIME to avoid leaks.
                $valid = DB::table('society_memberships as sm')
                    ->join('users as u', 'u.id', '=', 'sm.user_id')
                    ->where('sm.id', $delivery->society_membership_id)
                    ->where('sm.society_id', $delivery->society_id)
                    ->where('sm.user_id', $delivery->user_id)
                    ->where('sm.status', 'active')
                    ->whereNotNull('u.email_verified_at')
                    ->where('u.email', $delivery->recipient_email)->exists();
                if (! $valid) {
                    DB::table('society_mail_deliveries')->where('id', $delivery->id)->update([
                        'status' => 'skipped', 'last_error' => 'Membership/email no longer eligible.',
                        'updated_at' => now(),
                    ]);
                    continue;
                }
                $society = DB::table('societies')->where('id', $delivery->society_id)->first(['name']);
                if ($delivery->mail_event_id) {
                    $event = DB::table('society_mail_events')
                        ->where('society_id', $delivery->society_id)
                        ->where('id', $delivery->mail_event_id)->first();
                    if (! $event) throw new \RuntimeException('Missing source event.');
                    $payload = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
                    $headline = ucwords(str_replace('_', ' ', $event->kind));
                    $notice = [
                        'headline' => $headline,
                        'intro' => 'A transaction or loan status has been recorded in ' . $society->name . '.',
                        'event' => $payload,
                        'footer' => 'This is a shared society activity notice. It is not a receipt or a payout confirmation.',
                    ];
                    $mail = new MokhatloStatementMail("{$society->name}: {$headline}", $notice);
                } else {
                    $snapshot = DB::table('society_monthly_snapshots')
                        ->where('society_id', $delivery->society_id)
                        ->where('id', $delivery->monthly_snapshot_id)->first();
                    if (! $snapshot) throw new \RuntimeException('Missing monthly report snapshot.');
                    $correctAudience = $snapshot->audience_key === 'all'
                        || $snapshot->audience_key === 'member:' . $delivery->society_membership_id;
                    if (! $correctAudience) throw new \RuntimeException('Report audience mismatch.');
                    $data = json_decode($snapshot->payload, true, 512, JSON_THROW_ON_ERROR);
                    $kind = $data['kind'];
                    $periodLabel = \Carbon\CarbonImmutable::parse($data['period'] . '-01')->format('F Y');
                    $headline = $kind === 'member' ? 'Your private monthly statement' : 'Society monthly financial report';
                    $mail = new MokhatloStatementMail(
                        "{$society->name}: {$headline} — {$periodLabel}",
                        ['headline' => $headline, 'intro' => "Attached is your {$periodLabel} PDF report.",
                            'footer' => $kind === 'member'
                                ? 'This statement is personal and must not be forwarded to other members.'
                                : 'This society-level summary is shared with all active verified members.'],
                        $data
                    );
                }
                Mail::to($delivery->recipient_email)->send($mail);
                DB::table('society_mail_deliveries')->where('id', $delivery->id)->update([
                    'status' => 'sent', 'sent_at' => now(), 'claimed_at' => null,
                    'last_error' => null, 'updated_at' => now(),
                ]);
                $this->info("Sent delivery #{$delivery->id}");
            } catch (\Throwable $e) {
                $attempts = (int) $delivery->attempts + 1;
                DB::table('society_mail_deliveries')->where('id', $delivery->id)->update([
                    'status' => $attempts >= $max ? 'exhausted' : 'retry',
                    'next_attempt_at' => now()->addMinutes(min(360, 2 ** min($attempts, 8))),
                    'claimed_at' => null,
                    'last_error' => mb_substr($e->getMessage(), 0, 1900),
                    'updated_at' => now(),
                ]);
                $this->error("Delivery #{$delivery->id} failed: {$e->getMessage()}");
            }
        }
        $this->info("Claimed/sent attempts: {$processed}. SMTP acceptance does not guarantee inbox delivery.");
        return self::SUCCESS;
    }
}
