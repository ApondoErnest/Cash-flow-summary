<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Centers\Models\Organization;
use App\Support\Auth\RoleName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OwnerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['code' => 'DEMO'],
            [
                'name' => 'Demo Inspection Organization',
                'currency' => 'XAF',
                'timezone' => 'Africa/Douala',
                'default_language' => 'fr',
                'is_active' => true,
            ],
        );

        $username = (string) env('SEED_OWNER_USERNAME', 'owner');

        $owner = User::query()->firstOrNew(['username' => $username]);

        $attributes = [
            'organization_id' => $organization->id,
            'center_id' => null,
            'name' => (string) env('SEED_OWNER_NAME', 'Owner'),
            'is_active' => true,
        ];

        if (! $owner->exists) {
            $attributes['password'] = Hash::make((string) env('SEED_OWNER_PASSWORD', 'password'));
            $attributes['must_change_password'] = true;
        }

        $owner->fill($attributes)->save();

        $owner->syncRoles([RoleName::Owner]);
    }
}
