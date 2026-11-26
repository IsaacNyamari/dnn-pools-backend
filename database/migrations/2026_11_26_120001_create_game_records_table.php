<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_records', function (Blueprint $table) {
            $table->id();
            // "restrict": history must never disappear because an institution or a user is removed.
            $table->foreignId('institution_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('attendant_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('device_id', 64)->default('');
            $table->string('table_id', 8);
            $table->string('location_id', 32)->default('MAIN');
            $table->string('code', 6);
            $table->string('payment_method', 32)->nullable();
            $table->string('status', 16);
            $table->dateTime('local_timestamp', 6);
            // Server clock: created_at - local_timestamp is the sync delay.
            $table->dateTime('created_at', 6);

            $table->unique(['institution_id', 'table_id', 'code', 'local_timestamp'], 'game_records_dedupe');
            $table->index(['institution_id', 'local_timestamp']);
            $table->index(['institution_id', 'attendant_id', 'local_timestamp']);
            $table->index('local_timestamp');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_records');
    }
};
