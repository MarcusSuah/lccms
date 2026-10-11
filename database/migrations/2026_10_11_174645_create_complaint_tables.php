<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Public complaints. Complaints are found by a random public reference and
     * a high-entropy tracking token whose hash only is stored; there is no
     * sequential identifier in any public URL.
     */
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('public_reference', 20)->unique();
            $table->char('tracking_token_hash', 64)->unique();
            $table->string('subject');
            $table->string('category', 30);
            $table->text('description');
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->nullable()->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->nullable()->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('blocks')->restrictOnDelete();
            $table->string('house_reference', 80)->nullable();
            $table->boolean('is_anonymous')->default(false);
            $table->text('complainant_name')->nullable();
            $table->text('complainant_phone')->nullable();
            $table->text('complainant_email')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->string('priority', 10)->default('normal');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_summary')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['county_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->string('disk', 20)->default('local');
            $table->string('path');
            $table->string('thumbnail_path');
            $table->string('mime', 50);
            $table->unsignedInteger('size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->char('sha256', 64);
            $table->string('original_name')->nullable();
            $table->timestamps();

            $table->unique(['complaint_id', 'slot']);
        });

        // The maximum of five images is a database invariant, not only a form rule.
        DB::statement('ALTER TABLE complaint_attachments ADD CONSTRAINT complaint_attachments_max_five CHECK (slot BETWEEN 1 AND 5)');

        Schema::create('complaint_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('public_response')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('complaint_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_notes');
        Schema::dropIfExists('complaint_status_history');
        Schema::dropIfExists('complaint_attachments');
        Schema::dropIfExists('complaints');
    }
};
