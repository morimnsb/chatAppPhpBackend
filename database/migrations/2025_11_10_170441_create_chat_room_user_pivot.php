<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('chat_room_user')) {
            Schema::create('chat_room_user', function (Blueprint $table) {
                $table->unsignedBigInteger('chat_room_id');
                $table->unsignedBigInteger('user_id');
                $table->primary(['chat_room_id','user_id']);
                // اگر کلید خارجی نداشتید، بعداً اضافه کنید. برای SQLite می‌توان ساده نگه داشت.
            });
        }
    }

    public function down(): void {
        Schema::dropIfExists('chat_room_user');
    }
};
