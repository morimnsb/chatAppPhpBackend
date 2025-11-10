<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFriendshipsTable extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();

            // جهت اولیه
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');

            // زوج canonical برای یکتا کردن
            $table->unsignedBigInteger('user_low_id');
            $table->unsignedBigInteger('user_high_id');

            $table->timestamps();

            $table->foreign('from_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('to_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_low_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_high_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['user_low_id', 'user_high_id'], 'uniq_friend_pair');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
}
