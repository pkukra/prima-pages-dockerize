<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->timestamps();
            $table->string('created_by');
            $table->string('updated_by')->nullable();

            $table->uuid('incoming_mail_id');
            $table->foreign('incoming_mail_id')->references('id')->on('incoming_mails')->onDelete('cascade');

            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            
            $table->unsignedBigInteger('to_unit_id')->nullable();
            $table->foreign('to_unit_id')->references('id')->on('units')->onDelete('set null');

            $table->text('instruction')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('open');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositions');
    }
};
