<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\CompanyPanelProvider;
use App\Providers\Filament\PlatformPanelProvider;

return [
    AppServiceProvider::class,
    PlatformPanelProvider::class,
    CompanyPanelProvider::class,
];
