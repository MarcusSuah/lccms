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
        Schema::create('geo_reassignments', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20);
            $table->unsignedBigInteger('area_id');
            $table->string('area_reference_code', 60);
            $table->string('area_name');
            $table->unsignedBigInteger('from_parent_id');
            $table->unsignedBigInteger('to_parent_id');
            $table->json('from_path');
            $table->json('to_path');
            $table->text('reason');
            $table->date('effective_on');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->json('affected_counts')->nullable();
            $table->timestamps();

            $table->index(['level', 'area_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geo_reassignments');
    }
};
