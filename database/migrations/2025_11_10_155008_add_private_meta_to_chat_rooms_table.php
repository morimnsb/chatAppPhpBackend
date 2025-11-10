<?php

// database/migrations/2025_11_10_000110_add_private_meta_to_chat_rooms_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('chat_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_rooms', 'private_key')) {
                $table->string('private_key', 190)->nullable()->unique();
            }
            if (!Schema::hasColumn('chat_rooms', 'is_private')) {
                $table->boolean('is_private')->default(false);
            }
            if (!Schema::hasColumn('chat_rooms', 'name')) {
                $table->string('name')->nullable(); // به‌جای title از name استفاده می‌کنیم
            }
        });
    }
    public function down(): void {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropColumn(['private_key','is_private','name']);
        });
    }
};
