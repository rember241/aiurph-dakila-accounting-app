<?php

namespace Modules\Users\DTO\Tenants;

/**
 * Data transfer object for tenant bootstrap commands.
 */
final readonly class CreateTenantData
{
    public function __construct(
        public int $ownerUserId,
        public string $name,
        public ?string $slug,
        public string $timezone,
    )
    {
    }

    public static function fromArray(array $payload, int $ownerUserId): self
    {
        $slug = array_key_exists('slug', $payload) ? trim((string)$payload['slug']) : '';

        return new self(
            ownerUserId: $ownerUserId,
            name: trim((string)$payload['name']),
            slug: $slug !== '' ? $slug : null,
            timezone: trim((string)($payload['timezone'] ?? 'UTC')),
        );
    }
}
