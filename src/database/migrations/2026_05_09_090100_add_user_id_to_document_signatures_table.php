<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('document_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['document_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('document_signatures', function (Blueprint $table) {
            $table->dropIndex(['document_id', 'user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};

