<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\DocumentReviewStatus;
use App\Models\CompanyApprovalLog;
use App\Models\User;
use App\Services\Onboarding\CompanyApprovalService;
use App\Services\Onboarding\CompanyDocumentService;
use App\Services\Onboarding\CompanyReviewSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Phase3TestCase;

class Phase3ReviewTest extends Phase3TestCase
{
    public static function reviewers(): array
    {
        return [['Platform Super Admin'], ['Platform Admin']];
    }

    #[DataProvider('reviewers')]
    public function test_platform_roles_review_and_approval_unlocks_existing_membership(string $role): void
    {
        [$owner, $company] = $this->ready(true);
        $reviewer = $this->platformUser($role);
        $membershipId = $company->memberships()->sole()->id;
        $roles = Role::count();
        $assignments = DB::table('model_has_roles')->count();
        $this->actingAs($reviewer)->get('/platform/companies')->assertOk()->assertSee($company->name);
        $this->get('/platform/companies/'.$company->uuid)->assertOk()->assertSee($owner->email);
        $this->get('/platform/companies/'.$company->id)->assertNotFound();
        $document = $company->documents()->first();
        $this->get(route('platform.documents.download', ['company' => $company->uuid, 'document' => $document->uuid]))->assertOk();
        $this->acceptDocuments($reviewer, $company);
        $this->context()->runForPlatform($reviewer, fn () => app(CompanyApprovalService::class)->approve($reviewer, $company));
        $company->refresh();
        $this->assertSame(CompanyStatus::Approved, $company->status);
        $this->assertSame($reviewer->id, $company->approved_by);
        $this->assertNotNull($company->approved_at);
        $this->assertSame($reviewer->id, $company->approvalLogs()->latest('id')->first()->actor_user_id);
        $this->assertSame($membershipId, $company->memberships()->sole()->id);
        $this->assertSame($roles, Role::count());
        $this->assertSame($assignments, DB::table('model_has_roles')->count());
        $this->actingAs($owner)->get('/company/'.$company->uuid)->assertOk();
        $this->assertNull(getPermissionsTeamId());
        try {
            $this->context()->runForPlatform($reviewer, fn () => app(CompanyApprovalService::class)->approve($reviewer, $company));
            $this->fail('Duplicate approval accepted');
        } catch (ValidationException) {
            $this->assertSame(2, $company->approvalLogs()->count());
        }
    }

    public function test_approval_requires_pending_state_and_accepted_required_documents(): void
    {
        [$owner, $company] = $this->ready();
        $reviewer = $this->platformUser();
        foreach ([false, true] as $submitted) {
            if ($submitted) {
                app(CompanyReviewSubmissionService::class)->submit($owner, $company);
            }
            try {
                $this->context()->runForPlatform($reviewer, fn () => app(CompanyApprovalService::class)->approve($reviewer, $company));
                $this->fail('Invalid approval accepted');
            } catch (ValidationException) {
                $this->assertNull($company->fresh()->approved_at);
            }
        }
        $this->assertSame(1, $company->approvalLogs()->count());
    }

