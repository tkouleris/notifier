<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The people an appointment is with. Unlike extra recipients, they may have
     * no email (then they are not emailed) and may have a phone number.
     */
    public function up(): void
    {
        Schema::create('appointment_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->timestamps();
        });

        // Appointments saved so far kept their people as named recipients; move them over.
        $appointments = DB::table('reminders')->where('type', 'appointment')->pluck('id');

        DB::table('reminder_recipients')->whereIn('reminder_id', $appointments)->orderBy('id')->get()
            ->each(fn ($recipient) => DB::table('appointment_participants')->insert([
                'reminder_id' => $recipient->reminder_id,
                'name' => $recipient->name ?? $recipient->email,
                'email' => $recipient->email,
                'created_at' => $recipient->created_at,
                'updated_at' => $recipient->updated_at,
            ]));

        DB::table('reminder_recipients')->whereIn('reminder_id', $appointments)->delete();

        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('reminder_recipients', function (Blueprint $table) {
            $table->string('name')->nullable()->after('reminder_id');
        });

        DB::table('appointment_participants')->whereNotNull('email')->orderBy('id')->get()
            ->each(fn ($participant) => DB::table('reminder_recipients')->insertOrIgnore([
                'reminder_id' => $participant->reminder_id,
                'name' => $participant->name,
                'email' => $participant->email,
                'created_at' => $participant->created_at,
                'updated_at' => $participant->updated_at,
            ]));

        Schema::dropIfExists('appointment_participants');
    }
};
