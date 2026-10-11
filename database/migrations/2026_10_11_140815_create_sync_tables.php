<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Offline synchronisation log. op_uuid (the client's outbox id) is the
     * idempotency key: replaying an operation returns its stored result.
     */
    public function up(): void
    {
        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_uuid');
            $table->foreignId('device_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('census_campaigns')->restrictOnDelete();
            $table->unsignedSmallInteger('operation_count');
            $table->string('status', 20)->default('processing');
            $table->json('summary')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'batch_uuid']);
            $table->index(['campaign_id', 'received_at']);
        });

        Schema::create('sync_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('op_uuid')->unique();
            $table->foreignId('batch_id')->constrained('sync_batches')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('census_campaigns')->restrictOnDelete();
            $table->string('entity_type', 30);
            $table->uuid('entity_uuid');
            $table->string('operation', 20);
            $table->unsignedInteger('base_version')->nullable();
            $table->longText('payload');
            $table->char('payload_hash', 64);
            $table->string('status', 20);
            $table->string('error_code', 40)->nullable();
            $table->json('errors')->nullable();
            $table->json('result')->nullable();
            $table->unsignedBigInteger('conflict_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_uuid']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_operation_id')->constrained('sync_operations')->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('census_campaigns')->restrictOnDelete();
            $table->string('entity_type', 30);
            $table->unsignedBigInteger('entity_id');
            $table->uuid('entity_uuid');
            $table->string('conflict_class', 30);
            $table->unsignedInteger('base_version')->nullable();
            $table->unsignedInteger('server_version');
            $table->longText('server_snapshot');
            $table->longText('submitted_payload');
            $table->string('status', 30)->default('open');
            $table->foreignId('raised_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('county_id')->nullable()->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->nullable()->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->nullable()->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('blocks')->restrictOnDelete();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['raised_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('sync_batches');
    }
};
