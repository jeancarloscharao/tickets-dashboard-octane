<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class DemoTicketsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            'Rascunho','Aguardando Aprovação','Aprovado','Rejeitado',
            'Aguardando Execução','Em Execução','Aguardando Informações',
            'Concluído','Cancelado','Fechado'
        ];

        for ($i = 1; $i <= 20; $i++) {
            $created = Carbon::now()->subDays(rand(0,30))->subMinutes(rand(0, 1440));
            $first   = rand(0,1) ? (clone $created)->addMinutes(rand(5,240)) : null;
            $done    = rand(0,1) ? (clone $created)->addHours(rand(1,72)) : null;

            DB::table('tickets')->insert([
                'title'            => "Chamado #{$i}",
                'status'           => $done ? 'Concluído' : $statuses[array_rand($statuses)],
                'first_response_at'=> $first,
                'concluded_at'     => $done,
                'user_id'          => null,
                'created_at'       => $created,
                'updated_at'       => $done ?: Carbon::now(),
            ]);
        }
    }
}
