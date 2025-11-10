<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('chat_rooms', function (Blueprint $table) {
            if (Schema::hasColumn('chat_rooms', 'name')) {
                try {
                    $table->string('name')->nullable()->change();
                } catch (\Throwable $e) {
                    // در SQLite ممکن است change کار نکند؛ اشکالی ندارد، در کد مقدار می‌دهیم.
                }
            } else {
                $table->string('name')->nullable();
            }

            if (!Schema::hasColumn('chat_rooms', 'private_key')) {
                $table->string('private_key')->nullable()->unique();
            }
            if (!Schema::hasColumn('chat_rooms', 'is_private')) {
                $table->boolean('is_private')->default(true);
            }
        });
    }

    public function down(): void {
        Schema::table('chat_rooms', function (Blueprint $table) {
            // چیزی را حذف نمی‌کنیم که اطلاعات از بین نرود
        });
    }
};
