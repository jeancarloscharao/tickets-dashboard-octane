<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Octane\Facades\Octane;

class KpiService
{
    const CACHE_KEY = 'dashboard';
    const STALE_AFTER_SECONDS = 60; // janela "quente"

    public function warm(bool $boot = false, bool $concurrent = false): void
    {
        $t0 = microtime(true);
        $data = $this->compute($concurrent); // <-- concorrente só se pedido
        Octane::table('kpis')->set(self::CACHE_KEY, [
            'json' => json_encode($data),
            'ts'   => time(),
        ]);
        Log::info('KPIs warm '.($boot ? '(boot)' : ''), ['ms' => round((microtime(true)-$t0)*1000,1)]);
    }

    public function getCached(): ?array
    {
        $row = Octane::table('kpis')->get(self::CACHE_KEY);
        return $row ? json_decode($row['json'], true) : null;
    }

    public function isStale(?array $row): bool
    {
        $tbl = Octane::table('kpis')->get(self::CACHE_KEY);
        if (! $tbl) return true;
        return (time() - (int) $tbl['ts']) > self::STALE_AFTER_SECONDS;
    }

    /**
     * Computa KPIs; se $concurrent=false, roda SEQUENCIAL (seguro para boot/tick).
     * Se $concurrent=true, usa Octane::concurrently (apenas em requisições HTTP).
     */
    public function compute(bool $concurrent = true): array
    {
        $now    = now();
        $from7  = $now->copy()->subDays(7);
        $from30 = $now->copy()->subDays(30);

        $abertosSet = [
            'Rascunho','Aguardando Aprovação','Aguardando Execução',
            'Em Execução','Aguardando Informações',
        ];

        // Definimos as "tarefas" como closures independentes
        $tasks = [
            'counts' => function () use ($abertosSet) {
                return [
                    'total'      => DB::table('tickets')->count(),
                    'abertos'    => DB::table('tickets')->whereIn('status', $abertosSet)->count(),
                    'concluidos' => DB::table('tickets')->where('status','Concluído')->count(),
                ];
            },

            'countsByStatus' => function () use ($abertosSet) {
                return collect($abertosSet)
                    ->mapWithKeys(fn ($st) => [$st => DB::table('tickets')->where('status',$st)->count()])
                    ->all();
            },

            'sla7' => function () use ($from7) {
                return DB::table('tickets')
                    ->where('created_at','>=',$from7)
                    ->selectRaw("
                        AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_response,
                        AVG(TIMESTAMPDIFF(MINUTE, created_at, concluded_at))      as avg_resolution
                    ")->first();
            },

            'sla30' => function () use ($from30) {
                return DB::table('tickets')
                    ->where('created_at','>=',$from30)
                    ->selectRaw("
                        AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_response,
                        AVG(TIMESTAMPDIFF(MINUTE, created_at, concluded_at))      as avg_resolution
                    ")->first();
            },

            'recent' => function () {
                return DB::table('tickets')
                    ->latest('created_at')->limit(10)
                    ->get(['id','title','status','created_at']);
            },

            'usd' => function () {
                return rescue(function () {
                    $bid = Http::timeout(3)
                        ->get('https://economia.awesomeapi.com.br/json/last/USD-BRL')
                        ->json('USDBRL.bid');
                    return $bid ? (float)$bid : null;
                }, null, report: false);
            },

            // Soma de 30 dias em 6 janelas — serve para "forçar" I/O paralelo
            'sliceSum' => function () use ($now) {
                $slices = [];
                for ($i=0; $i<6; $i++) {
                    $start = $now->copy()->subDays(30)->addDays($i*5);
                    $end   = $start->copy()->addDays(5);
                    $slices[] = [$start, $end];
                }

                $calc = function ($win) {
                    return DB::table('tickets')
                        ->whereBetween('created_at', [$win[0], $win[1]])
                        ->count();
                };

                // sequencial aqui; quando estivermos em modo concorrente,
                // as fatias já correm em paralelo com as outras tasks
                $total = 0;
                foreach ($slices as $win) { $total += $calc($win); }
                return $total;
            },
        ];

        if (! $concurrent) {
            // MODO SEQUENCIAL (seguro para boot/tick)
            $results = [];
            foreach ($tasks as $k => $fn) { $results[$k] = $fn(); }
        } else {
            // MODO CONCORRENTE (apenas em requisição HTTP)
            $ordered = array_values($tasks);
            [$counts, $countsByStatus, $sla7, $sla30, $recent, $usd, $sliceSum] =
                Octane::concurrently($ordered);

            $results = [
                'counts'          => $counts,
                'countsByStatus'  => $countsByStatus,
                'sla7'            => $sla7,
                'sla30'           => $sla30,
                'recent'          => $recent,
                'usd'             => $usd,
                'sliceSum'        => $sliceSum,
            ];
        }

        // Normaliza retorno
        $counts          = $results['counts']          ?? null;
        $countsByStatus  = $results['countsByStatus']  ?? null;
        $sla7            = $results['sla7']            ?? null;
        $sla30           = $results['sla30']           ?? null;
        $recent          = $results['recent']          ?? null;
        $usd             = $results['usd']             ?? null;
        $sliceSum        = $results['sliceSum']        ?? null;

        return [
            'counts'          => $counts,
            'counts_by_status'=> $countsByStatus,
            'sla' => [
                '7d'  => [
                    'avg_response_min'   => $sla7?->avg_response ? round($sla7->avg_response, 1) : null,
                    'avg_resolution_min' => $sla7?->avg_resolution ? round($sla7->avg_resolution, 1) : null,
                ],
                '30d' => [
                    'avg_response_min'   => $sla30?->avg_response ? round($sla30->avg_response, 1) : null,
                    'avg_resolution_min' => $sla30?->avg_resolution ? round($sla30->avg_resolution, 1) : null,
                ],
            ],
            'last10'  => $recent,
            'usd_brl' => $usd,
            'sanity'  => ['last30_split_count' => $sliceSum],
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
