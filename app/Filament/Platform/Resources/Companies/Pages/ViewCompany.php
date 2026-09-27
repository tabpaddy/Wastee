<?php

namespace App\Filament\Platform\Resources\Companies\Pages;

use App\Enums\CompanyStatus;
use App\Filament\Platform\Resources\Companies\CompanyResource;
use App\Services\Onboarding\CompanyApprovalService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')->color('success')->requiresConfirmation()
                ->visible(fn () => $this->reviewable())
                ->action(function (): void {
                    app(CompanyApprovalService::class)->approve(auth()->user(), $this->getRecord());
                    $this->refreshDecision();
                }),
            Action::make('requestCorrection')->label('Request correction')->color('warning')
                ->visible(fn () => $this->reviewable())->schema([Textarea::make('reason')->required()->minLength(10)->maxLength(4000)])
                ->action(function (array $data): void {
                    app(CompanyApprovalService::class)->requestCorrection(auth()->user(), $this->getRecord(), $data['reason']);
                    $this->refreshDecision();
                }),
            Action::make('reject')->color('danger')
                ->visible(fn () => $this->reviewable())->schema([Textarea::make('reason')->required()->minLength(10)->maxLength(4000)])
                ->action(function (array $data): void {
                    app(CompanyApprovalService::class)->reject(auth()->user(), $this->getRecord(), $data['reason']);
                    $this->refreshDecision();
                }),
        ];
    }

    private function reviewable(): bool
    {
        return Gate::allows('platform.companies.review') && $this->getRecord()->status === CompanyStatus::PendingReview;
    }

    private function refreshDecision(): void
    {
        $this->getRecord()->refresh();
        Notification::make()->title('Review decision recorded')->success()->send();
        $this->redirect(CompanyResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
