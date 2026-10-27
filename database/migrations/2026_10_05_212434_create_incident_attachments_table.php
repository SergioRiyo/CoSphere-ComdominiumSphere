<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->string('original_name');
            $table->string('path')->unique();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamp('created_at');
            $table->index(['incident_id', 'uploaded_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_attachments');
    }
};
