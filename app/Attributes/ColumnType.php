<?php

namespace App\Attributes;

enum ColumnType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case DateTime = 'datetime';
    case Boolean = 'boolean';
    case Badge = 'badge';
    case Email = 'email';
    case Image = 'image';
    case Json = 'json';
    case CountLink = 'count_link';
}
