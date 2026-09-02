<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            // reply = public message, note = internal only, event = system activity entry.
            $table->string('kind')->default('reply');
            $table->boolean('is_from_contact')->default(false);
            $table->text('body');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_messages');
    }
};
