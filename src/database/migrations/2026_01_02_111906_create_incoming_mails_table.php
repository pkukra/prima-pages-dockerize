<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_mails', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->timestamps();
            $table->string('created_by');
            $table->string('updated_by')->nullable();

            $table->string('mail_number')->unique();
            $table->string('sender');
            $table->string('subject');
            $table->date('mail_date');
            $table->date('received_date');
            $table->text('summary')->nullable();
            $table->string('file_path')->nullable();

            $table->string('status_code', 30)->nullable();
            $table->foreign('status_code')
                ->references('code')
                ->on('mail_statuses')
                ->onUpdate('no action')
                ->onDelete('no action');

            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->foreign('recipient_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_mails');
    }
};
