<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->timestamps();
            $table->string('created_by');
            $table->string('updated_by')->nullable();

            $table->string('code', 30)->unique();   // misal: 'new', 'registered', dll
            $table->string('name');                 // misal: 'Baru', 'Dicatat', dll
            $table->string('type', 20)->default('incoming'); // incoming, outgoing, internal
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_statuses');
    }
};
