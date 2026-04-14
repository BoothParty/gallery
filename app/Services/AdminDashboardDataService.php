<?php

namespace App\Services;

use App\Models\Party;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class AdminDashboardDataService
{
    public function __construct(
        private readonly CrudDiscoveryService $crudDiscoveryService,
    ) {}

    public function sidebarData(?int $currentPartyId = null): array
    {
        $parties = Party::query()
            ->where(function ($query) {
                $query
                    ->whereNull('expire_at')
                    ->orWhere('expire_at', '>', now());
            })
            ->limit(10)
            ->get(['id', 'name'])
            ->map(fn (Party $availableParty) => [
                'id' => $availableParty->id,
                'name' => $availableParty->name,
                'expired' => false,
            ])
            ->values();

        $searchableParties = Party::query()
            ->get(['id', 'name', 'expire_at'])
            ->map(fn (Party $availableParty) => [
                'id' => $availableParty->id,
                'name' => $availableParty->name,
                'expired' => $availableParty->expire_at !== null && $availableParty->expire_at->isPast(),
            ])
            ->values();

        $defaultPartyId = $searchableParties->first()['id'] ?? null;
        $resolvedPartyId = $currentPartyId ?? $defaultPartyId;

        return [
            'currentPartyId' => $resolvedPartyId,
            'parties' => $parties,
            'searchableParties' => $searchableParties,
        ];
    }

    public function tableDataForResource(string $resourceSlug, int $limit = 50): array
    {
        $resource = $this->crudDiscoveryService->discover()[$resourceSlug] ?? null;

        if (! is_array($resource) || ! isset($resource['model']) || ! is_string($resource['model'])) {
            return [
                'columns' => [],
                'rows' => [],
            ];
        }

        $columns = is_array($resource['columns'] ?? null) ? $resource['columns'] : [];
        $columnNames = collect($columns)
            ->pluck('name')
            ->filter(fn (mixed $columnName) => is_string($columnName) && $columnName !== '')
            ->values();

        $modelClass = $resource['model'];

        if (! is_subclass_of($modelClass, Model::class)) {
            return [
                'columns' => $columns,
                'rows' => [],
            ];
        }

        $countWithClauses = collect($columns)
            ->filter(fn (array $column) => ($column['type'] ?? '') === 'count_link' && is_string($column['countRelation'] ?? null))
            ->map(fn (array $column) => $column['countRelation'].' as '.$column['name'])
            ->values()
            ->all();

        $records = $modelClass::query()
            ->when($countWithClauses !== [], fn ($query) => $query->withCount($countWithClauses))
            ->limit($limit)
            ->get();

        // Strip internal 'countRelation' field before sending columns to the frontend
        $columns = collect($columns)->map(function (array $column) {
            unset($column['countRelation']);

            return $column;
        })->all();

        $relationLabelMaps = collect($columns)
            ->filter(fn (array $column) => isset($column['name'], $column['relation']) && is_array($column['relation']))
            ->mapWithKeys(function (array $column) use ($records): array {
                $columnName = $column['name'];
                $relationModelClass = $column['relation']['model'] ?? null;
                $labelColumn = $column['relation']['labelColumn'] ?? null;

                if (! is_string($columnName) || ! is_string($relationModelClass) || ! is_string($labelColumn)) {
                    return [];
                }

                if (! is_subclass_of($relationModelClass, Model::class)) {
                    return [];
                }

                /** @var Model $relatedModelInstance */
                $relatedModelInstance = new $relationModelClass;
                $relatedModelKeyName = $relatedModelInstance->getKeyName();

                $foreignKeys = $records
                    ->pluck($columnName)
                    ->filter(fn (mixed $value) => $value !== null)
                    ->unique()
                    ->values();

                $labelMap = $relationModelClass::query()
                    ->whereIn($relatedModelKeyName, $foreignKeys)
                    ->pluck($labelColumn, $relatedModelKeyName)
                    ->all();

                return [$columnName => $labelMap];
            });

        $rows = $records
            ->map(function (Model $record) use ($columnNames, $relationLabelMaps): array {
                return $columnNames
                    ->mapWithKeys(function (string $columnName) use ($record, $relationLabelMaps): array {
                        $value = $record->{$columnName};

                        if (isset($relationLabelMaps[$columnName]) && $value !== null) {
                            $value = $relationLabelMaps[$columnName][$value] ?? $value;
                        }

                        if ($value instanceof DateTimeInterface) {
                            $value = $value->format(DATE_ATOM);
                        }

                        return [$columnName => $value];
                    })
                    ->all();
            })
            ->values();

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }
}
