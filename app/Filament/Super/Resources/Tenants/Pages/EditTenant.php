<?php

namespace App\Filament\Super\Resources\Tenants\Pages;

use App\Filament\Super\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;
}
