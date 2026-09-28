<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\Staff\StaffInvitationAcceptanceService;
use App\Services\Staff\StaffInvitationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Phase4TestCase;

class Phase4InvitationTest extends Phase4TestCase
{
    public function test_failed_outer_transaction_sends_no_invitation(): void
    {
        [$owner, $company] = $this->companyUser();
        try {
            $this->context()->runForCompany($owner, $company, function () use ($owner, $company): void {
                DB::transaction(function () use ($owner, $company): void {
                    app(StaffInvitationService::class)->invite($owner, ['email' => 'rollback@example.test', 'role_uuid' => $this->companyRole($company)->uuid]);
                    throw new \RuntimeException('rollback');
                });
            });
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('staff_invitations', 0);
            Notification::assertNothingSent();
        }
    }

    public function test_delivery_failure_keeps_invitation_and_does_not_log_token(): void
    {
        [$owner, $company] = $this->companyUser();
        Log::spy();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP failure'));
        $invitation = $this->context()->runForCompany($owner, $company, fn () => app(StaffInvitationService::class)->invite(
            $owner, ['email' => 'delivery@example.test', 'role_uuid' => $this->companyRole($company)->uuid]));
        $this->assertDatabaseHas('staff_invitations', ['id' => $invitation->id, 'status' => 'pending']);
        Log::shouldHaveReceived('warning')->once()->with(
            'Staff invitation delivery failed; resend is available.', ['invitation' => $invitation->uuid]);
    }

