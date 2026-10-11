<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('house_enumerations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('house_id')->constrained()->restrictOnDelete();
            $table->foreignId('campaign_id')->constrained('census_campaigns')->restrictOnDelete();
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->constrained('blocks')->restrictOnDelete();
            $table->string('occupancy_status', 30);
            $table->string('status', 20)->default('draft');
            $table->foreignId('enumerator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('visited_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['house_id', 'campaign_id']);
            $table->index(['campaign_id', 'status', 'block_id']);
            $table->index(['campaign_id', 'enumerator_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('house_enumerations');
    }
};
