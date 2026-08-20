<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tilaka_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('UUID()'));
            $table->timestamps();
            $table->string('created_by');
            $table->string('updated_by')->nullable();

            // Foreign key to users
            $table->unsignedBigInteger('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Tilaka UUID
            $table->uuid('tilaka_uuid')->nullable()->unique();

            // Identity data
            $table->string('nik', 16);
            $table->string('full_name');
            $table->string('email');
            $table->string('phone')->nullable();

            // Document paths
            $table->string('photo_ktp_path')->nullable();
            $table->string('selfie_path')->nullable();

            // Verification status
            $table->enum('verification_status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->text('rejection_reason')->nullable();

            // Index for faster lookups
            $table->index('user_id');
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tilaka_profiles');
    }
};
