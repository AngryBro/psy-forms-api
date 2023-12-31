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
        Schema::create('researches', function (Blueprint $table) {
            $table->id();
            $table->string("slug")->unique();
            $table->boolean("published");
            $table->integer("version");
            $table->foreignIdFor(App\Models\User::class)->nullable();
            $table->string("public_name")->nullable();
            $table->string("private_name")->nullable();
            $table->text("description")->nullable();
            $table->json("blocks");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('researches');
    }
};
