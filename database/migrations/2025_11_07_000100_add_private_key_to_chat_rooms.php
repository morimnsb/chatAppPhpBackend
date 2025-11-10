<?php
// database/migrations/2025_11_07_000100_add_private_key_to_chat_rooms.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('chat_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('chat_rooms', 'is_private')) {
                $table->boolean('is_private')->default(false)->index();
            }
            if (!Schema::hasColumn('chat_rooms', 'private_key')) {
                $table->string('private_key', 64)->nullable()->unique();
            }
        });
    }
    public function down(): void {
        Schema::table('chat_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('chat_rooms', 'private_key')) {
                $table->dropUnique(['private_key']);
                $table->dropColumn('private_key');
            }
            if (Schema::hasColumn('chat_rooms', 'is_private')) {
                $table->dropColumn('is_private');
            }
        });
    }
};
