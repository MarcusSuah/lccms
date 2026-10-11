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
     * One row per person per campaign. Only approved methodology fields are
     * stored; there is deliberately no national identification number.
     */
    public function up(): void
    {
        Schema::create('occupants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference_code', 80)->unique();
            $table->unsignedBigInteger('household_id');
            $table->foreignId('campaign_id')->constrained('census_campaigns')->restrictOnDelete();
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->constrained('blocks')->restrictOnDelete();
            $table->string('full_name');
            $table->string('normalized_name');
            $table->date('date_of_birth')->nullable();
            $table->unsignedTinyInteger('age_years')->nullable();
            $table->boolean('age_is_estimated')->default(false);
            $table->string('sex', 20);
            $table->string('relationship_to_head', 30);
            $table->boolean('usual_resident')->default(true);
            $table->string('school_attendance', 30)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('enumerator_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->foreign(['household_id', 'campaign_id'])
                ->references(['id', 'campaign_id'])
                ->on('households')
                ->restrictOnDelete();

            $table->index(['household_id', 'status']);
            $table->index(['campaign_id', 'status', 'block_id']);
            $table->index(['campaign_id', 'status', 'community_id']);
            $table->index(['campaign_id', 'normalized_name']);
        });

        // At most one current head per household, enforced by the database.
        DB::statement("ALTER TABLE occupants ADD COLUMN head_marker BIGINT UNSIGNED
            GENERATED ALWAYS AS (IF(relationship_to_head = 'head' AND status <> 'archived', household_id, NULL)) STORED");
        DB::statement('CREATE UNIQUE INDEX occupants_one_head_per_household ON occupants (head_marker)');

        DB::statement('ALTER TABLE occupants ADD CONSTRAINT occupants_age_present CHECK (date_of_birth IS NOT NULL OR age_years IS NOT NULL)');
        DB::statement('ALTER TABLE occupants ADD CONSTRAINT occupants_age_range CHECK (age_years IS NULL OR age_years <= 130)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('occupants');
    }
};
