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
        Schema::create('menus', function (Blueprint $table) {
            $table->unsignedInteger('idmenu')->autoIncrement();
            $table->string('menu');
            $table->boolean('tipo');
            $table->unsignedInteger('superior')->nullable();
            $table->string('link')->nullable();
            $table->integer('orden');
            $table->boolean('visible');
            $table->boolean('newtab');
            $table->string('icono')->nullable();
            $table->timestamps();
            $table->foreign('superior')->references('idmenu')->on('menus')->onDelete('CASCADE')->onUpdate('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
