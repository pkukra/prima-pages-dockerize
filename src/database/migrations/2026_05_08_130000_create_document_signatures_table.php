<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->constrained('documents')
                ->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->decimal('x', 12, 8);
            $table->decimal('y', 12, 8);
            $table->decimal('width', 12, 8);
            $table->decimal('height', 12, 8);
            $table->string('signature_path');
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};

