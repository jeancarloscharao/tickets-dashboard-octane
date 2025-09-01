<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoTicketsBigSeeder extends Seeder
{
    public function run(): void
    {
        $total     = 100_000;   // total de tickets
        $batchSize = 5_000;     // tamanho do lote (20 lotes)
        $statuses  = [
            'Rascunho','Aguardando Aprovação','Aprovado','Rejeitado',
            'Aguardando Execução','Em Execução','Aguardando Informações',
            'Concluído','Cancelado','Fechado'
        ];

        DB::disableQueryLog();  // economiza memória
        $now = CarbonImmutable::now();
        $inserted = 0;

        while ($inserted < $total) {
            $batch = [];
            $limit = min($batchSize, $total - $inserted);

            for ($i = 0; $i < $limit; $i++) {
                // Datas aleatórias dos últimos 180 dias
                $created = $now
                    ->subDays(random_int(0, 180))
                    ->subMinutes(random_int(0, 1440));

                $hasFirst = (bool) random_int(0, 1);
                $first    = $hasFirst ? $created->addMinutes(random_int(5, 240)) : null;

                $hasDone  = (bool) random_int(0, 1);
                $done     = $hasDone ? $created->addHours(random_int(1, 72)) : null;

                $status   = $done ? 'Concluído' : $statuses[array_rand($statuses)];

                $batch[] = [
                    'title'             => 'Chamado #' . ($inserted + $i + 1),
                    'status'            => $status,
                    'first_response_at' => $first?->toDateTimeString(),
                    'concluded_at'      => $done?->toDateTimeString(),
                    'user_id'           => null,
                    'created_at'        => $created->toDateTimeString(),
                    'updated_at'        => ($done ?: $created)->toDateTimeString(),
                ];
            }

            DB::table('tickets')->insert($batch);
            $inserted += $limit;

            if (isset($this->command)) {
                $this->command->info("Inseridos: {$inserted}/{$total}");
            }
        }
    }
}
