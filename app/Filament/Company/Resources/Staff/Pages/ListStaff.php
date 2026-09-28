<?php

namespace App\Filament\Company\Resources\Staff\Pages;

use App\Filament\Company\Resources\Invitations\InvitationResource;
use App\Filament\Company\Resources\Staff\StaffResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('invitations')->label('Manage invitations')->url(fn () => InvitationResource::getUrl())
            ->visible(fn () => auth()->user()->can('staff.invite'))];
    }
}
