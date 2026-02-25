<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digdeep_route_visits', function (Blueprint $table) {
            $table->id();
            $table->string('url', 2048);
            $table->string('method', 10)->default('GET');
            $table->unsignedInteger('visit_count')->default(1);
            $table->timestamp('last_visited_at');

            $table->unique(['url', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digdeep_route_visits');
    }
};
