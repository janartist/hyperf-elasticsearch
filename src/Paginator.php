<?php

declare(strict_types=1);

namespace Janartist\Elasticsearch;

use Hyperf\Utils\Collection;
use Hyperf\Utils\Contracts\Arrayable;
use Hyperf\Utils\Contracts\Jsonable;
use JsonSerializable;

class Paginator implements Arrayable, JsonSerializable, Jsonable
{
    /**
     * Determine if there are more items in the data source.
     *
     * @return bool
     */
    protected $hasMore;

    protected $perPage;

    protected $currentPage;

    protected $items;

    /**
     * Create a new paginator instance.
     *
     * @param mixed $items
     */
    public function __construct($items, int $perPage, int $currentPage)
    {
        $this->items = $items;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;

        $this->setItems($items);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
    /**
     * Determine if there are more items in the data source.
     */
    public function hasMorePages(): bool
    {
        return $this->hasMore;
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        return [
            'current_page' => $this->currentPage,
            'per_page' => $this->perPage,
            'data' => $this->items->toArray(),
            'has_more' => $this->hasMorePages()
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }
    /**
     * Set the items for the paginator.
     * @param mixed $items
     */
    protected function setItems($items): void
    {
        $this->items = $items instanceof Collection ? $items : Collection::make($items);

        $this->hasMore = $this->items->count() > $this->perPage;

        $this->items = $this->items->slice(0, $this->perPage);
    }
}
