<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_mails', function (Blueprint $table) {
            $table->unsignedBigInteger('incoming_mail_type_id')
                ->nullable()
                ->after('status_code');

            $table->foreign('incoming_mail_type_id')
                ->references('id')
                ->on('incoming_mails_type')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('incoming_mails', function (Blueprint $table) {
            $table->dropForeign(['incoming_mail_type_id']);
            $table->dropColumn('incoming_mail_type_id');
        });
    }
};

