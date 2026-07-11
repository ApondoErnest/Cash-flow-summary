<?php

declare(strict_types=1);

namespace App\Modules\Settings\Support;

final readonly class OrganizationProfileData
{
    public function __construct(
        public string $name,
        public string $code,
        public string $defaultLanguage,
        public ?string $contactEmail,
        public ?string $contactPhone,
    ) {}
}
