<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Centers\Models\Organization;
use App\Support\Auth\RoleName;
use Illuminate\Database\Seeder;

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
        $password = (string) env('SEED_OWNER_PASSWORD', 'password');

        $owner = User::query()->firstOrNew(['username' => $username]);

        $attributes = [
            'organization_id' => $organization->id,
            'center_id' => null,
            'name' => (string) env('SEED_OWNER_NAME', 'Owner'),
            'is_active' => true,
        ];

        if (! $owner->exists) {
            $attributes['must_change_password'] = true;
        }

        if (! $owner->exists || app()->environment('local')) {
            // Plain string — User model casts password as hashed.
            $attributes['password'] = $password;
        }

        $owner->fill($attributes)->save();

        $owner->syncRoles([RoleName::Owner]);
    }
}
