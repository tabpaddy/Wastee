<?php

namespace App\Filament\Platform\Resources\Communities\Pages;

use App\Filament\Platform\Resources\Communities\CommunityResource;
use App\Filament\Platform\Resources\Communities\Schemas\CommunityForm;
use App\Services\Operations\CommunityService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListCommunities extends ListRecords
{
    protected static string $resource = CommunityResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('createCommunity')->visible(fn () => auth()->user()->can('platform.geography.manage'))
            ->schema(CommunityForm::fields())->action(fn (array $data) => app(CommunityService::class)->create(auth()->user(), $data))];
    }
}
