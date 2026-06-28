<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_type', 32);
            $table->string('currency', 3)->default('BRL');
            $table->string('status', 32)->default('ACTIVE');
            $table->string('brand_name');
            $table->string('compe_code', 8)->nullable();
            $table->string('branch_code', 16)->nullable();
            $table->string('account_number', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('account_balances', function (Blueprint $table) {
            $table->uuid('account_id')->primary();
            $table->bigInteger('available_amount_cents')->default(0);
            $table->bigInteger('blocked_amount_cents')->default(0);
            $table->bigInteger('reserved_amount_cents')->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->index();
            $table->uuid('counterparty_account_id')->nullable();
            $table->string('type', 32);
            $table->bigInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('status', 32);
            $table->string('fraud_status', 32)->nullable();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('reference')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->string('consent_id')->primary();
            $table->string('status', 64);
            $table->json('permissions');
            $table->timestamp('expiration_date_time')->nullable();
            $table->timestamp('creation_date_time');
            $table->string('logged_user_document')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->uuid('payment_id')->primary();
            $table->string('consent_id')->index();
            $table->uuid('account_id')->nullable();
            $table->string('status', 16);
            $table->bigInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('local_instrument', 16)->default('DICT');
            $table->string('rejection_reason')->nullable();
            $table->uuid('correlation_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('organisation_id')->unique();
            $table->string('name');
            $table->json('roles');
            $table->string('base_url');
            $table->string('auth_server_url');
            $table->string('adapter')->default('open_banking_brasil');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('operations', function (Blueprint $table) {
            $table->uuid('correlation_id')->primary();
            $table->string('status', 32);
            $table->string('operation_type', 64);
            $table->uuid('resource_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('consents');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('account_balances');
        Schema::dropIfExists('wallet_accounts');
    }
};
