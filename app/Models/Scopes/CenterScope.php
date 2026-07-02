<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Support\Center\CenterContextResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class CenterScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $resolver = app(CenterContextResolver::class);

        if (! $resolver->shouldApplyOperationalScope()) {
            return;
        }

        $context = $resolver->resolve();

        if ($context === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('center_id'), $context->centerId);
    }
}
