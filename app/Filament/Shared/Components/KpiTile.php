<?php

namespace App\Filament\Shared\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * KPI Tile component for displaying metrics.
 *
 * Displays a key performance indicator with optional trend indicator
 * showing percentage change from previous period.
 */
class KpiTile extends Component
{
    public string $trendDirection;

    public string $trendColor;

    public function __construct(
        public string $label,
        public string|int|float $value,
        public ?string $prefix = null,
        public ?string $suffix = null,
        public ?float $change = null,
        public ?string $changePeriod = 'vs last period',
        public ?string $icon = null,
        public string $color = 'primary',
    ) {
        $this->trendDirection = $this->calculateTrendDirection();
        $this->trendColor = $this->calculateTrendColor();
    }

    protected function calculateTrendDirection(): string
    {
        if ($this->change === null || $this->change === 0.0) {
            return 'neutral';
        }

        return $this->change > 0 ? 'up' : 'down';
    }

    protected function calculateTrendColor(): string
    {
        return match ($this->trendDirection) {
            'up' => 'text-green-600 dark:text-green-400',
            'down' => 'text-red-600 dark:text-red-400',
            default => 'text-gray-500 dark:text-gray-400',
        };
    }

    public function formattedChange(): string
    {
        if ($this->change === null) {
            return '';
        }

        $sign = $this->change >= 0 ? '+' : '';

        return $sign.number_format($this->change, 1).'%';
    }

    public function render(): View
    {
        return view('filament.shared.components.kpi-tile');
    }
}
