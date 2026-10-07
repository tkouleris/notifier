<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The name a birthday card is signed with; empty means the owner's name.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('sender_name')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn('sender_name');
        });
    }
};
