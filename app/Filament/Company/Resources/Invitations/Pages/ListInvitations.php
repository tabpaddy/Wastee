<?php

namespace App\Filament\Company\Resources\Invitations\Pages;

use App\Filament\Company\Resources\Invitations\InvitationResource;
use App\Filament\Company\Resources\Invitations\Schemas\InvitationForm;
use App\Services\Staff\StaffInvitationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInvitations extends ListRecords
{
    protected static string $resource = InvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('invite')->label('Invite staff')->schema(InvitationForm::fields())
            ->visible(fn () => auth()->user()->can('staff.invite') && auth()->user()->can('roles.assign'))
            ->action(function (array $data): void {
                app(StaffInvitationService::class)->invite(auth()->user(), $data);
                Notification::make()->title('Invitation saved')->body('The email will be sent after saving. Resend is available if delivery fails.')->success()->send();
            })];
    }
}
