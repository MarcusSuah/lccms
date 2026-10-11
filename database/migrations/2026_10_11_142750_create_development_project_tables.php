<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Development projects may be located at any level from county to
     * community (the deepest non-null path column is the project area).
     */
    public function up(): void
    {
        Schema::create('development_projects', function (Blueprint $table) {
            $table->id();
            $table->string('reference_code', 80)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 30);
            $table->foreignId('county_id')->constrained('counties')->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained('districts')->restrictOnDelete();
            $table->foreignId('clan_id')->nullable()->constrained('clans')->restrictOnDelete();
            $table->foreignId('town_id')->nullable()->constrained('towns')->restrictOnDelete();
            $table->foreignId('community_id')->nullable()->constrained('communities')->restrictOnDelete();
            $table->foreignId('block_id')->nullable()->constrained('blocks')->restrictOnDelete();
            $table->string('status', 30)->default('planned');
            $table->date('start_date')->nullable();
            $table->date('target_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('sponsor')->nullable();
            $table->string('responsible_organization')->nullable();
            $table->decimal('budget_amount', 15, 2)->nullable();
            $table->string('budget_currency', 3)->nullable();
            $table->boolean('budget_is_public')->default(false);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('verification_status', 20)->default('unverified');
            $table->boolean('is_public')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['county_id', 'status']);
            $table->index(['community_id', 'status']);
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('development_projects')->cascadeOnDelete();
            $table->string('title');
            $table->date('due_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('project_progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('development_projects')->cascadeOnDelete();
            $table->date('reported_on');
            $table->unsignedTinyInteger('progress_percent');
            $table->text('summary');
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_progress_reports');
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('development_projects');
    }
};
