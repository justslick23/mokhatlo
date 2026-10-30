<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Societies\CreateSociety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocietyAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_societies(): void
    {
        $this->get('/societies')
            ->assertRedirect(route('login'));
    }

    public function test_unverified_user_cannot_create_society(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post('/societies', [
                'name' => 'Test Society',
            ])
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_create_society(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/societies', [
                'name' => 'Test Savings Society',
                'description' => 'A test society',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('societies', [
            'name' => 'Test Savings Society',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('society_memberships', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_member_cannot_switch_to_another_society(): void
    {
        $owner = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $otherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $society = app(CreateSociety::class)->execute(
            $owner,
            ['name' => 'Private Savings Society']
        );

        $this->actingAs($otherUser)
            ->post(route('societies.switch', $society))
            ->assertForbidden();
    }

    public function test_creator_can_access_society_dashboard(): void
    {
        $owner = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $society = app(CreateSociety::class)->execute(
            $owner,
            ['name' => 'My Savings Society']
        );

        $this->actingAs($owner)
            ->withSession([
                'active_society_id' => $society->id,
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('My Savings Society');
    }
}
