<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('status');
            $table->index('created_at');
            $table->index('first_response_at');
            $table->index('concluded_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['first_response_at']);
            $table->dropIndex(['concluded_at']);
            $table->dropIndex(['status', 'created_at']);
        });
    }
};
