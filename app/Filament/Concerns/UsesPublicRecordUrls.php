<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

trait UsesPublicRecordUrls
{
    public static function getUrl(?string $name = null, array $parameters = [], bool $isAbsolute = true,
        ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false,
        ?string $configuration = null): string
    {
        $record = $parameters['record'] ?? null;
        if ($record instanceof Model) {
            $publicKey = static::getRecordRouteKeyName() ?? $record->getRouteKeyName();
            $parameters['record'] = $record->getAttribute($publicKey);
        }

        return parent::getUrl($name, $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters, $configuration);
    }
}
