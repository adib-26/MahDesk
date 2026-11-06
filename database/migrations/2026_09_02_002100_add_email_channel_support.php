<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // The customer-facing address (e.g. support@company.com) that is
            // forwarded to this workspace's inbound routing address.
            $table->string('support_email')->nullable()->unique()->after('slug');
        });

        Schema::table('ticket_messages', function (Blueprint $table) {
            // RFC 5322 Message-ID (without angle brackets) of inbound customer
            // emails and outbound notifications, used to thread replies.
            $table->string('message_id')->nullable()->index()->after('meta');
        });

        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('message_id');
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_email');
            $table->string('subject')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('status_reason')->nullable();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'message_id']);
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');

        Schema::table('ticket_messages', function (Blueprint $table) {
            $table->dropIndex(['message_id']);
            $table->dropColumn('message_id');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropUnique(['support_email']);
            $table->dropColumn('support_email');
        });
    }
};
