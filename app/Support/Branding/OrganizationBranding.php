<?php

declare(strict_types=1);

namespace App\Support\Branding;

use App\Modules\Centers\Models\Organization;
use Illuminate\Support\Facades\Schema;

final class OrganizationBranding
{
    public function displayName(): string
    {
        if (! Schema::hasTable('organizations')) {
            return '';
        }

        $name = Organization::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->value('name');

        return filled($name) ? trim((string) $name) : '';
    }
}
