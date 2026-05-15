<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone', 20)->nullable();
            $table->string('street')->nullable();
            $table->string('external_number')->nullable();
            $table->string('internal_number')->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('municipality')->nullable();
            $table->string('address_state')->nullable();
            $table->text('maps_url')->nullable();
            $table->date('start_date')->nullable();
            $table->string('employment_status')->default('Activo');
            $table->string('photo')->nullable();

            $table->string('position');
            $table->string('department');
            $table->string('bank')->nullable();
            $table->string('account_number')->nullable();
            $table->string('education_level')->nullable();
            $table->string('specialty')->nullable();
            $table->string('contract_type')->nullable();
            $table->string('seniority')->nullable();
            $table->string('nss')->nullable();
            $table->string('rfc')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
