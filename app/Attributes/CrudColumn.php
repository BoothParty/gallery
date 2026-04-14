<?php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class CrudColumn
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ColumnType $type = ColumnType::Text,
        public readonly bool $sortable = false,
        public readonly bool $searchable = false,
        public readonly bool $visible = true,
        public readonly ?string $format = null,
        public readonly ?string $badge = null, // 'success', 'warning', 'danger', 'info'
    ) {}
}