    public function test_correction_replacement_and_resubmission_preserve_decisions(): void
    {
        [$owner, $company] = $this->ready(true);
        $reviewer = $this->platformUser();
        $old = $company->documents()->first();
        $this->context()->runForPlatform($reviewer, function () use ($reviewer, $company, $old): void {
            app(CompanyDocumentService::class)->review($reviewer, $company, $old,
                ['status' => 'rejected', 'review_remarks' => 'The scan is unreadable.', 'reviewed_by' => 999]);
            app(CompanyApprovalService::class)->requestCorrection($reviewer, $company, 'Please replace the unreadable certificate.');
        });
        $this->assertSame($reviewer->id, $old->fresh()->reviewed_by);
        $this->actingAs($owner)->get(route('onboarding.show', $company->uuid))->assertOk()->assertSee('Please replace');
        $new = app(CompanyDocumentService::class)->upload($owner, $company, ['document_type' => $old->document_type->value,
            'document' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'), 'status' => 'approved', 'reviewed_by' => 999]);
        $this->assertSame(DocumentReviewStatus::Rejected, $old->fresh()->status);
        $this->assertSame(DocumentReviewStatus::Pending, $new->fresh()->status);
        $this->assertNull($new->reviewed_by);
        $this->assertSame(3, $company->documents()->count());
        app(CompanyReviewSubmissionService::class)->submit($owner, $company);
        $this->assertSame(CompanyStatus::PendingReview, $company->fresh()->status);
        $this->assertSame(['submitted', 'correction_requested', 'submitted'],
            $company->approvalLogs()->orderBy('id')->get()->map(fn ($log) => $log->action->value)->all());
        $this->acceptDocuments($reviewer, $company);
        $this->context()->runForPlatform($reviewer, fn () => app(CompanyApprovalService::class)->approve($reviewer, $company));
        $this->assertSame(CompanyStatus::Approved, $company->fresh()->status);
    }

    public static function reasonDecisions(): array
    {
        return [['reject'], ['requestCorrection']];
    }

    #[DataProvider('reasonDecisions')]
    public function test_negative_company_decisions_require_meaningful_reason(string $decision): void
    {
        [, $company] = $this->ready(true);
        $reviewer = $this->platformUser();
        foreach (['', 'short', '                    '] as $reason) {
            try {
                $this->context()->runForPlatform($reviewer, fn () => app(CompanyApprovalService::class)->$decision($reviewer, $company, $reason));
                $this->fail('Invalid reason accepted');
            } catch (ValidationException) {
                $this->assertSame(CompanyStatus::PendingReview, $company->fresh()->status);
            }
        }
        $this->assertSame(1, $company->approvalLogs()->count());
    }

    public function test_rejection_retains_data_denies_operations_and_allows_explicit_resubmission(): void
    {
        [$owner, $company] = $this->ready(true);
        $reviewer = $this->platformUser();
        $this->context()->runForPlatform($reviewer, fn () => app(CompanyApprovalService::class)->reject($reviewer, $company, 'Registration details could not be confirmed.'));
        $this->assertSame(CompanyStatus::Rejected, $company->fresh()->status);
        $this->assertSame(1, $company->memberships()->count());
        $this->assertSame(2, $company->documents()->count());
        $this->assertSame(1, $company->locations()->count());
        $this->actingAs($owner)->get('/company/'.$company->uuid)->assertForbidden();
        $this->get(route('onboarding.show', $company->uuid))->assertOk()->assertSee('could not be confirmed');
        $this->put(route('onboarding.update', $company->uuid), $this->profile())->assertSessionHasNoErrors();
        $this->post(route('onboarding.submit', $company->uuid))->assertSessionHasNoErrors();
        $this->assertSame(CompanyStatus::PendingReview, $company->fresh()->status);
        $this->assertSame(3, $company->approvalLogs()->count());
    }

    public function test_company_owner_and_company_admin_cannot_review(): void
    {
        [$owner, $company] = $this->ready(true);
        [$admin, $operational] = $this->companyUser('Admin');
        foreach ([$owner, $admin] as $actor) {
            $this->actingAs($actor)->get('/platform/companies')->assertForbidden();
            try {
                $this->context()->runForPlatform($actor, fn () => app(CompanyApprovalService::class)->approve($actor, $company));
                $this->fail('Company actor approved company');
            } catch (AuthorizationException) {
                $this->assertSame(CompanyStatus::PendingReview, $company->fresh()->status);
            }
        }
    }

    public function test_platform_reader_without_review_permission_cannot_decide(): void
    {
        [, $company] = $this->ready(true);
        $reader = User::factory()->create();
        $this->context()->runForPlatform($reader, fn () => $reader->givePermissionTo(['platform.access', 'platform.companies.view']));
        $this->actingAs($reader)->get('/platform/companies/'.$company->uuid)->assertOk()->assertDontSee('Request correction');
        $this->expectException(AuthorizationException::class);
        $this->context()->runForPlatform($reader, fn () => app(CompanyApprovalService::class)->reject($reader, $company, 'A sufficiently detailed reason.'));
    }

    public function test_stale_team_and_spoofed_reviewer_are_rejected(): void
    {
        [, $company] = $this->ready(true);
        $reviewer = $this->platformUser();
        $other = $this->platformUser();
        $this->context()->setPlatform($reviewer);
        try {
            app(CompanyApprovalService::class)->reject($other, $company, 'A sufficiently detailed reason.');
            $this->fail('Actor mismatch accepted');
        } catch (AuthorizationException) {
            $this->assertSame(CompanyStatus::PendingReview, $company->fresh()->status);
        }
        setPermissionsTeamId($company->id);
        $this->expectException(AuthorizationException::class);
        app(CompanyApprovalService::class)->reject($reviewer, $company, 'A sufficiently detailed reason.');
    }

    public function test_foreign_document_review_is_rejected_and_document_decisions_are_final(): void
    {
        [, $company] = $this->ready(true);
        [, $foreign] = $this->ready(true);
        $reviewer = $this->platformUser();
        $document = $company->documents()->first();
        $this->context()->runForPlatform($reviewer, function () use ($reviewer, $company, $foreign, $document): void {
            try {
                app(CompanyDocumentService::class)->review($reviewer, $company, $foreign->documents()->first(), ['status' => 'approved']);
                $this->fail('Foreign document accepted');
            } catch (ModelNotFoundException) {
                $this->assertSame(DocumentReviewStatus::Pending, $foreign->documents()->first()->status);
            }
            try {
                app(CompanyDocumentService::class)->review($reviewer, $company, $document, ['status' => 'rejected', 'review_remarks' => 'bad']);
                $this->fail('Short reason accepted');
            } catch (ValidationException) {
                $this->assertNull($document->fresh()->reviewed_by);
            }
            app(CompanyDocumentService::class)->review($reviewer, $company, $document, ['status' => 'approved', 'reviewed_by' => 999]);
            $this->assertSame($reviewer->id, $document->fresh()->reviewed_by);
            $this->expectException(ValidationException::class);
            app(CompanyDocumentService::class)->review($reviewer, $company, $document, ['status' => 'rejected', 'review_remarks' => 'Changed my decision.']);
        });
    }

    public function test_approval_logs_remain_append_only(): void
    {
        [, $company] = $this->ready(true);
        $this->expectException(QueryException::class);
        DB::table('company_approval_logs')->where('company_id', $company->id)->update(['remarks' => 'tampered']);
    }

    public function test_real_filament_approval_action_replays_platform_context(): void
    {
        [, $company] = $this->ready(true);
        $reviewer = $this->platformUser();
        $this->acceptDocuments($reviewer, $company);
        $snapshot = $this->snapshot($this->actingAs($reviewer)->get('/platform/companies/'.$company->uuid)->assertOk(), 'ViewCompany');
        $mounted = $this->update($snapshot, 'mountAction', ['approve'])->assertOk();
        $this->update($mounted->json('components.0.snapshot'), 'callMountedAction')->assertOk();
        $this->assertSame(CompanyStatus::Approved, $company->fresh()->status);
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_filament_review_action_rechecks_revoked_platform_access(): void
    {
        [, $company] = $this->ready(true);
        $reviewer = $this->platformUser();
        $snapshot = $this->snapshot($this->actingAs($reviewer)->get('/platform/companies/'.$company->uuid)->assertOk(), 'ViewCompany');
        $mounted = $this->update($snapshot, 'mountAction', ['approve'])->assertOk();
        $this->context()->runForPlatform($reviewer, fn () => $reviewer->syncRoles([]));
        $this->update($mounted->json('components.0.snapshot'), 'callMountedAction')->assertForbidden();
        $this->assertSame(CompanyStatus::PendingReview, $company->fresh()->status);
    }

    public function test_real_document_review_action_and_foreign_record_tampering(): void
    {
        [, $company] = $this->ready(true);
        [, $foreign] = $this->ready(true);
        $reviewer = $this->platformUser();
        $document = $company->documents()->first();
        $foreignDocument = $foreign->documents()->first();
        $snapshot = $this->snapshot($this->actingAs($reviewer)->get('/platform/companies/'.$company->uuid)->assertOk(), 'DocumentsRelationManager');
        $mounted = $this->update($snapshot, 'mountAction', ['approveDocument', [], ['table' => true, 'recordKey' => (string) $document->id]])->assertOk();
        $this->update($mounted->json('components.0.snapshot'), 'callMountedAction')->assertOk();
        $this->assertSame(DocumentReviewStatus::Approved, $document->fresh()->status);
        $this->assertSame($reviewer->id, $document->fresh()->reviewed_by);
        $tampered = $this->update($snapshot, 'mountAction', ['approveDocument', [], ['table' => true, 'recordKey' => (string) $foreignDocument->id]]);
        $this->assertContains($tampered->status(), [200, 404]);
        $this->assertSame(DocumentReviewStatus::Pending, $foreignDocument->fresh()->status);
        $this->assertNull(getPermissionsTeamId());
    }

    public function test_company_decision_rolls_back_when_log_insertion_fails(): void
    {
        [, $company] = $this->ready(true);
        $reviewer = $this->platformUser();
        $event = 'eloquent.creating: '.CompanyApprovalLog::class;
        Event::listen($event, fn () => throw new \RuntimeException('log failure'));
        try {
            $this->context()->runForPlatform($reviewer, fn () => app(CompanyApprovalService::class)->reject($reviewer, $company, 'A sufficiently detailed rejection.'));
            $this->fail('Expected log failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('log failure', $exception->getMessage());
            $this->assertSame(CompanyStatus::PendingReview, $company->fresh()->status);
            $this->assertSame(1, $company->approvalLogs()->count());
        } finally {
            Event::forget($event);
        }
    }

    private function snapshot(TestResponse $response, string $component): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $response->getContent(), $matches);
        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            if (str_contains(json_decode($snapshot, true)['memo']['name'], $component)) {
                return $snapshot;
            }
        }
        $this->fail('Missing component snapshot: '.$component);
    }

    private function update(string $snapshot, string $method, array $params = []): TestResponse
    {
        return $this->postJson(Livewire::getUpdateUri(), ['components' => [
            ['snapshot' => $snapshot, 'updates' => [], 'calls' => [['method' => $method, 'params' => $params]]],
        ]], ['X-Livewire' => 'true']);
    }
}
