<?php

namespace App\Filament\Platform\Resources\Companies\Tables;

use App\Enums\CompanyStatus;
use App\Enums\DocumentReviewStatus;
use App\Support\CompanyOnboardingRequirements;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('registration_number')->searchable(),
            TextColumn::make('owner.name')->searchable(),
            TextColumn::make('status')->formatStateUsing(fn ($state) => str_replace('_', ' ', $state->value))->badge(),
            TextColumn::make('submitted_at')->dateTime()->sortable(),
            TextColumn::make('head_office')->state(fn ($record) => $record->locations->first()?->address_line ?? 'Not provided'),
            TextColumn::make('documents_complete')->label('Required documents')->state(function ($record): string {
                $required = collect(CompanyOnboardingRequirements::documentTypes())->map(fn ($type) => $type->value);
                $count = $record->documents->filter(fn ($document) => $required->contains($document->document_type->value)
                    && $document->status !== DocumentReviewStatus::Rejected)->count();

                return $count.' / '.$required->count();
            }),
        ])->filters([
            SelectFilter::make('status')->options(collect(CompanyStatus::cases())->mapWithKeys(fn ($status) => [$status->value => ucfirst(str_replace('_', ' ', $status->value))])->all()),
        ])->recordActions([ViewAction::make()])->defaultSort('submitted_at', 'desc');
    }
}
