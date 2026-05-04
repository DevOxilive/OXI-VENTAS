<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empleados', function (Blueprint $table) {
            $table->id();

            // DATOS PERSONALES
            $table->string('nombre');
            $table->string('apellido');
            $table->string('correo')->unique();
            $table->string('telefono', 20)->nullable();
            $table->text('domicilio')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->string('estado')->default('Activo');
            $table->string('foto')->nullable();

            // DATOS LABORALES
            $table->string('puesto');
            $table->string('departamento');
            $table->string('banco')->nullable();
            $table->string('numero_cuenta')->nullable();
            $table->string('grado_estudios')->nullable();
            $table->string('especialidad')->nullable();
            $table->string('tipo_contrato')->nullable();
            $table->string('antiguedad')->nullable();
            $table->string('nss')->nullable();
            $table->string('rfc')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empleados');
    }
};
