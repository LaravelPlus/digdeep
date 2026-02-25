<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digdeep_profiles', function (Blueprint $table) {
            $table->index('method');
            $table->index('status_code');
            $table->index('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('digdeep_profiles', function (Blueprint $table) {
            $table->dropIndex(['method']);
            $table->dropIndex(['status_code']);
            $table->dropIndex(['duration_ms']);
        });
    }
};
