<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mkap_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('client_identifier', 128)->unique('uq_mkap_clients_identifier');
            $table->string('name', 255);
            $table->string('status', 32);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

        Schema::create('mkap_key_pools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('mkap_clients');
            $table->string('pool_identifier', 128);
            $table->unsignedInteger('pool_version');
            $table->unsignedSmallInteger('identifier_length');
            $table->string('identifier_alphabet', 256);
            $table->unsignedSmallInteger('secret_length_bytes')->default(32);
            $table->unsignedInteger('key_count');
            $table->unsignedInteger('selection_count');
            $table->string('status', 32);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(
                ['client_id', 'pool_identifier', 'pool_version'],
                'uq_mkap_pools_client_version'
            );
        });

        Schema::create('mkap_authentication_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained('mkap_key_pools');
            $table->string('public_identifier', 256);
            $table->binary('encrypted_secret');
            $table->unsignedInteger('encryption_key_version');
            $table->string('status', 32);
            $table->string('generation_batch_id', 128)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('revoked_at', 6)->nullable();
            $table->unique(['pool_id', 'public_identifier'], 'uq_mkap_keys_pool_identifier');
            $table->index(
                ['pool_id', 'status', 'public_identifier'],
                'idx_mkap_keys_pool_status_identifier'
            );
        });

        Schema::create('mkap_key_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained('mkap_key_pools');
            $table->unsignedInteger('export_version');
            $table->unsignedInteger('format_version');
            $table->unsignedInteger('key_count');
            $table->binary('checksum');
            $table->string('status', 32);
            $table->dateTime('created_at', 6);
            $table->dateTime('revoked_at', 6)->nullable();
            $table->unique(['pool_id', 'export_version'], 'uq_mkap_exports_pool_version');
        });

        Schema::create('mkap_key_generation_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained('mkap_key_pools');
            $table->string('batch_identifier', 128)->unique('uq_mkap_batches_identifier');
            $table->unsignedInteger('requested_count');
            $table->unsignedInteger('generated_count');
            $table->unsignedInteger('failed_count');
            $table->string('status', 32);
            $table->dateTime('started_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
            $table->index(['pool_id', 'status'], 'idx_mkap_batches_pool_status');
        });

        Schema::create('mkap_authentication_challenges', function (Blueprint $table): void {
            $table->id();
            $table->char('request_identifier', 16)->charset('binary')
                ->unique('uq_mkap_challenges_request');
            $table->foreignId('client_id')->constrained('mkap_clients');
            $table->foreignId('pool_id')->constrained('mkap_key_pools');
            $table->unsignedInteger('pool_version');
            $table->binary('nonce');
            $table->text('selected_key_identifiers');
            $table->string('http_method', 32);
            $table->string('request_target', 4096);
            $table->binary('body_sha256');
            $table->dateTime('expires_at', 6);
            $table->dateTime('consumed_at', 6)->nullable();
            $table->string('status', 32);
            $table->dateTime('created_at', 6);
            $table->index(['expires_at', 'status'], 'idx_mkap_challenges_expiry_status');
        });

        Schema::create('mkap_authentication_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('challenge_id')->nullable();
            $table->string('event_type', 64);
            $table->string('result', 32);
            $table->string('failure_reason', 255)->nullable();
            $table->binary('ip_address')->nullable();
            $table->dateTime('created_at', 6);
            $table->foreign('client_id')->references('id')->on('mkap_clients');
            $table->foreign('challenge_id')->references('id')->on('mkap_authentication_challenges');
            $table->index(['client_id', 'created_at'], 'idx_mkap_logs_client_created');
        });

        Schema::create('mkap_key_rotations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pool_id')->constrained('mkap_key_pools');
            $table->unsignedBigInteger('old_key_id');
            $table->unsignedBigInteger('new_key_id');
            $table->string('reason', 255);
            $table->dateTime('created_at', 6);
            $table->foreign('old_key_id')->references('id')->on('mkap_authentication_keys');
            $table->foreign('new_key_id')->references('id')->on('mkap_authentication_keys');
            $table->index(['pool_id', 'created_at'], 'idx_mkap_rotations_pool_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkap_key_rotations');
        Schema::dropIfExists('mkap_authentication_logs');
        Schema::dropIfExists('mkap_authentication_challenges');
        Schema::dropIfExists('mkap_key_generation_batches');
        Schema::dropIfExists('mkap_key_exports');
        Schema::dropIfExists('mkap_authentication_keys');
        Schema::dropIfExists('mkap_key_pools');
        Schema::dropIfExists('mkap_clients');
    }
};
