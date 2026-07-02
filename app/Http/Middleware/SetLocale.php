<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale\AppLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        AppLocale::apply();

        return $next($request);
    }
}
