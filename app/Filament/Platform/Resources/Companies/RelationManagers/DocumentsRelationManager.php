<?php

namespace App\Filament\Platform\Resources\Companies\RelationManagers;

use App\Enums\CompanyStatus;
use App\Enums\DocumentReviewStatus;
use App\Services\Onboarding\CompanyDocumentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class DocumentsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'documents';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('platform.companies.view');
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('document_type')->formatStateUsing(fn ($state) => str_replace('_', ' ', $state->value)),
            TextColumn::make('uuid')->label('Document reference'),
            TextColumn::make('created_at')->dateTime(),
            TextColumn::make('status')->formatStateUsing(fn ($state) => $state->value)->badge(),
            TextColumn::make('reviewer.name'), TextColumn::make('reviewed_at')->dateTime(),
            TextColumn::make('review_remarks')->wrap(),
        ])->defaultSort('id', 'desc')->recordActions([
            Action::make('download')->url(fn ($record) => route('platform.documents.download',
                ['company' => $this->getOwnerRecord()->uuid, 'document' => $record->uuid]))->openUrlInNewTab(),
            Action::make('approveDocument')->label('Accept')->requiresConfirmation()
                ->visible(fn ($record) => $this->reviewable($record))
                ->action(fn ($record) => app(CompanyDocumentService::class)->review(auth()->user(), $this->getOwnerRecord(), $record, ['status' => 'approved'])),
            Action::make('rejectDocument')->label('Reject')->color('danger')
                ->visible(fn ($record) => $this->reviewable($record))
                ->schema([Textarea::make('review_remarks')->required()->minLength(10)->maxLength(4000)])
                ->action(fn ($record, array $data) => app(CompanyDocumentService::class)->review(auth()->user(),
                    $this->getOwnerRecord(), $record, [...$data, 'status' => 'rejected'])),
        ]);
    }

    private function reviewable(Model $record): bool
    {
        return Gate::allows('platform.companies.review')
            && $this->getOwnerRecord()->status === CompanyStatus::PendingReview
            && $record->status === DocumentReviewStatus::Pending
            && $this->getOwnerRecord()->documents()->latestOfEachType()->whereKey($record->id)->exists();
    }
}
