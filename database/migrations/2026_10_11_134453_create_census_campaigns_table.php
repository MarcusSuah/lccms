<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('census_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('reference_date');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE census_campaigns ADD CONSTRAINT census_campaigns_dates CHECK (end_date >= start_date)');

        Schema::create('campaign_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('census_campaigns')->cascadeOnDelete();
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->nullable()->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->nullable()->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('blocks')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_scopes');
        Schema::dropIfExists('census_campaigns');
    }
};
