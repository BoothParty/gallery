<?php

namespace App\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class CrudCountColumn
{
    /**
     * @param  string  $name  Virtual column name used as the row key (e.g. 'parties_count')
     * @param  string  $label  Display label shown in the table header
     * @param  string  $relation  Eloquent relation method name on the model (e.g. 'parties')
     * @param  string  $linkHref  URL to navigate to when the user clicks the arrow
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $relation,
        public readonly string $linkHref,
        public readonly bool $sortable = false,
        public readonly bool $visible = true,
    ) {}
}
