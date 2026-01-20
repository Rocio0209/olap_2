<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->unsignedInteger('idestado');
            $table->unsignedInteger('idmunicipio');
            $table->unsignedInteger('idlocalidad');
            $table->string('clues',15);
            $table->unsignedInteger('idtipo_unidad');
            $table->unsignedInteger('idtipologia_unidad');
            $table->unsignedInteger('idinstitucion');
            $table->string('nombre');
            $table->unsignedInteger('idestrato');
            $table->unsignedInteger('idtipo_vialidad');
            $table->string('vialidad')->nullable();
            $table->unsignedInteger('idtipo_asentamiento');
            $table->string('asentamiento')->nullable();
            $table->string('nointerior',40)->nullable();
            $table->string('noexterior',40)->nullable();
            $table->string('cp',5)->nullable();
            $table->unsignedInteger('idtipo_administracion')->default(4);
            $table->string('latitud',40)->nullable();
            $table->string('longitud',40)->nullable();
            $table->string('email',200)->nullable();
            $table->string('telefono',40)->nullable();
            $table->date('construccion')->nullable();
            $table->date('inicio_operacion')->nullable();
            $table->unsignedInteger('idstatus_unidad')->default(1);
            $table->longText('horarios')->nullable();
            $table->unsignedInteger('idmotivo_baja')->nullable();
            $table->date('fecha_efectiva_baja')->nullable();
            $table->unsignedInteger('idtipo_establecimiento')->nullable();
            $table->unsignedInteger('idsubtipologia')->nullable();
            $table->string('nombre_responsable')->nullable();
            $table->string('pa_responsable')->nullable();
            $table->string('sa_responsable')->nullable();
            $table->unsignedInteger('idprofesion')->nullable();
            $table->string('cedula_responsable')->nullable();
            $table->unsignedInteger('idmarca_um')->nullable();
            $table->string('marca_esp_um')->nullable();
            $table->string('modelo_um')->nullable();
            $table->string('idprograma_um',20)->nullable();
            $table->string('idtipo_um',20)->nullable();
            $table->unsignedInteger('idtipologia_um')->nullable();
            $table->unsignedInteger('idnivel_atencion')->nullable();
            $table->unsignedInteger('idstatus_propiedad')->nullable();

            $table->primary('clues');

            $table->foreign(['idestado','idmunicipio','idlocalidad'])->references(['idestado','idmunicipio','idlocalidad'])->on('localidades')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idtipo_unidad')->references('idtipo_unidad')->on('tipos_unidades')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idtipologia_unidad')->references('idtipologia_unidad')->on('tipologias_unidades')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idinstitucion')->references('idinstitucion')->on('instituciones')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idestrato')->references('idestrato')->on('estratos')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idtipo_vialidad')->references('idtipo_vialidad')->on('tipos_vialidades')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idtipo_asentamiento')->references('idtipo_asentamiento')->on('tipos_asentamientos')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idtipo_administracion')->references('idtipo_administracion')->on('tipos_administracion')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idstatus_unidad')->references('idstatus_unidad')->on('status_unidades')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idtipo_establecimiento')->references('idtipo_establecimiento')->on('tipos_establecimiento')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idnivel_atencion')->references('idnivel_atencion')->on('niveles_atencion')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idprofesion')->references('idprofesion')->on('profesiones')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idmotivo_baja')->references('idmotivo_baja')->on('motivos_baja')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idstatus_propiedad')->references('idstatus_propiedad')->on('status_propiedades')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idsubtipologia')->references('idsubtipologia')->on('subtipologias')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idmarca_um')->references('idmarca_um')->on('marcas_um')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idtipologia_um')->references('idtipologia_um')->on('tipologias_um')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idprograma_um')->references('idprograma_um')->on('programas_um')->onDelete('RESTRICT')->onUpdate('CASCADE');
            $table->foreign('idtipo_um')->references('idtipo_um')->on('tipos_um')->onDelete('RESTRICT')->onUpdate('CASCADE');

        });

        DB::statement('ALTER TABLE unidades CHANGE idestado idestado INT(2) UNSIGNED ZEROFILL NOT NULL');
        DB::statement('ALTER TABLE unidades CHANGE idmunicipio idmunicipio INT(3) UNSIGNED ZEROFILL NOT NULL');
        DB::statement('ALTER TABLE unidades CHANGE idlocalidad idlocalidad INT(4) UNSIGNED ZEROFILL NOT NULL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tipos_administracion');
    }
};
