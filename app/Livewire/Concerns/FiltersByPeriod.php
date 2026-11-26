<?php

namespace App\Livewire\Concerns;

use App\Support\Reporting;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Date range and attendant filters, kept in the URL.
 */
trait FiltersByPeriod
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $attendant = '';

    public function updatedFiltersByPeriod(string $property): void
    {
        // Pagination itself is also a property update, which must not reset the page.
        if (in_array($property, ['from', 'to', 'attendant'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('from', 'to', 'attendant');
        $this->resetPage();
    }

    /**
     * Apply the filters to a query that is already limited to one institution.
     *
     * @template TQuery of Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    protected function applyFilters(Builder $query, string $timeColumn): Builder
    {
        Reporting::between($query, $timeColumn, $this->from, $this->to);

        if (ctype_digit($this->attendant)) {
            $query->where('attendant_id', (int) $this->attendant);
        }

        return $query;
    }
}
