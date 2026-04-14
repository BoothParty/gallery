<?php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class CrudResource
{
    public function __construct(
        public readonly string $label,
        public readonly string $labelPlural,
        public readonly string $icon = 'table',
        public readonly int $perPage = 15,
        public readonly ?string $defaultSort = 'id',
        public readonly string $defaultSortDirection = 'desc',
        public readonly bool $canCreate = true,
        public readonly bool $canEdit = true,
        public readonly bool $canDelete = true,
        public readonly bool $canExport = false,
    ) {}
}
