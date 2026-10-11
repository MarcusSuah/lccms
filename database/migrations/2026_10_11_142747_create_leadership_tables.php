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
     * Leadership history. Tenures are never deleted when a new leader takes
     * office; the previous tenure is closed with an end date.
     */
    public function up(): void
    {
        Schema::create('leadership_positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('title');
            $table->string('level', 20);
            $table->boolean('single_holder')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('leadership_tenures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('leadership_positions')->restrictOnDelete();
            $table->string('level', 20);
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('blocks')->restrictOnDelete();
            $table->string('holder_name');
            $table->text('contact_phone')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('selection_method', 30);
            $table->string('verification_status', 20)->default('unverified');
            $table->boolean('is_public')->default(false);
            $table->text('exception_note')->nullable();
            $table->foreignId('exception_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['position_id', 'community_id', 'block_id', 'starts_on'], 'leadership_tenures_slot_index');
        });

        DB::statement("ALTER TABLE leadership_tenures ADD CONSTRAINT leadership_tenures_area_matches_level CHECK (
            (level = 'community' AND block_id IS NULL) OR (level = 'block' AND block_id IS NOT NULL)
        )");
        DB::statement('ALTER TABLE leadership_tenures ADD CONSTRAINT leadership_tenures_dates CHECK (ends_on IS NULL OR ends_on >= starts_on)');

        // At most one open-ended tenure per position and area, unless an
        // authorised exception is recorded (e.g. co-chairs by local custom).
        DB::statement("ALTER TABLE leadership_tenures ADD COLUMN current_marker VARCHAR(64)
            GENERATED ALWAYS AS (IF(ends_on IS NULL AND exception_approved_by IS NULL,
                CONCAT(position_id, ':', community_id, ':', IFNULL(block_id, 0)), NULL)) STORED");
        DB::statement('CREATE UNIQUE INDEX leadership_tenures_one_current_holder ON leadership_tenures (current_marker)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leadership_tenures');
        Schema::dropIfExists('leadership_positions');
    }
};
