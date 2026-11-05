<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique();          // UN/LOCODE, e.g. IDJKT
            $table->string('type', 20)->default('port');   // port, airport, warehouse, city
            $table->string('city');
            $table->string('country');
            $table->char('country_code', 2);
            $table->string('region', 20)->nullable()->index(); // SEA, TRANSIT, EUR
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
