<?php

namespace App\Services\MokhatloMail;

use Illuminate\Support\Facades\DB;

final class RecipientOutbox
{
    public function recipients(int $societyId): \Illuminate\Support\Collection
    {
        return DB::table('society_memberships as sm')
            ->join('users as u', 'u.id', '=', 'sm.user_id')
            ->where('sm.society_id', $societyId)
            ->where('sm.status', 'active')
            ->whereNotNull('u.email_verified_at')
            ->whereNotNull('u.email')
            ->where('u.email', '<>', '')
            ->get(['sm.id as membership_id', 'u.id as user_id', 'u.email']);
    }

    public function forEvent(int $societyId, int $eventId): int
    {
        $count = 0;
        foreach ($this->recipients($societyId) as $recipient) {
            $count += DB::table('society_mail_deliveries')->insertOrIgnore([
                'society_id' => $societyId, 'mail_event_id' => $eventId,
                'monthly_snapshot_id' => null,
                'society_membership_id' => $recipient->membership_id,
                'user_id' => $recipient->user_id, 'recipient_email' => $recipient->email,
                'delivery_key' => hash('sha256', "event:{$eventId}:{$recipient->membership_id}"),
                'status' => 'pending', 'attempts' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return $count;
    }

    public function forSnapshot(int $societyId, int $snapshotId, string $audienceKey): int
    {
        $count = 0;
        foreach ($this->recipients($societyId) as $recipient) {
            if ($audienceKey !== 'all' && $audienceKey !== 'member:' . $recipient->membership_id) {
                continue;
            }
            $count += DB::table('society_mail_deliveries')->insertOrIgnore([
                'society_id' => $societyId, 'mail_event_id' => null,
                'monthly_snapshot_id' => $snapshotId,
                'society_membership_id' => $recipient->membership_id,
                'user_id' => $recipient->user_id, 'recipient_email' => $recipient->email,
                'delivery_key' => hash('sha256', "snapshot:{$snapshotId}:{$recipient->membership_id}"),
                'status' => 'pending', 'attempts' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return $count;
    }
}
