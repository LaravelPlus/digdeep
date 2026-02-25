<?php

namespace LaravelPlus\DigDeep\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ThresholdExceeded
{
    use Dispatchable;

    /**
     * @param  array<int, string>  $exceededThresholds
     * @param  array{duration_ms: float, memory_peak_mb: float, query_count: int, query_time_ms: float}  $profileSummary
     */
    public function __construct(
        public string $profileId,
        public array $exceededThresholds,
        public array $profileSummary,
    ) {}
}
