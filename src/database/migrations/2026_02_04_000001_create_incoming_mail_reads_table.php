<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_mail_reads', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));

            $table->uuid('incoming_mail_id');
            $table->foreign('incoming_mail_id')->references('id')->on('incoming_mails')->onDelete('cascade');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamp('read_at');
            $table->timestamps();

            // Ensure unique read record per mail per user
            $table->unique(['incoming_mail_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_mail_reads');
    }
};
