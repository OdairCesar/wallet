<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_accounts', function (Blueprint $table) {
            $table->string('consent_id');
            $table->uuid('account_id');
            $table->timestamps();

            $table->primary(['consent_id', 'account_id']);
            $table->foreign('consent_id')->references('consent_id')->on('consents')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('wallet_accounts')->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('document', 14)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('document');
        });

        Schema::dropIfExists('consent_accounts');
    }
};
