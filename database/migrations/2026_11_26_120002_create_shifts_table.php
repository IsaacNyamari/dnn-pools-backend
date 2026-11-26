<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('attendant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('device_id', 64)->default('');
            $table->string('location_id', 32)->default('MAIN');
            $table->dateTime('start_time', 6);
            $table->dateTime('end_time', 6);
            $table->unsignedInteger('total_cash')->default(0);
            $table->unsignedInteger('total_mpesa')->default(0);
            $table->dateTime('created_at', 6);

            $table->unique(['institution_id', 'attendant_id', 'device_id', 'start_time'], 'shifts_dedupe');
            $table->index(['institution_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
