<?php

namespace App\Http\Controllers;

use App\Services\KpiService;
use Illuminate\Support\Str;
use Laravel\Octane\Facades\Octane;

class DashboardProController extends Controller
{
    public function __invoke(KpiService $svc)
    {
        $rid = Str::uuid()->toString();
        $t0  = microtime(true);

        $cached = $svc->getCached();

        if (!$cached || $svc->isStale($cached)) {
            // AQUI: concorrente = true (estamos em requisição HTTP)
            $calc0 = microtime(true);
            $fresh = $svc->compute(concurrent: true);
            Octane::table('kpis')->set($svc::CACHE_KEY, [
                'json' => json_encode($fresh),
                'ts'   => time(),
            ]);
            $cached = $fresh;
            $serverTiming = "compute;dur=".round((microtime(true)-$calc0)*1000,1);
        } else {
            $serverTiming = "hit;dur=".round((microtime(true)-$t0)*1000,1);
        }

        return response()->json($cached)
            ->header('Server-Timing', $serverTiming)
            ->header('X-Request-Id', $rid);
    }
}
