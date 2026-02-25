<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digdeep_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('method', 10);
            $table->text('url');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->float('duration_ms')->default(0);
            $table->float('memory_peak_mb')->default(0);
            $table->unsignedInteger('query_count')->default(0);
            $table->float('query_time_ms')->default(0);
            $table->boolean('is_ajax')->default(false);
            $table->string('tags')->default('');
            $table->string('notes')->default('');
            $table->json('data');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('digdeep_profiles');
    }
};
