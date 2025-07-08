<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('disk'); // e.g., 'public', 's3'
            $table->string('file_name');
            $table->unsignedBigInteger('file_size'); // Size in bytes
            $table->string('mime_type');
            $table->morphs('attachable'); // Adds attachable_id and attachable_type
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // <--- أضف هذا السطر هنا
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
