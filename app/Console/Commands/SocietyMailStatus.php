<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class SocietyMailStatus extends Command
{
    protected $signature = 'mokhatlo:mail:status {--society=}';
    protected $description = 'Show actual notification delivery results and eligible/missing email addresses.';

    public function handle(): int
    {
        $societyId = $this->option('society') ? (int) $this->option('society') : null;
        $counts = DB::table('society_mail_deliveries')
            ->when($societyId, fn ($q) => $q->where('society_id', $societyId))
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->get();
        $this->table(['Delivery state', 'Count'], $counts->map(fn ($r) => [$r->status, $r->total]));
        $members = DB::table('society_memberships as sm')->join('users as u', 'u.id', '=', 'sm.user_id')
            ->where('sm.status', 'active')
            ->when($societyId, fn ($q) => $q->where('sm.society_id', $societyId))
            ->select(['sm.society_id', 'sm.member_number', 'u.name', 'u.email', 'u.email_verified_at'])->get();
        $missing = $members->filter(fn ($r) => ! $r->email || ! $r->email_verified_at);
        $this->line("Active members: {$members->count()}; missing/unverified emails: {$missing->count()}");
        foreach ($missing as $r) {
            $this->warn("Society {$r->society_id} / {$r->member_number}: {$r->name} — no verified email");
        }
        $this->info('Accepted by SMTP ≠ confirmed read/inbox delivery.');
        return self::SUCCESS;
    }
}
