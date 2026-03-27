<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
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

        $astro = new AstroService();

        // 1. Reportes de clima estándar cada 4 horas
        $schedule->command('weather:post STGO')->everyFourHours();
        $schedule->command('weather:post ANTOF')->everyFourHours();

        // ==============================
        // SANTIAGO (STGO)
        // ==============================

        // Reporte 30 min antes del Amanecer
        $schedule->command('weather:post STGO --type=sunrise')
            ->everyMinute()
            ->when(fn() => $astro->isThirtyMinsBeforeSunrise('STGO'));

        // Reporte 30 min antes del Ocaso
        $schedule->command('weather:post STGO --type=sunset')
            ->everyMinute()
            ->when(fn() => $astro->isThirtyMinsBeforeSunset('STGO'));


        // ==============================
        // ANTOFAGASTA (ANTOF)
        // ==============================

        // Reporte 30 min antes del Amanecer
        $schedule->command('weather:post ANTOF --type=sunrise')
            ->everyMinute()
            ->when(fn() => $astro->isThirtyMinsBeforeSunrise('ANTOF'));

        // Reporte 30 min antes del Ocaso
        $schedule->command('weather:post ANTOF --type=sunset')
            ->everyMinute()
            ->when(fn() => $astro->isThirtyMinsBeforeSunset('ANTOF'));

    })->create();
