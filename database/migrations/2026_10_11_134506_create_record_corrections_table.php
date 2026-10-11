<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Requested changes to finalized census records. The before/after values
     * may contain personal data and are stored encrypted.
     */
    public function up(): void
    {
        Schema::create('record_corrections', function (Blueprint $table) {
            $table->id();
            $table->morphs('correctable');
            $table->foreignId('campaign_id')->constrained('census_campaigns')->restrictOnDelete();
            $table->text('changes');
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index();
            $table->string('source', 20)->default('web');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('record_corrections');
    }
};
