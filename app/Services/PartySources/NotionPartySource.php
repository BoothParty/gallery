<?php

namespace App\Services\PartySources;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Notion\Databases\Query;
use Notion\Databases\Query\CompoundFilter;
use Notion\Databases\Query\DateFilter;
use Notion\Databases\Query\MultiSelectFilter;
use Notion\Databases\Query\Sort;
use Notion\Databases\Query\StatusFilter;
use Notion\Notion;
use Notion\Pages\Page;
use Notion\Pages\Properties\Date;
use Notion\Pages\Properties\RichTextProperty;
use Notion\Pages\Properties\Title;
use RuntimeException;

class NotionPartySource implements PartySource
{
    /**
     * Notion caps a query at 100 rows; the admin only needs the next events, so
     * the pagination is bounded rather than walking the whole database.
     */
    private const MAX_PAGES = 5;

    public function __construct(
        private readonly Notion $notion,
    ) {}

    public function key(): string
    {
        return 'notion';
    }

    public function label(): string
    {
        return 'Notion';
    }

    /**
     * Rows of the configured Notion database whose date is today or later.
     *
     * @return list<PartySourceEvent>
     */
    public function events(): array
    {
        $database = $this->notion->databases()->find($this->databaseId());
        $dateProperty = $this->dateProperty();
        $statusProperty = $this->statusProperty();
        $optionsProperty = $this->optionsProperty();

        $query = Query::create()
            ->changeFilter(
                CompoundFilter::and(
                    StatusFilter::property($statusProperty)->equals('Confirmé'),
                    MultiSelectFilter::property($optionsProperty)->contains('Galerie en ligne'),
                    DateFilter::property($dateProperty)->thisWeek(),
                )
            )
            ->changeSorts(Sort::property($dateProperty)->ascending())
            ->changePageSize(Query::MAX_PAGE_SIZE);

        $events = [];
        $remainingPages = self::MAX_PAGES;

        do {
            $result = $this->notion->databases()->query($database, $query);

            foreach ($result->pages as $page) {
                $event = $this->toEvent($page);

                if ($event !== null) {
                    $events[] = $event;
                }
            }

            $remainingPages--;

            if (! $result->hasMore || $result->nextCursor === null) {
                break;
            }

            $query = $query->changeStartCursor($result->nextCursor);
        } while ($remainingPages > 0);

        return $events;
    }

    /**
     * Rows without a usable name are skipped instead of failing the whole
     * import; a misconfigured property name is reported through the logs.
     */
    private function toEvent(Page $page): ?PartySourceEvent
    {
        $name = $this->name($page);

        if ($name === null || trim($name) === '') {
            Log::warning('Skipping a Notion event without a usable name.', [
                'page_id' => $page->id,
                'name_property' => $this->nameProperty(),
            ]);

            return null;
        }

        return new PartySourceEvent(
            id: $page->id,
            name: trim($name),
            startsAt: $this->startsAt($page),
        );
    }

    private function name(Page $page): ?string
    {
        $property = $this->property($page, $this->nameProperty());

        if ($property instanceof Title || $property instanceof RichTextProperty) {
            return $property->toString();
        }

        return $page->title()?->toString();
    }

    private function startsAt(Page $page): ?CarbonImmutable
    {
        $property = $this->property($page, $this->dateProperty());

        if (! $property instanceof Date) {
            return null;
        }

        $start = $property->start();

        return $start === null ? null : CarbonImmutable::instance($start);
    }

    private function property(Page $page, string $propertyName): mixed
    {
        return $page->properties()->getAll()[$propertyName] ?? null;
    }

    private function databaseId(): string
    {
        $databaseId = config('services.notion.database_id');

        if (! is_string($databaseId) || $databaseId === '') {
            throw new RuntimeException('The Notion party source requires NOTION_DATABASE_ID to be set.');
        }

        return $databaseId;
    }

    private function nameProperty(): string
    {
        return (string) config('services.notion.properties.name');
    }

    private function dateProperty(): string
    {
        return (string) config('services.notion.properties.date');
    }

    private function statusProperty(): string
    {
        return (string) config('services.notion.properties.status');
    }

    private function optionsProperty(): string
    {
        return (string) config('services.notion.properties.options');
    }
}
