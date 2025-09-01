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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('status', [
                'Rascunho','Aguardando Aprovação','Aprovado','Rejeitado',
                'Aguardando Execução','Em Execução','Aguardando Informações',
                'Concluído','Cancelado','Fechado'
            ]);
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('concluded_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
