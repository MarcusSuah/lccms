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
        Schema::create('infrastructure_assets', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 80)->unique();
            $table->string('name');
            $table->string('category', 30);
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('blocks')->restrictOnDelete();
            $table->string('condition', 30)->default('unknown');
            $table->string('availability', 30)->default('available');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->date('last_inspected_on')->nullable();
            $table->text('maintenance_needs')->nullable();
            $table->string('responsible_organization')->nullable();
            $table->json('details')->nullable();
            $table->text('notes')->nullable();
            $table->string('verification_status', 20)->default('unverified');
            $table->boolean('is_public')->default(false);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['community_id', 'category']);
            $table->index(['county_id', 'category', 'condition']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infrastructure_assets');
    }
};
