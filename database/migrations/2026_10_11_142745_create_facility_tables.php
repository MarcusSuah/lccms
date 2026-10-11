<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Public and private service facilities. Each facility has exactly one type
     * and one ownership value, so it is counted once per reporting category.
     * Absence of a service is recorded explicitly in community_service_assessments;
     * a missing facility row never means "none".
     */
    public function up(): void
    {
        Schema::create('facility_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('category', 30)->index();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 80)->unique();
            $table->string('name');
            $table->foreignId('facility_type_id')->constrained()->restrictOnDelete();
            $table->string('category', 30);
            $table->string('ownership', 30);
            $table->string('operating_status', 30);
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('blocks')->restrictOnDelete();
            $table->string('address_description', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_name')->nullable();
            $table->text('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->unsignedSmallInteger('established_year')->nullable();
            $table->json('details')->nullable();
            $table->text('notes')->nullable();
            $table->string('verification_status', 20)->default('unverified');
            $table->boolean('is_public')->default(false);
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'ownership', 'status']);
            $table->index(['community_id', 'category']);
            $table->index(['county_id', 'category']);
        });

        Schema::create('facility_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->date('inspected_on');
            $table->foreignId('inspector_id')->constrained('users')->restrictOnDelete();
            $table->string('operating_status', 30);
            $table->string('condition', 20);
            $table->text('findings');
            $table->text('recommendations')->nullable();
            $table->date('follow_up_due_on')->nullable();
            $table->timestamps();

            $table->index(['facility_id', 'inspected_on']);
        });

        Schema::create('community_service_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->constrained('communities')->restrictOnDelete();
            $table->string('category', 30);
            $table->string('status', 30);
            $table->date('assessed_on');
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_service_assessments');
        Schema::dropIfExists('facility_inspections');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('facility_types');
    }
};
