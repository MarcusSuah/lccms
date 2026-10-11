<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Photographs for communities, facilities, infrastructure, events and posts.
     * Files live on private storage and are streamed only when approved,
     * public and cleared for consent.
     */
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->nullableMorphs('mediable');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('thumbnail_path');
            $table->string('mime', 50);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->char('sha256', 64)->index();
            $table->string('original_name')->nullable();
            $table->string('caption', 500)->nullable();
            $table->string('alt_text', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('visibility', 20)->default('internal');
            $table->string('approval_status', 20)->default('pending');
            $table->string('consent_status', 20)->default('not_required');
            $table->boolean('shows_identifiable_people')->default(false);
            $table->boolean('shows_children')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id', 'approval_status', 'visibility'], 'media_items_public_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};
