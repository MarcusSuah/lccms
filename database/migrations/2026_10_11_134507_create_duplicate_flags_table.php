<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Possible duplicate registrations awaiting human review. Nothing is
     * merged or deleted automatically.
     */
    public function up(): void
    {
        Schema::create('duplicate_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('census_campaigns')->restrictOnDelete();
            $table->foreignId('occupant_id')->constrained('occupants')->restrictOnDelete();
            $table->foreignId('matched_occupant_id')->constrained('occupants')->restrictOnDelete();
            $table->unsignedTinyInteger('score');
            $table->json('reasons');
            $table->string('status', 30)->default('open')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->unique(['occupant_id', 'matched_occupant_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_flags');
    }
};
