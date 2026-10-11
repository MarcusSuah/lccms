<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A household is a social unit living in one house, recorded per campaign.
     * Household size is never stored; it is derived from occupant rows.
     */
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference_code', 80)->unique();
            $table->foreignId('house_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('census_campaigns')->restrictOnDelete();
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->constrained('blocks')->restrictOnDelete();
            $table->unsignedTinyInteger('sequence_in_house');
            $table->string('contact_name')->nullable();
            $table->text('contact_phone')->nullable();
            $table->string('residence_type', 30)->default('usual');
            $table->string('status', 20)->default('draft');
            $table->foreignId('enumerator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('review_note')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['id', 'campaign_id']);
            $table->unique(['house_id', 'campaign_id', 'sequence_in_house']);
            $table->index(['campaign_id', 'status', 'block_id']);
            $table->index(['campaign_id', 'status', 'community_id']);
            $table->index(['campaign_id', 'enumerator_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('households');
    }
};
