<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // People notified in addition to the reminder's owner (at most 3, enforced in validation).
        Schema::create('reminder_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->timestamps();

            $table->unique(['reminder_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_recipients');
    }
};
