<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\CenterScope;
use Illuminate\Database\Eloquent\Builder;

trait HasCenterScope
{
    public static function bootHasCenterScope(): void
    {
        static::addGlobalScope(new CenterScope);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutCenterScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(CenterScope::class);
    }
}
