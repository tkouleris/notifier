<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            // Stored in UTC; `timezone` is the user's zone, used to display and edit it.
            $table->dateTime('notify_at');
            $table->string('timezone')->default('UTC');
            $table->string('channel', 20)->default('email');
            $table->string('status', 20)->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'notify_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
