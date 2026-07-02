<?php

declare(strict_types=1);

namespace App\Modules\Users\Models;

use App\Modules\Centers\Models\Center;
use App\Modules\Centers\Models\Organization;
use App\Support\Auth\RoleName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $guard_name = 'web';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'center_id',
        'name',
        'username',
        'phone',
        'email',
        'password',
        'is_active',
        'must_change_password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function center(): BelongsTo
    {
        return $this->belongsTo(Center::class);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(RoleName::Owner);
    }

    public function isCenterStaff(): bool
    {
        return $this->hasRole(RoleName::CenterManager)
            || $this->hasRole(RoleName::Cashier);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret);
    }
}
