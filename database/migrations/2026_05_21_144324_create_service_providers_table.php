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
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('cpf_cnpj')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('specialty')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('cpf_cnpj');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_providers');
    }
};
