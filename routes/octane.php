<?php

use App\Services\KpiService;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Facades\Octane;

Octane::listen(WorkerStarting::class, function () {
    // no boot: sequencial
    app(KpiService::class)->warm(boot: true, concurrent: false);
});

Octane::tick('refresh-kpis', function () {
    // no tick: sequencial
    app(KpiService::class)->warm(concurrent: false);
})->seconds(30);
