<?php

declare(strict_types=1);

namespace App\Modules\WhatsApp\Support;

final readonly class WhatsAppCredentials
{
    /**
     * @param  list<string>  $ownerPhones
     */
    public function __construct(
        public array $ownerPhones,
        public string $phoneNumberId,
        public string $accessToken,
    ) {
        if ($ownerPhones === []) {
            throw new \InvalidArgumentException('At least one owner WhatsApp number is required.');
        }
    }

    public function primaryOwnerPhone(): string
    {
        return $this->ownerPhones[0];
    }
}
