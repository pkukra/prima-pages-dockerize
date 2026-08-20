<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('created_by');
            $table->string('updated_by')->nullable();
            $table->softDeletes();

            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->foreignId('owner_id')
                ->references('id')
                ->on('document_owners');
                
            $table->foreignId('type_id')
                ->references('id')
                ->on('document_types');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
