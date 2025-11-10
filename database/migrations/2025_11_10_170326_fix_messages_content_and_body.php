<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('messages', function (Blueprint $table) {
            // اگر content نیست، بساز
            if (!Schema::hasColumn('messages', 'content')) {
                $table->text('content')->nullable()->after('chat_room_id');
            }
            // اگر body وجود دارد و NOT NULL است، در SQLite مستقیماً تغییر قیود سخت است،
            // اما با nullable() در اکثر موارد اعمال می‌شود.
            if (Schema::hasColumn('messages', 'body')) {
                // تلاش برای nullable
                try {
                    $table->text('body')->nullable()->change();
                } catch (\Throwable $e) {
                    // در SQLite ممکن است change کار نکند؛ پایین‌تر با UPDATE جبران می‌کنیم.
                }
            }
            // ستون‌های ضروری
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

        // یک‌بار داده‌های قدیمی را کپی کن تا body خالی نماند
        if (Schema::hasColumn('messages', 'body')) {
            // content تهی را از body پر کن
            DB::statement("UPDATE messages SET content = COALESCE(content, body)");
        }
    }

    public function down(): void {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'content')) {
                $table->dropColumn('content');
            }
            // بقیه را دست نمی‌زنیم تا اطلاعات از بین نرود
        });
    }
};
