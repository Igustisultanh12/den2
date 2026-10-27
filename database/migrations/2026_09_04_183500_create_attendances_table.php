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
        if (!Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->date('attendance_date');
                $table->time('time_in');
                $table->string('status')->default('hadir'); // hadir, dinas_luar, piket, izin
                $table->string('ip_address', 45)->nullable();
                $table->string('device')->nullable(); // Merk HP dan serinya
                $table->text('user_agent')->nullable();
                $table->double('latitude')->nullable();
                $table->double('longitude')->nullable();
                $table->string('location_name')->nullable();
                $table->text('notes')->nullable();
                $table->string('photo')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'attendance_date']);
                $table->index('attendance_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
