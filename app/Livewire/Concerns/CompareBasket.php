<?php

namespace App\Livewire\Concerns;

/** The compare basket (up to 4 price option ids), kept in the session like the old app's sessionStorage. */
trait CompareBasket
{
    public const BASKET_KEY = 'compare_ids';

    public const BASKET_MAX = 4;

    public function basket(): array
    {
        return array_values(array_filter((array) session(self::BASKET_KEY, []), 'is_string'));
    }

    protected function setBasket(array $ids): void
    {
        session([self::BASKET_KEY => array_slice(array_values(array_unique($ids)), 0, self::BASKET_MAX)]);
    }

    public function toggleCompare(string $id): void
    {
        $ids = $this->basket();
        $this->setBasket(in_array($id, $ids, true) ? array_diff($ids, [$id]) : [...$ids, $id]);
    }

    public function removeCompare(string $id): void
    {
        $this->setBasket(array_diff($this->basket(), [$id]));
    }

    public function clearCompare(): void
    {
        $this->setBasket([]);
    }
}
