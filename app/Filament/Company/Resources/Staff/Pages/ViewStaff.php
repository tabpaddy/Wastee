<?php

namespace App\Filament\Company\Resources\Staff\Pages;

use App\Enums\CompanyMembershipStatus;
use App\Filament\Company\Resources\Staff\Schemas\StaffForm;
use App\Filament\Company\Resources\Staff\StaffResource;
use App\Services\Staff\CompanyMembershipService;
use App\Services\Staff\CompanyRoleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewStaff extends ViewRecord
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->profileAction(), $this->rolesAction(), $this->suspendAction(), $this->deactivateAction(), $this->reactivateAction()];
    }

    private function profileAction(): Action
    {
        return Action::make('editProfile')->visible(fn () => auth()->user()->can('update', $this->getRecord()))
            ->schema(StaffForm::profile())
            ->fillForm(fn () => $this->getRecord()->user->only(['first_name', 'last_name', 'phone']))
            ->action(function (array $data): void {
                app(CompanyMembershipService::class)->updateProfile(auth()->user(), $this->getRecord(), $data);
                $this->refreshStaff();
            });
    }

    private function rolesAction(): Action
    {
        return Action::make('assignRoles')->visible(fn () => $this->manageable('update')
            && auth()->user()->can('roles.assign') && $this->getRecord()->status === CompanyMembershipStatus::Active)
            ->schema([StaffForm::roles()])
            ->fillForm(fn () => ['roles' => $this->getRecord()->user->roles->pluck('uuid')->all()])
            ->action(function (array $data): void {
                app(CompanyRoleService::class)->assignRoles(auth()->user(), $this->getRecord(), $data['roles']);
                $this->refreshStaff();
            });
    }

    private function suspendAction(): Action
    {
        return Action::make('suspend')->color('warning')->requiresConfirmation()
            ->visible(fn () => $this->manageable('suspend') && $this->getRecord()->status === CompanyMembershipStatus::Active)
            ->action(function (): void {
                app(CompanyMembershipService::class)->suspend(auth()->user(), $this->getRecord());
                $this->refreshStaff();
            });
    }

    private function deactivateAction(): Action
    {
        return Action::make('deactivate')->color('danger')
            ->visible(fn () => $this->manageable('deactivate') && $this->getRecord()->status !== CompanyMembershipStatus::Inactive)
            ->schema([Textarea::make('reason')->required()->minLength(5)->maxLength(2000)])
            ->action(function (array $data): void {
                app(CompanyMembershipService::class)->deactivate(auth()->user(), $this->getRecord(), $data['reason']);
                $this->refreshStaff();
            });
    }

    private function reactivateAction(): Action
    {
        return Action::make('reactivate')->label(fn () => $this->getRecord()->status === CompanyMembershipStatus::Inactive ? 'Rejoin with confirmed roles' : 'Reactivate')
            ->visible(fn () => $this->manageable('reactivate') && $this->getRecord()->status !== CompanyMembershipStatus::Active)
            ->schema(fn () => $this->getRecord()->status === CompanyMembershipStatus::Inactive ? [StaffForm::roles()] : [])
            ->requiresConfirmation()
            ->action(function (array $data): void {
                app(CompanyMembershipService::class)->reactivate(auth()->user(), $this->getRecord(), $data['roles'] ?? []);
                $this->refreshStaff();
            });
    }

    private function manageable(string $ability): bool
    {
        return $this->getRecord()->user_id !== auth()->id() && auth()->user()->can($ability, $this->getRecord());
    }

    private function refreshStaff(): void
    {
        $this->getRecord()->refresh();
        Notification::make()->title('Staff membership updated')->success()->send();
        $this->redirect(StaffResource::getUrl('view', ['record' => $this->getRecord()]));
    }
}
