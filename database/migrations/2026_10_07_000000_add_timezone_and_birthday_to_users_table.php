<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The timezone moves from each notification form to a per-user setting. Existing
     * users start with the timezone of the notification they created most recently.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 64)->default('UTC')->after('theme');
            $table->date('birthday')->nullable()->after('timezone');
        });

        DB::table('reminders')
            ->select('user_id', 'timezone')
            ->whereIn('id', DB::table('reminders')->selectRaw('max(id)')->groupBy('user_id'))
            ->orderBy('user_id')
            ->each(function ($latest) {
                DB::table('users')->where('id', $latest->user_id)->update(['timezone' => $latest->timezone]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'birthday']);
        });
    }
};
