<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\Lga;
use App\Models\User;
use App\Services\Auth\RoleProvisioner;
use App\Services\Onboarding\CompanyDocumentService;
use App\Services\Onboarding\CompanyRegistrationService;
use App\Services\Onboarding\CompanyReviewSubmissionService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Phase3TestCase;

class Phase3OnboardingTest extends Phase3TestCase
{
    public function test_upload_cleans_up_file_when_record_insert_fails(): void
    {
        [$owner, $company] = $this->draft();
        $event = 'eloquent.creating: '.CompanyDocument::class;
        Event::listen($event, fn () => throw new \RuntimeException('document failure'));
        try {
            app(CompanyDocumentService::class)->upload($owner, $company, [
                'document_type' => 'other', 'document' => UploadedFile::fake()->create('file.pdf', 10, 'application/pdf')]);
            $this->fail('Expected insertion failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('document failure', $exception->getMessage());
            $this->assertSame([], Storage::disk('company_documents')->allFiles());
            $this->assertSame(0, $company->documents()->count());
        } finally {
            Event::forget($event);
        }
    }

    public static function frozenStatuses(): array
    {
        return [['pending_review'], ['approved'], ['suspended']];
    }

    #[DataProvider('frozenStatuses')]
    public function test_all_material_edits_are_blocked_in_frozen_states(string $status): void
    {
        [$owner, $company] = $this->draft();
        $company->update(['status' => $status]);
        $this->actingAs($owner)->put(route('onboarding.update', $company->uuid), $this->profile())->assertSessionHasErrors('company');
        $this->post(route('onboarding.location', $company->uuid), [
            'label' => 'Office', 'address_line' => '1 Test Road', 'lga_id' => Lga::factory()->create()->id])->assertSessionHasErrors('company');
        $this->post(route('onboarding.document', $company->uuid), ['document_type' => 'other',
            'document' => UploadedFile::fake()->create('file.pdf', 10, 'application/pdf')])->assertSessionHasErrors('company');
        $this->assertSame(0, $company->locations()->count());
        $this->assertSame(0, $company->documents()->count());
    }

    private function registration(): array
    {
        return ['first_name' => 'Ada', 'last_name' => 'Okafor', 'email' => 'ADA@example.test', 'phone' => '08012345678',
            'password' => 'A-secure-password9!', 'password_confirmation' => 'A-secure-password9!'];
    }

    public function test_public_registration_hashes_password_and_sends_uuid_verification(): void
    {
        Notification::fake();
        $this->get('/register')->assertOk();
        $this->post('/register', [...$this->registration(), 'status' => 'suspended', 'email_verified_at' => now(), 'role' => 'Platform Super Admin'])
            ->assertRedirect(route('verification.notice'));
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Ada Okafor', $user->name);
        $this->assertTrue(Hash::check($this->registration()['password'], $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertSame('active', $user->status->value);
        $this->assertSame('7', $user->uuid[14]);
        Notification::assertSentTo($user, VerifyEmail::class);
        $url = (new VerifyEmail)->toMail($user)->actionUrl;
        $this->assertStringContainsString('/'.$user->uuid.'/', $url);
        $this->get($url)->assertRedirect(route('onboarding.index'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get($url)->assertRedirect(route('onboarding.index'));
    }

    public function test_duplicate_email_weak_and_mismatched_passwords_are_rejected(): void
    {
        User::factory()->create(['email' => 'ada@example.test']);
        $this->post('/register', $this->registration())->assertSessionHasErrors('email');
        $this->post('/register', [...$this->registration(), 'email' => 'other@example.test', 'password' => 'weak',
            'password_confirmation' => 'weak'])->assertSessionHasErrors('password');
        $this->post('/register', [...$this->registration(), 'email' => 'other@example.test',
            'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_verification_signatures_hashes_and_foreign_ids_fail(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();
        $this->actingAs($user);
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->uuid, 'hash' => 'wrong']))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $other->uuid, 'hash' => sha1($other->email)]))->assertForbidden();
        $this->get(route('verification.verify', ['id' => $user->uuid, 'hash' => sha1($user->email)]))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_resend_and_unverified_onboarding_redirect(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/onboarding')->assertRedirect(route('verification.notice'));
        $this->post(route('verification.send'))->assertRedirect();
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $user->markEmailAsVerified();
        $this->post(route('verification.send'))->assertRedirect();
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_owner_can_login_logout_and_inactive_account_cannot_login(): void
    {
        $user = User::factory()->create();
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('onboarding.index'));
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $user->forceFill(['status' => 'suspended'])->save();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    }

    public function test_company_creation_immediately_provisions_owner_and_ignores_forged_fields(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $this->actingAs($owner)->post(route('onboarding.store'), [...$this->profile(),
            'owner_user_id' => $foreign->id, 'status' => 'approved', 'approved_by' => $foreign->id, 'company_id' => 999])
            ->assertRedirect();
        $company = Company::sole();
        $this->assertSame($owner->id, $company->owner_user_id);
        $this->assertSame(CompanyStatus::Draft, $company->status);
        $this->assertNull($company->approved_by);
        $this->assertTrue($company->memberships()->current()->where('user_id', $owner->id)->exists());
        $this->assertDatabaseCount('roles', 7);
        $this->assertDatabaseHas('model_has_roles', ['model_id' => $owner->id, 'company_id' => $company->id]);
        $this->assertNotNull($company->settings);
        $this->assertNull(getPermissionsTeamId());
        $this->get('/company/'.$company->uuid)->assertForbidden();
        $this->get(route('onboarding.show', $company->uuid))->assertOk()->assertSee('Company profile');
    }

    public function test_creation_restores_platform_and_company_contexts_and_clears_stale_team(): void
    {
        $admin = $this->platformUser();
        $this->context()->runForPlatform($admin, function () use ($admin): void {
            $this->draft($admin);
            $this->assertSame(0, getPermissionsTeamId());
            $this->assertTrue($this->context()->allowsPlatform($admin, 'platform.companies.review'));
        });
        [$owner, $existing] = $this->companyUser();
        $this->context()->runForCompany($owner, $existing, function () use ($owner, $existing): void {
            $this->draft($owner);
            $this->assertSame($existing->id, getPermissionsTeamId());
            $this->assertTrue($this->context()->allowsCompany($owner, 'company.access'));
        });
        setPermissionsTeamId(999);
        $this->draft();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_creation_rolls_back_every_record_when_provisioning_fails(): void
    {
        $owner = User::factory()->create();
        $this->partialMock(RoleProvisioner::class, function ($mock): void {
            $mock->shouldReceive('assignInitialOwner')->once()->andThrow(new \RuntimeException('simulated failure'));
        });
        try {
            app(CompanyRegistrationService::class)->create($owner, $this->profile());
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('simulated failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('company_memberships', 0);
        $this->assertDatabaseCount('company_settings', 0);
        $this->assertDatabaseCount('roles', 2);
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_duplicate_registration_number_and_invalid_profile_are_rejected(): void
    {
        [$owner, $company] = $this->draft();
        $this->actingAs($owner)->post(route('onboarding.store'), [...$this->profile(), 'registration_number' => $company->registration_number])
            ->assertSessionHasErrors('registration_number');
        $this->post(route('onboarding.store'), ['name' => 'Incomplete'])->assertSessionHasErrors(['email', 'phone']);
        $this->assertDatabaseCount('companies', 1);
    }

    public static function visibleStatuses(): array
    {
        return [['draft'], ['pending_review'], ['correction_required'], ['rejected'], ['approved'], ['suspended']];
    }

    #[DataProvider('visibleStatuses')]
    public function test_owner_can_view_each_status_but_foreign_and_platform_users_cannot(string $status): void
    {
        [$owner, $company] = $this->draft();
        $company->update(['status' => $status]);
        $this->actingAs($owner)->get(route('onboarding.show', $company->uuid))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('onboarding.show', $company->uuid))->assertForbidden();
        $this->actingAs($this->platformUser())->get(route('onboarding.show', $company->uuid))->assertForbidden();
    }

    public function test_membership_without_ownership_does_not_grant_onboarding(): void
    {
        [$owner, $company] = $this->draft();
        $staff = User::factory()->create();
        $company->memberships()->create(['user_id' => $staff->id, 'joined_at' => now()]);
        $this->actingAs($staff)->get(route('onboarding.show', $company->uuid))->assertForbidden();
        $this->put(route('onboarding.update', $company->uuid), $this->profile())->assertForbidden();
    }

    public function test_numeric_invalid_foreign_routes_and_sequential_owners_are_isolated(): void
    {
        [$a, $companyA] = $this->draft();
        [$b, $companyB] = $this->draft();
        $this->actingAs($a)->get('/onboarding/'.$companyA->id)->assertNotFound();
        $this->get('/onboarding/not-a-uuid')->assertNotFound();
        $this->get('/onboarding/'.$companyB->uuid)->assertForbidden();
        $this->get('/onboarding/'.$companyA->uuid)->assertOk();
        $this->actingAs($b)->get('/onboarding/'.$companyA->uuid)->assertForbidden();
        $this->get('/onboarding/'.$companyB->uuid)->assertOk();
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_profile_whitelist_and_pending_freeze(): void
    {
        [$owner, $company] = $this->ready();
        $this->actingAs($owner)->put(route('onboarding.update', $company->uuid), [...$this->profile(),
            'status' => 'approved', 'owner_user_id' => 999, 'approved_by' => 999])->assertSessionHasNoErrors();
        $this->assertSame($owner->id, $company->fresh()->owner_user_id);
        $this->assertSame(CompanyStatus::Draft, $company->fresh()->status);
        app(CompanyReviewSubmissionService::class)->submit($owner, $company);
        $this->put(route('onboarding.update', $company->uuid), $this->profile())->assertSessionHasErrors('company');
        $this->post(route('onboarding.document', $company->uuid), ['document_type' => 'other',
            'document' => UploadedFile::fake()->create('file.pdf', 10, 'application/pdf')])->assertSessionHasErrors('company');
    }

    public function test_head_office_uses_normalized_state_and_preserves_history(): void
    {
        [$owner, $company] = $this->draft();
        $lga = Lga::factory()->create();
        $payload = ['label' => 'Office', 'address_line' => 'First road', 'lga_id' => $lga->id, 'state_id' => 999, 'company_id' => 999];
        $this->actingAs($owner)->post(route('onboarding.location', $company->uuid), $payload)->assertSessionHasNoErrors();
        $first = $company->locations()->sole();
        $this->assertSame($lga->state_id, $first->state_id);
        $this->assertSame('7', $first->uuid[14]);
        $this->post(route('onboarding.location', $company->uuid), $payload)->assertSessionHasErrors('location');
        $this->travel(1)->days();
        $this->post(route('onboarding.location', $company->uuid), [...$payload, 'address_line' => 'Second road'])->assertSessionHasNoErrors();
        $this->assertSame(2, $company->locations()->count());
        $this->assertSame(1, $company->locations()->current()->count());
        $this->assertNotNull($first->fresh()->active_to);
        $this->actingAs(User::factory()->create())->post(route('onboarding.location', $company->uuid), $payload)->assertForbidden();
    }

    public function test_documents_are_private_and_foreign_uuid_downloads_fail(): void
    {
        [$owner, $company] = $this->ready();
        [, $foreign] = $this->ready();
        $document = $company->documents()->first();
        $foreignDocument = $foreign->documents()->first();
        Storage::disk('company_documents')->assertExists($document->path);
        $this->assertSame('7', $document->uuid[14]);
        $this->actingAs($owner)->get(route('onboarding.download', ['company' => $company->uuid, 'document' => $document->uuid]))->assertOk();
        $this->get(route('onboarding.download', ['company' => $company->uuid, 'document' => $foreignDocument->uuid]))->assertNotFound();
        $this->get(route('onboarding.download', ['company' => $foreign->uuid, 'document' => $foreignDocument->uuid]))->assertForbidden();
        $this->get('/storage/'.$document->path)->assertForbidden();
        $this->assertFalse(config('filesystems.disks.company_documents.serve'));
    }

    public function test_invalid_uploads_are_rejected_without_files_or_records(): void
    {
        [$owner, $company] = $this->draft();
        $this->actingAs($owner);
        foreach ([
            UploadedFile::fake()->create('script.php', 10, 'text/plain'),
            UploadedFile::fake()->create('big.pdf', 10241, 'application/pdf'),
            UploadedFile::fake()->create('disguised.exe', 10, 'application/pdf'),
        ] as $file) {
            $this->post(route('onboarding.document', $company->uuid), ['document_type' => 'other', 'document' => $file])
                ->assertSessionHasErrors('document');
        }
        $this->assertSame(0, $company->documents()->count());
        $this->assertSame([], Storage::disk('company_documents')->allFiles());
        $this->actingAs(User::factory()->create())->post(route('onboarding.document', $company->uuid), [
            'document_type' => 'other', 'document' => UploadedFile::fake()->create('file.pdf', 10, 'application/pdf')])->assertForbidden();
    }

    public function test_complete_submission_logs_once_and_blocks_duplicates(): void
    {
        [$owner, $company] = $this->ready();
        $this->actingAs($owner)->post(route('onboarding.submit', $company->uuid), ['status' => 'approved', 'actor_user_id' => 999])->assertSessionHasNoErrors();
        $this->assertSame(CompanyStatus::PendingReview, $company->fresh()->status);
        $this->assertNotNull($company->fresh()->submitted_at);
        $this->assertSame($owner->id, $company->approvalLogs()->sole()->actor_user_id);
        $this->post(route('onboarding.submit', $company->uuid))->assertSessionHasErrors('company');
        $this->assertSame(1, $company->approvalLogs()->count());
    }

    public static function missingRequirements(): array
    {
        return [['profile'], ['location'], ['documents'], ['verification'], ['membership'], ['role']];
    }

    #[DataProvider('missingRequirements')]
    public function test_service_rejects_incomplete_application(string $missing): void
    {
        [$owner, $company] = $this->ready();
        match ($missing) {
            'profile' => $company->update(['registration_number' => null]),
            'location' => $company->locations()->delete(),
            'documents' => $company->documents()->delete(),
            'verification' => $owner->forceFill(['email_verified_at' => null])->save(),
            'membership' => $company->memberships()->update(['status' => 'inactive']),
            'role' => DB::table('model_has_roles')->where('company_id', $company->id)->delete(),
        };
        try {
            app(CompanyReviewSubmissionService::class)->submit($owner, $company);
            $this->fail('Incomplete application was submitted');
        } catch (ValidationException) {
            $this->assertSame(CompanyStatus::Draft, $company->fresh()->status);
            $this->assertSame(0, $company->approvalLogs()->count());
        }
    }

    public function test_foreign_submission_and_unverified_creation_are_denied(): void
    {
        [$owner, $company] = $this->ready();
        $this->actingAs(User::factory()->create())->post(route('onboarding.submit', $company->uuid))->assertForbidden();
        $this->expectException(HttpException::class);
        app(CompanyRegistrationService::class)->create(User::factory()->unverified()->create(), $this->profile());
    }
}
