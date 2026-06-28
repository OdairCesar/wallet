<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->string('client_id')->default('');
            $table->string('route')->default('');
            $table->unique(['client_id', 'route', 'key']);
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique(['client_id', 'route', 'key']);
            $table->dropColumn(['client_id', 'route']);
            $table->unique('key');
        });
    }
};
