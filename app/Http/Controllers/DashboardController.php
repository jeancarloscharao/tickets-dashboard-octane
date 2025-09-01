<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Facades\Octane;

class DashboardController extends Controller
{
    public function index()
    {
        // Tudo roda em paralelo:
        [$counts, $sla, $usd, $recent] = Octane::concurrently([
            // 1) Contagens úteis (ex.: chamados/tickets)
            fn () => [
                'total'      => DB::table('tickets')->count(),
                'abertos'    => DB::table('tickets')->whereIn('status', [
                    'Rascunho','Aguardando Aprovação','Aguardando Execução',
                    'Em Execução','Aguardando Informações'
                ])->count(),
                'concluidos' => DB::table('tickets')->where('status','Concluído')->count(),
            ],

            // 2) KPI de SLA (exemplo simples: médias em minutos)
            fn () => DB::table('tickets')
                    ->selectRaw("
                        AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_response,
                        AVG(TIMESTAMPDIFF(MINUTE, created_at, concluded_at))      as avg_resolution
                    ")
                    ->first(),

            // 3) Chamada HTTP externa (ex.: cotação USD-BRL) com fallback
            fn () => rescue(function () {
                    $bid = Http::timeout(3)
                        ->get('https://economia.awesomeapi.com.br/json/last/USD-BRL')
                        ->json('USDBRL.bid');
                    return $bid ? (float) $bid : null;
                }, null, report: false),

            // 4) Últimos registros (pra lista do dashboard)
            fn () => DB::table('tickets')
                    ->latest('created_at')
                    ->limit(5)
                    ->get(['id','title','status','created_at']),
        ]);

        // Exemplo: retornar JSON ou passar pra view
        return response()->json([
            'counts' => $counts,
            'sla'    => [
                'avg_response_min'  => $sla?->avg_response ? round($sla->avg_response, 1) : null,
                'avg_resolution_min'=> $sla?->avg_resolution ? round($sla->avg_resolution, 1) : null,
            ],
            'usd_brl' => $usd,   // pode ser null se a API falhar
            'recent'  => $recent,
        ]);
    }
}
