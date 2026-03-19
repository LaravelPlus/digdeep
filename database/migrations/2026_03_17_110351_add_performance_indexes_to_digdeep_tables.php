<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digdeep_profiles', function (Blueprint $table) {
            // Composite indexes for common filtered queries
            $table->index(['status_code', 'created_at'], 'digdeep_profiles_status_created_index');
            $table->index(['duration_ms', 'created_at'], 'digdeep_profiles_duration_created_index');
        });

        Schema::table('digdeep_route_visits', function (Blueprint $table) {
            // Index for sorting by last visited time
            $table->index('last_visited_at', 'digdeep_route_visits_last_visited_index');
        });
    }

    public function down(): void
    {
        Schema::table('digdeep_profiles', function (Blueprint $table) {
            $table->dropIndex('digdeep_profiles_status_created_index');
            $table->dropIndex('digdeep_profiles_duration_created_index');
        });

        Schema::table('digdeep_route_visits', function (Blueprint $table) {
            $table->dropIndex('digdeep_route_visits_last_visited_index');
        });
    }
};