    public function test_invitation_sensitive_fields_and_weak_password_cannot_be_forged(): void
    {
        [$owner, $company] = $this->companyUser();
        $invitation = $this->context()->runForCompany($owner, $company, fn () => app(StaffInvitationService::class)->invite(
            $owner, ['email' => 'fixed@example.test', 'role_uuid' => $this->companyRole($company)->uuid,
                'company_id' => 0, 'invited_by' => 999, 'status' => 'accepted', 'token_hash' => 'forged', 'accepted_by' => 999]));
        $this->assertSame($company->id, $invitation->company_id);
        $this->assertSame($owner->id, $invitation->invited_by);
        $this->assertSame(InvitationStatus::Pending, $invitation->fresh()->status);
        $this->assertNull($invitation->accepted_by);
        $this->post(route('staff-invitations.accept', $invitation->uuid), [...$this->account(), 'session_proof' => $invitation->token_hash])->assertForbidden();
        $notification = Notification::sent(new AnonymousNotifiable, StaffInvitationNotification::class)->last();
        $this->get($notification->toMail(new AnonymousNotifiable)->actionUrl)->assertRedirect();
        $this->post(route('staff-invitations.accept', $invitation->uuid), [...$this->account(),
            'password' => 'weak', 'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invitation_hashes_secure_token_and_derives_sensitive_fields(): void
    {
        [$owner, $company] = $this->companyUser();
        [$invitation, $token] = $this->invitation($owner, $company);
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertStringNotContainsString($token, $invitation->toJson());
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
        $this->assertSame('7', $invitation->uuid[14]);
        $this->assertSame($owner->id, $invitation->invited_by);
        $this->assertSame($company->id, $invitation->company_id);
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_existing_user_must_authenticate_then_can_accept_without_duplicate_account(): void
    {
        [$owner, $company] = $this->companyUser();
        $staff = User::factory()->unverified()->create();
        [$invitation, $token, $url] = $this->invitation($owner, $company, $staff->email);
        $this->get($url)->assertRedirect(route('staff-invitations.show', $invitation->uuid));
        $this->get(route('staff-invitations.show', $invitation->uuid))->assertOk()->assertSee('Sign in to continue')->assertDontSee('<style>', false);
        $this->post(route('staff-invitations.accept', $invitation->uuid), $this->account())->assertSessionHasErrors('account');
        $this->post('/login', ['email' => $staff->email, 'password' => 'password'])->assertRedirect(route('staff-invitations.show', $invitation->uuid));
        $this->post(route('staff-invitations.accept', $invitation->uuid))->assertRedirect('/company/'.$company->uuid);
        $this->assertTrue($staff->fresh()->hasVerifiedEmail());
        $this->assertSame(InvitationStatus::Accepted, $invitation->fresh()->status);
        $this->assertSame($staff->id, $invitation->fresh()->accepted_by);
        $this->assertDatabaseCount('users', 2);
        $this->get('/company/'.$company->uuid)->assertOk();
        $this->get($url)->assertStatus(410);
        $this->assertDatabaseCount('company_membership_periods', 1);
    }

    public function test_new_recipient_creates_verified_uuid_account_with_fixed_invited_email(): void
    {
        [$owner, $company] = $this->companyUser();
        [$invitation, $token, $url] = $this->invitation($owner, $company, 'new@example.test');
        $this->get($url)->assertRedirect();
        $this->get(route('staff-invitations.show', $invitation->uuid))->assertOk()->assertSee('Create account and accept');
        $this->post(route('staff-invitations.accept', $invitation->uuid), [...$this->account(),
            'email' => 'attacker@example.test', 'status' => 'suspended', 'company_id' => 999, 'role' => 'Owner'])->assertRedirect();
        $staff = User::where('email', 'new@example.test')->sole();
        $this->assertSame('7', $staff->uuid[14]);
        $this->assertTrue(Hash::check($this->account()['password'], $staff->password));
        $this->assertTrue($staff->hasVerifiedEmail());
        $this->assertSame('active', $staff->status->value);
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.test']);
        $this->assertAuthenticatedAs($staff);
        $this->get('/company/'.$company->uuid)->assertOk();
        $this->assertDatabaseCount('company_memberships', 2);
    }

    public function test_wrong_authenticated_email_and_fake_token_are_denied(): void
    {
        [$owner, $company] = $this->companyUser();
        [$invitation, $token, $url] = $this->invitation($owner, $company);
        $this->get(str_replace($token, str_repeat('A', 64), $url))->assertForbidden();
        $this->get(route('staff-invitations.show', $invitation->uuid))->assertForbidden();
        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create())->post(route('staff-invitations.accept', $invitation->uuid))->assertForbidden();
        $this->assertSame(InvitationStatus::Pending, $invitation->fresh()->status);
    }

    public function test_duplicate_pending_invitation_is_denied_and_expired_row_can_be_replaced(): void
    {
        [$owner, $company] = $this->companyUser();
        [$invitation] = $this->invitation($owner, $company);
        try {
            $this->invitation($owner, $company);
            $this->fail('Duplicate invitation accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('staff_invitations', 1);
        }
        $this->travel(8)->days();
        [$replacement] = $this->invitation($owner, $company);
        $this->assertSame(InvitationStatus::Expired, $invitation->fresh()->status);
        $this->assertNotSame($invitation->id, $replacement->id);
    }

    public function test_resend_rotates_token_and_invalidates_existing_session_proof(): void
    {
        [$owner, $company] = $this->companyUser();
        [$invitation, $token, $url] = $this->invitation($owner, $company);
        $this->get($url)->assertRedirect();
        $this->context()->runForCompany($owner, $company, fn () => app(StaffInvitationService::class)->resend($owner, $invitation));
        $this->assertNotSame(hash('sha256', $token), $invitation->fresh()->token_hash);
        $this->get($url)->assertForbidden();
        $this->post(route('staff-invitations.accept', $invitation->uuid), $this->account())->assertForbidden();
        $this->assertDatabaseCount('staff_invitations', 1);
    }

    public function test_revocation_is_idempotent_and_expiry_is_checked_without_scheduler(): void
    {
        [$owner, $company] = $this->companyUser();
        [$revoked, , $revokedUrl] = $this->invitation($owner, $company);
        $this->context()->runForCompany($owner, $company, function () use ($owner, $revoked): void {
            app(StaffInvitationService::class)->revoke($owner, $revoked);
            app(StaffInvitationService::class)->revoke($owner, $revoked);
        });
        $this->get($revokedUrl)->assertStatus(410);
        [$expired, , $expiredUrl] = $this->invitation($owner, $company, 'expiry@example.test');
        $this->travel(8)->days();
        $this->get($expiredUrl)->assertStatus(410);
        $this->assertSame(InvitationStatus::Pending, $expired->fresh()->status);
    }

    public static function forbiddenRoles(): array
    {
        return [['Owner'], ['platform'], ['foreign']];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_owner_platform_and_foreign_roles_cannot_be_invited(string $kind): void
    {
        [$owner, $company] = $this->companyUser();
        [, $foreign] = $this->companyUser();
        $role = match ($kind) {
            'Owner' => $this->companyRole($company, 'Owner'),
            'platform' => Role::where('company_id', 0)->first(),
            default => $this->companyRole($foreign),
        };
        try {
            $this->context()->runForCompany($owner, $company, fn () => app(StaffInvitationService::class)->invite($owner, [
                'email' => 'staff@example.test', 'role_uuid' => $role->uuid]));
            $this->fail('Disallowed role invited');
        } catch (AuthorizationException|ValidationException) {
            $this->assertDatabaseCount('staff_invitations', 0);
        }
    }

    public function test_unauthorized_staff_and_foreign_invitation_mutation_are_denied(): void
    {
        [$owner, $company] = $this->companyUser();
        [$other, $foreign] = $this->companyUser('Waste Collector');
        [$invitation] = $this->invitation($owner, $company);
        $this->expectException(AuthorizationException::class);
        $this->context()->runForCompany($other, $foreign, fn () => app(StaffInvitationService::class)->revoke($other, $invitation));
    }

    public function test_acceptance_rechecks_inviter_authority_and_rolls_back_new_account(): void
    {
        [$owner, $company] = $this->companyUser();
        [$invitation, $token] = $this->invitation($owner, $company);
        $this->context()->runForCompany($owner, $company, fn () => $owner->syncRoles([]));
        $acceptance = app(StaffInvitationAcceptanceService::class);
        try {
            $acceptance->accept($invitation, $acceptance->verifyToken($invitation, $token), null, $this->account());
            $this->fail('Revoked inviter was accepted');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('company_memberships', 1);
            $this->assertDatabaseCount('company_membership_periods', 0);
            $this->assertSame(InvitationStatus::Pending, $invitation->fresh()->status);
            $this->assertNull(getPermissionsTeamId());
        }
    }

    public function test_platform_user_can_accept_deliberate_membership_without_losing_platform_role(): void
    {
        [$owner, $company] = $this->companyUser();
        $platformUser = $this->platformUser();
        $membership = $this->staff($owner, $company, $platformUser);
        $this->actingAs($platformUser)->get('/company/'.$company->uuid)->assertOk();
        $this->get('/platform')->assertOk();
        $this->assertSame($platformUser->id, $membership->user_id);
        $this->assertNull(getPermissionsTeamId());
    }
}
