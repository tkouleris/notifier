<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How often a notification repeats, its first occurrence as a local time (so it
     * survives timezone changes) and its reminders as offsets before each occurrence.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('repeat', 10)->nullable()->after('email_theme');
            $table->string('repeat_anchor', 16)->nullable()->after('repeat');
            $table->json('reminder_offsets')->nullable()->after('repeat_anchor');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['repeat', 'repeat_anchor', 'reminder_offsets']);
        });
    }
};
