<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Birthday notifications keep the person's name in `title`, the address the
     * card goes to in `email`, and the birthday itself (the year is optional).
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('type', 20)->default('standard')->after('user_id');
            $table->string('email')->nullable()->after('message');
            $table->unsignedTinyInteger('birth_day')->nullable()->after('email');
            $table->unsignedTinyInteger('birth_month')->nullable()->after('birth_day');
            $table->unsignedSmallInteger('birth_year')->nullable()->after('birth_month');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['type', 'email', 'birth_day', 'birth_month', 'birth_year']);
        });
    }
};
