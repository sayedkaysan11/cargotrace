<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracking_event_id')->nullable()->constrained('tracking_events')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('severity', 10);
            $table->string('status', 15)->default('OPEN');
            $table->text('description')->nullable();
            $table->dateTime('detected_at');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['shipment_id', 'status']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_exceptions');
    }
};
