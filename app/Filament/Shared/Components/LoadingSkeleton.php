<?php

namespace App\Filament\Shared\Components;

use Illuminate\View\Component;

/**
 * Loading Skeleton Component.
 *
 * Displays animated skeleton placeholders while data is loading.
 * Supports different types: table, chart, stats, card.
 *
 * Usage:
 * <x-loading-skeleton type="table" :rows="5" />
 * <x-loading-skeleton type="chart" height="300px" />
 * <x-loading-skeleton type="stats" :count="4" />
 */
class LoadingSkeleton extends Component
{
    public string $type;

    public int $rows;

    public int $columns;

    public int $count;

    public string $height;

    /**
     * Create a new component instance.
     */
    public function __construct(
        string $type = 'card',
        int $rows = 5,
        int $columns = 4,
        int $count = 1,
        string $height = '200px'
    ) {
        $this->type = $type;
        $this->rows = $rows;
        $this->columns = $columns;
        $this->count = $count;
        $this->height = $height;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
        return view('filament.shared.components.loading-skeleton');
    }
}
