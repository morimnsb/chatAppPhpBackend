<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $table) {
      if (!Schema::hasColumn('users', 'first_name')) {
        $table->string('first_name')->nullable()->after('id');
      }
      if (!Schema::hasColumn('users', 'last_name')) {
        $table->string('last_name')->nullable()->after('first_name');
      }
      // اگر قبلاً فقط ستون name داشتید و می‌خواهید نگه دارید، دست نزنید.
      // در غیر این صورت می‌توانید بعدها name را از کد حذف کنید.
    });
  }

  public function down(): void {
    Schema::table('users', function (Blueprint $table) {
      if (Schema::hasColumn('users', 'first_name')) {
        $table->dropColumn('first_name');
      }
      if (Schema::hasColumn('users', 'last_name')) {
        $table->dropColumn('last_name');
      }
    });
  }
};
