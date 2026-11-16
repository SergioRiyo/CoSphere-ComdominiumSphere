<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('content');
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('announcement_id')->references('id')->on('announcements')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('announcements')->exists()) {
            throw new RuntimeException('Cannot roll back while announcements exist.');
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['announcement_id']);
        });
        Schema::dropIfExists('announcements');
    }
};
