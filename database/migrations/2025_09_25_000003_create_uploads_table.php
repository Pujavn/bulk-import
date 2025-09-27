<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique(); // returned to client
            $table->string('filename'); // client file name
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('total_chunks');
            $table->string('mime')->nullable();
            $table->string('sha256'); // expected full-file checksum
            $table->enum('status', ['initiated', 'in_progress', 'completed', 'failed'])->default('initiated');
            $table->json('meta')->nullable(); // arbitrary client hints
            $table->timestamps();
        });


        Schema::create('upload_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('uploads')->cascadeOnDelete();
            $table->unsignedInteger('index'); // 0-based
            $table->unsignedBigInteger('size');
            $table->string('sha256'); // chunk checksum (optional enforcement)
            $table->timestamps();
            $table->unique(['upload_id', 'index']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('upload_chunks');
        Schema::dropIfExists('uploads');
    }
};
