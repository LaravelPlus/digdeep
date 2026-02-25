<?php

namespace LaravelPlus\DigDeep\Models;

use Illuminate\Database\Eloquent\Model;

class DigDeepRouteVisit extends Model
{
    protected $table = 'digdeep_route_visits';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'visit_count' => 'integer',
            'last_visited_at' => 'datetime',
        ];
    }
}
