<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('created_by')->default('system');
            $table->string('updated_by')->nullable();

            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
