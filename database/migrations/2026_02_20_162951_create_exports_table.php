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
    Schema::create('exports', function (Blueprint $table) {
        $table->id();

        $table->string('type')->default('biologicos'); // por si luego exportas otras cosas
        $table->string('status')->default('queued');   // queued|processing|completed|failed
        $table->unsignedTinyInteger('progress')->default(0); // 0-100

        $table->string('batch_id')->nullable(); // luego lo usamos con Bus::batch
        $table->string('final_path')->nullable(); // excel final
        $table->json('params'); // catalogo, cubo, clues, filtros

        $table->text('error')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
