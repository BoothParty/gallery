<?php

namespace App\Services;

use App\Attributes\ColumnType;
use App\Attributes\CrudColumn;
use App\Attributes\CrudCountColumn;
use App\Attributes\CrudRelationColumn;
use App\Attributes\CrudResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class CrudDiscoveryService
{
    private const RESOURCES_CACHE_KEY = 'admin.crud.resources';

    /**
     * Discover all models annotated with #[CrudResource].
     * Returns a map of [ 'slug' => ResourceDefinition ].
     *
     * The signature of the models directory is part of the cache key, so
     * editing a model's attributes lands immediately: the edit addresses a
     * different entry rather than having to invalidate the previous one.
     */
    public function discover(): array
    {
        return Cache::remember(
            self::RESOURCES_CACHE_KEY.'.'.$this->modelsSignature(),
            now()->addMinutes(5),
            fn (): array => $this->scan(),
        );
    }

    private function modelsSignature(): string
    {
        $modelPath = app_path('Models');

        if (! is_dir($modelPath)) {
            return 'no-models-directory';
        }

        $finder = (new Finder)->files()->name('*.php')->in($modelPath);

        $parts = [];

        foreach ($finder as $file) {
            $parts[] = $file->getRelativePathname().'|'.$file->getMTime().'|'.$file->getSize();
        }

        sort($parts);

        return sha1(implode(';', $parts));
    }

    private function scan(): array
    {
        $resources = [];
        $modelPath = app_path('Models');

        if (! is_dir($modelPath)) {
            return $resources;
        }

        $finder = (new Finder)->files()->name('*.php')->in($modelPath);

        foreach ($finder as $file) {
            $class = $this->classFromFile($file->getRealPath());

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $resourceAttrs = $reflection->getAttributes(CrudResource::class);

            if (empty($resourceAttrs)) {
                continue;
            }

            /** @var CrudResource $resource */
            $resource = $resourceAttrs[0]->newInstance();
            $slug = Str::slug($resource->labelPlural);

            $resources[$slug] = [
                'slug' => $slug,
                'model' => $class,
                'label' => $resource->label,
                'labelPlural' => $resource->labelPlural,
                'icon' => $resource->icon,
                'perPage' => $resource->perPage,
                'defaultSort' => $resource->defaultSort,
                'defaultSortDirection' => $resource->defaultSortDirection,
                'canCreate' => $resource->canCreate,
                'canEdit' => $resource->canEdit,
                'canDelete' => $resource->canDelete,
                'canExport' => $resource->canExport,
                'columns' => $this->resolveColumns($reflection),
            ];
        }

        // Sort by label for a consistent sidebar order
        uasort($resources, fn ($a, $b) => strcmp($a['label'], $b['label']));

        return $resources;
    }

    private function resolveColumns(ReflectionClass $reflection): array
    {
        $columns = [];
        $relationMap = $this->resolveRelationMap($reflection);
        $columnAttrs = $reflection->getAttributes(CrudColumn::class);

        foreach ($columnAttrs as $attr) {
            /** @var CrudColumn $col */
            $col = $attr->newInstance();
            $columns[] = [
                'name' => $col->name,
                'label' => $col->label,
                'type' => $col->type->value,
                'sortable' => $col->sortable,
                'searchable' => $col->searchable,
                'visible' => $col->visible,
                'format' => $col->format,
                'badge' => $col->badge,
                'relation' => $relationMap[$col->name] ?? null,
                'linkHref' => null,
                'countRelation' => null,
            ];
        }

        // Fallback: if no CrudColumn attributes, auto-discover from fillable + timestamps
        if (empty($columns)) {
            $columns = $this->inferColumnsFromModel($reflection, $relationMap);
        }

        // Append computed count columns declared via #[CrudCountColumn]
        foreach ($reflection->getAttributes(CrudCountColumn::class) as $attr) {
            /** @var CrudCountColumn $col */
            $col = $attr->newInstance();
            $columns[] = [
                'name' => $col->name,
                'label' => $col->label,
                'type' => ColumnType::CountLink->value,
                'sortable' => $col->sortable,
                'searchable' => false,
                'visible' => $col->visible,
                'format' => null,
                'badge' => null,
                'relation' => null,
                'linkHref' => $col->linkHref,
                'countRelation' => $col->relation,
            ];
        }

        return $columns;
    }

    private function resolveRelationMap(ReflectionClass $reflection): array
    {
        $relations = [];
        $relationAttrs = $reflection->getAttributes(CrudRelationColumn::class);

        foreach ($relationAttrs as $attr) {
            /** @var CrudRelationColumn $relation */
            $relation = $attr->newInstance();
            $relations[$relation->column] = [
                'model' => $relation->model,
                'labelColumn' => $relation->labelColumn,
            ];
        }

        return $relations;
    }

    private function inferColumnsFromModel(ReflectionClass $reflection, array $relationMap): array
    {
        $instance = $reflection->newInstanceWithoutConstructor();
        $hidden = method_exists($instance, 'getHidden') ? $instance->getHidden() : [];
        $fillable = method_exists($instance, 'getFillable') ? $instance->getFillable() : [];
        $casts = method_exists($instance, 'getCasts') ? $instance->getCasts() : [];
        $columns = [];

        foreach (['id', ...$fillable, 'created_at', 'updated_at'] as $field) {
            if (in_array($field, $hidden)) {
                continue;
            }

            $columns[] = [
                'name' => $field,
                'label' => Str::headline($field),
                'type' => $this->guessType($field, $casts),
                'sortable' => in_array($field, ['id', 'created_at', 'updated_at']),
                'searchable' => false,
                'visible' => $field !== 'id',
                'format' => null,
                'badge' => null,
                'relation' => $relationMap[$field] ?? null,
                'linkHref' => null,
                'countRelation' => null,
            ];
        }

        return $columns;
    }

    private function guessType(string $field, array $casts = []): string
    {
        $cast = strtolower((string) ($casts[$field] ?? ''));

        if (str_starts_with($cast, 'datetime') || str_starts_with($cast, 'immutable_datetime')) {
            return 'datetime';
        }

        if (str_starts_with($cast, 'date') || str_starts_with($cast, 'immutable_date')) {
            return 'date';
        }

        return match (true) {
            str_ends_with($field, '_at') => 'datetime',
            str_ends_with($field, '_date') => 'date',
            in_array($field, ['id', 'user_id', 'count', 'price',
                'amount', 'quantity', 'order']) => 'number',
            in_array($field, ['is_active', 'active', 'enabled',
                'published', 'visible']) => 'boolean',
            in_array($field, ['email', 'email_address']) => 'email',
            default => 'text',
        };
    }

    private function classFromFile(string $path): string
    {
        // Convert absolute path back to fully-qualified class name
        $relativePath = Str::after($path, app_path().DIRECTORY_SEPARATOR);
        $class = Str::replace(DIRECTORY_SEPARATOR, '\\', Str::beforeLast($relativePath, '.php'));

        return app()->getNamespace().$class;
    }
}
