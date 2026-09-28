<?php

namespace App\Filament\Company\Resources\Invitations\Tables;

use App\Services\Staff\StaffInvitationService;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('email')->searchable(), TextColumn::make('role_name')->label('Invited role'),
            TextColumn::make('status')->formatStateUsing(fn ($record) => $record->status->value === 'pending' && ! $record->expires_at->isFuture() ? 'expired' : $record->status->value)->badge(),
            TextColumn::make('inviter.name')->label('Invited by'), TextColumn::make('expires_at')->dateTime(),
            TextColumn::make('acceptedBy.name')->label('Accepted by'), TextColumn::make('accepted_at')->dateTime(),
        ])->recordActions([
            Action::make('resend')->requiresConfirmation()->visible(fn ($record) => $record->isAcceptable())
                ->action(fn ($record) => app(StaffInvitationService::class)->resend(auth()->user(), $record)),
            Action::make('revoke')->color('danger')->requiresConfirmation()->visible(fn ($record) => $record->isAcceptable())
                ->action(fn ($record) => app(StaffInvitationService::class)->revoke(auth()->user(), $record)),
        ])->defaultSort('created_at', 'desc');
    }
}
