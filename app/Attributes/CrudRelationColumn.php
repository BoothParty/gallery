<?php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class CrudRelationColumn
{
    /**
     * @param  class-string  $model
     */
    public function __construct(
        public readonly string $column,
        public readonly string $model,
        public readonly string $labelColumn = 'name',
    ) {}
}
