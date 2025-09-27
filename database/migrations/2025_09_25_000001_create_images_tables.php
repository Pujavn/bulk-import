<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('public');
            $table->string('path'); // original image path
            $table->string('mime');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('sha256')->index(); // original file checksum
            $table->timestamps();
        });


        Schema::create('image_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('image_id')->constrained('images')->cascadeOnDelete();
            $table->unsignedInteger('max_side'); // 256/512/1024
            $table->string('path');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->timestamps();
            $table->unique(['image_id', 'max_side']);
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('image_variants');
        Schema::dropIfExists('images');
    }
};
