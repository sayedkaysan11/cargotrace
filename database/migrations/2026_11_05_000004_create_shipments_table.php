<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 30)->unique();
            $table->char('scenario', 1)->default('A')->index(); // group scenario A/B/C
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('carrier_id')->constrained('carriers')->restrictOnDelete();
            $table->foreignId('origin_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('destination_location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('cargo_type', 60);
            $table->decimal('total_weight_kg', 12, 2)->default(0);
            $table->decimal('total_volume_cbm', 10, 2)->default(0);

            $table->string('current_status', 20)->default('CREATED');

            $table->dateTime('expected_departure_at');
            $table->dateTime('expected_arrival_at');
            $table->dateTime('estimated_arrival_at')->nullable();   // ETA, recalculated by EtaService
            $table->dateTime('actual_departure_at')->nullable();
            $table->dateTime('actual_arrival_at')->nullable();

            // Cached delay result (source of truth = EtaService rule)
            $table->boolean('is_delayed')->default(false);
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('delay_severity', 10)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('current_status');
            $table->index('is_delayed');
            $table->index('expected_arrival_at');
            $table->index(['customer_id', 'current_status']);
            $table->index(['carrier_id', 'is_delayed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
