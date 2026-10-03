<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each reminder gets one final date plus up to 4 optional earlier ones, each
     * sent on its own. The single date that lived on `reminders` becomes the final one.
     */
    public function up(): void
    {
        Schema::create('reminder_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->dateTime('notify_at');
            $table->boolean('is_final')->default(false);
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'notify_at']);
        });

        DB::table('reminder_dates')->insertUsing(
            ['reminder_id', 'notify_at', 'is_final', 'status', 'sent_at', 'created_at', 'updated_at'],
            DB::table('reminders')->select('id', 'notify_at', DB::raw('1'), 'status', 'sent_at', 'created_at', 'updated_at')
        );

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex(['status', 'notify_at']);
            $table->dropColumn(['notify_at', 'status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dateTime('notify_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
        });

        DB::table('reminder_dates')->where('is_final', true)->orderBy('id')->each(function ($date) {
            DB::table('reminders')->where('id', $date->reminder_id)->update([
                'notify_at' => $date->notify_at,
                'status' => $date->status,
                'sent_at' => $date->sent_at,
            ]);
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->index(['status', 'notify_at']);
        });

        Schema::dropIfExists('reminder_dates');
    }
};
