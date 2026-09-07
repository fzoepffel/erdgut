<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of weekly mercurial broadcasts. One row per send: the subject and
 * rich-text body actually mailed, a snapshot of the recipients it went to,
 * and who sent it. Kept for the record and to reuse a past issue as a
 * starting point for the next one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercurials', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->longText('body_html');
            // Snapshot [{email, name}] of who this issue was sent to.
            $table->json('recipients');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercurials');
    }
};
