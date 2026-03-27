<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule; // Importante
use App\Services\AstroService;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->withSchedule(function (Schedule $schedule): void {

        // 1. Reporte de clima cada 4 horas (Standard)
        $schedule->command('weather:post STGO')->everyFourHours();
        $schedule->command('weather:post ANTOF')->everyFourHours();

        // 2. Reporte especial 30 min antes del amanecer (Santiago)
        $schedule->command('weather:post STGO --type=sunrise')
            ->everyMinute()
            ->when(function () {
                return (new AstroService())->isThirtyMinsBeforeSunrise('STGO');
            });

        // Reporte especial 30 min antes del OCASO
        $schedule->command('weather:post STGO --type=sunset')
            ->everyMinute()
            ->when(function () {
                $data = (new AstroService())->getSunData('STGO');
                $thirtyMinsBefore = $data['sunset_raw'] - (30 * 60);
                return date('H:i') === date('H:i', $thirtyMinsBefore);
            });

        // 3. Reporte especial 30 min antes del amanecer (Antofagasta)
        $schedule->command('weather:post ANTOF --type=sunrise')
            ->everyMinute()
            ->when(function () {
                return (new AstroService())->isThirtyMinsBeforeSunrise('ANTOF');
            });

    })->create();
