<?php

declare(strict_types=1);

namespace App\View\Components\Authentication;

use App\Support\Branding\OrganizationBranding;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class BrandPanel extends Component
{
    public string $organizationName;

    public function __construct(
        public ?string $heading = null,
        public ?string $description = null,
    ) {
        $this->organizationName = app(OrganizationBranding::class)->displayName();
    }

    public function render(): View|Closure|string
    {
        return view('components.authentication.brand-panel');
    }
}
