<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositions', function (Blueprint $table) {
            $table->text('resolved_note')->nullable()->after('status');
            $table->timestamp('resolved_at')->nullable()->after('resolved_note');
            $table->unsignedBigInteger('resolved_by_user_id')->nullable()->after('resolved_at');

            $table->foreign('resolved_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('dispositions', function (Blueprint $table) {
            $table->dropForeign(['resolved_by_user_id']);
            $table->dropColumn(['resolved_note', 'resolved_at', 'resolved_by_user_id']);
        });
    }
};
