<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('messages', function (Blueprint $table) {
            // اگر ستونی که API انتظار دارد نیست، بساز
            if (!Schema::hasColumn('messages', 'content')) {
                $table->text('content')->nullable()->after('chat_room_id');
            }
            // اطمینان از ستون‌های کلیدی
            if (!Schema::hasColumn('messages', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('chat_room_id');
            }
            if (!Schema::hasColumn('messages', 'chat_room_id')) {
                $table->unsignedBigInteger('chat_room_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('messages', 'created_at')) {
                $table->timestamps();
            }
        });

        // اگر قبلاً اسامی دیگری داشتید (message / text / body) آنها را به content کپی کن
        if (Schema::hasColumn('messages', 'message')) {
            DB::statement('UPDATE messages SET content = message WHERE content IS NULL');
        }
        if (Schema::hasColumn('messages', 'text')) {
            DB::statement('UPDATE messages SET content = text WHERE content IS NULL');
        }
        if (Schema::hasColumn('messages', 'body')) {
            DB::statement('UPDATE messages SET content = body WHERE content IS NULL');
        }
    }

    public function down(): void {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'content')) {
                $table->dropColumn('content');
            }
            // ستون‌های اضافه‌شده دیگر را اگر فقط همین مایگریشن ساخته، می‌توانید حذف کنید
            // ولی معمولاً نگه می‌داریم.
        });
    }
};
