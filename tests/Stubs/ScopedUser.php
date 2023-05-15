<?php

declare(strict_types=1);

namespace Namoshek\Scout\Database\Tests\Stubs;

use Laravel\Scout\Searchable;
use Namoshek\Scout\Database\StandaloneField;

/**
 * A scoped user stub for tests.
 *
 * @package Namoshek\Scout\Database\Tests\Stubs
 */
class ScopedUser extends User
{
    use Searchable;

    private string $tenantId;

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function searchableAs(): string
    {
        return 'user';
    }

    public function toSearchableArray(): array
    {
        return array_merge(parent::toSearchableArray(), [
            'tenant_id' => new StandaloneField($this->tenantId),
        ]);
    }
}
