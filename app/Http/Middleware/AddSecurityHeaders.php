<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Security\ContentSecurityPolicyBuilder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function __construct(
        private readonly ContentSecurityPolicyBuilder $contentSecurityPolicyBuilder,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! config('production_security.enabled')) {
            return $response;
        }

        if (config('production_security.hsts.enabled')
            && ($request->isSecure() || config('production_security.force_https'))) {
            $response->headers->set('Strict-Transport-Security', $this->hstsValue());
        }

        if (config('production_security.headers.csp')) {
            /** @var array<string, string> $directives */
            $directives = config('production_security.csp', []);
            $response->headers->set(
                'Content-Security-Policy',
                $this->contentSecurityPolicyBuilder->build($directives),
            );
        }

        $frameOptions = config('production_security.headers.frame_options');

        if (is_string($frameOptions) && $frameOptions !== '') {
            $response->headers->set('X-Frame-Options', $frameOptions);
        }

        $contentTypeOptions = config('production_security.headers.content_type_options');

        if (is_string($contentTypeOptions) && $contentTypeOptions !== '') {
            $response->headers->set('X-Content-Type-Options', $contentTypeOptions);
        }

        $referrerPolicy = config('production_security.headers.referrer_policy');

        if (is_string($referrerPolicy) && $referrerPolicy !== '') {
            $response->headers->set('Referrer-Policy', $referrerPolicy);
        }

        $permissionsPolicy = config('production_security.headers.permissions_policy');

        if (is_string($permissionsPolicy) && $permissionsPolicy !== '') {
            $response->headers->set('Permissions-Policy', $permissionsPolicy);
        }

        return $response;
    }

    private function hstsValue(): string
    {
        $value = 'max-age='.(int) config('production_security.hsts.max_age', 31536000);

        if (config('production_security.hsts.include_subdomains')) {
            $value .= '; includeSubDomains';
        }

        if (config('production_security.hsts.preload')) {
            $value .= '; preload';
        }

        return $value;
    }
}
