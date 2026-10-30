<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\User;
use App\Notifications\SocietyInvitationNotification;
use App\Services\Societies\CreateSociety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SocietyManagementTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
        ]);
    }

    private function createSociety(User $owner, string $name)
    {
        return app(CreateSociety::class)->execute(
            $owner,
            ['name' => $name]
        );
    }

    public function test_owner_can_invite_member(): void
    {
        Notification::fake();

        $owner = $this->verifiedUser();
        $society = $this->createSociety(
            $owner,
            'Test Society'
        );

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->post(route('members.invite.store'), [
                'email' => 'member@example.com',
                'role_name' => 'member',
            ])
            ->assertRedirect(route('members.index'));

        $this->assertDatabaseHas('society_invitations', [
            'society_id' => $society->id,
            'email' => 'member@example.com',
            'role_name' => 'member',
        ]);

        Notification::assertSentOnDemand(
            SocietyInvitationNotification::class
        );
    }

    public function test_invited_user_can_accept_invitation(): void
    {
        Notification::fake();

        $owner = $this->verifiedUser();
        $society = $this->createSociety(
            $owner,
            'Test Society'
        );

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->post(route('members.invite.store'), [
                'email' => 'invitee@example.com',
                'role_name' => 'member',
            ])
            ->assertRedirect();

        $token = null;

        Notification::assertSentOnDemand(
            SocietyInvitationNotification::class,
            function ($notification) use (&$token) {
                $token = $notification->token;

                return true;
            }
        );

        $this->assertNotNull($token);

        $invitee = User::factory()->create([
            'email' => 'invitee@example.com',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($invitee)
            ->post(route('invitations.accept', $token))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('society_memberships', [
            'society_id' => $society->id,
            'user_id' => $invitee->id,
            'status' => 'active',
        ]);
    }

    public function test_non_member_cannot_access_member_list(): void
    {
        $owner = $this->verifiedUser();
        $outsider = $this->verifiedUser();

        $society = $this->createSociety(
            $owner,
            'Private Society'
        );

        $this->actingAs($outsider)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->get(route('members.index'))
            ->assertRedirect(route('societies.index'));
    }

    public function test_owner_can_create_and_activate_cycle(): void
    {
        $owner = $this->verifiedUser();
        $society = $this->createSociety(
            $owner,
            'Savings Society'
        );

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->post(route('cycles.store'), [
                'name' => '2027 Savings',
                'code' => '2027',
                'start_date' => '2027-01-01',
                'end_date' => '2027-12-31',

                'minimum_monthly_contribution' => '500.00',
                'contribution_due_day' => 25,
                'contribution_grace_days' => 0,
                'contribution_penalty_percent' => '5',

                'loan_interest_percent' => '10',
                'loan_interest_basis' => 'per_term',
                'loan_max_term_months' => 6,
                'loan_penalty_percent' => '5',
            ])
            ->assertRedirect(route('cycles.index'));

        $cycle = Cycle::where(
            'society_id',
            $society->id
        )->firstOrFail();

        $this->assertEquals('draft', $cycle->status);

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->post(route('cycles.activate', $cycle->id))
            ->assertRedirect(route('cycles.index'));

        $this->assertDatabaseHas('cycles', [
            'id' => $cycle->id,
            'society_id' => $society->id,
            'status' => 'active',
            'active_slot' => 1,
        ]);
    }

    public function test_cycle_from_another_society_cannot_be_activated(): void
    {
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();

        $societyA = $this->createSociety($ownerA, 'Society A');
        $societyB = $this->createSociety($ownerB, 'Society B');

        $foreignCycle = $societyB->cycles()->create([
            'name' => 'Private Cycle',
            'code' => 'PRIVATE',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'draft',
        ]);

        $this->actingAs($ownerA)
            ->withSession([
                'active_society_id' => $societyA->id,
            ])
            ->post(route('cycles.activate', $foreignCycle->id))
            ->assertNotFound();
    }
}
