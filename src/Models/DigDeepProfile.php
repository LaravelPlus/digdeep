<?php

namespace LaravelPlus\DigDeep\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DigDeepProfile extends Model
{
    use HasUuids;

    protected $table = 'digdeep_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_ajax' => 'boolean',
            'duration_ms' => 'float',
            'memory_peak_mb' => 'float',
            'query_time_ms' => 'float',
            'query_count' => 'integer',
            'status_code' => 'integer',
        ];
    }

    public function scopeAjax(Builder $query): Builder
    {
        return $query->where('is_ajax', true);
    }

    public function scopePages(Builder $query): Builder
    {
        return $query->where('is_ajax', false);
    }
}
