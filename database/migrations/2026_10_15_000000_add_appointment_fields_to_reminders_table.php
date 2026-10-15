<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where and how an appointment happens; its people are recipients with a name.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('appointment_mode', 20)->nullable()->after('layout');
            $table->string('address')->nullable()->after('appointment_mode');
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('phone', 50)->nullable()->after('longitude');
            $table->string('meeting_url', 2048)->nullable()->after('phone');
        });

        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->string('name')->nullable()->after('reminder_id');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['appointment_mode', 'address', 'latitude', 'longitude', 'phone', 'meeting_url']);
        });

        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
