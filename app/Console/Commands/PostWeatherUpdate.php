<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeatherService;
use App\Services\ImageService;
use App\Services\AstroService;
use App\Services\XService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PostWeatherUpdate extends Command
{
    protected $signature = 'weather:post {region=STGO} {--type=clima}';
    protected $description = 'Publica el clima con un toque local y humano.';

    protected $weather;
    protected $image;
    protected $astro;

    public function __construct(WeatherService $weather, ImageService $image, AstroService $astro)
    {
        parent::__construct();
        $this->weather = $weather;
        $this->image = $image;
        $this->astro = $astro;
    }

    public function handle()
    {
        $region = strtoupper($this->argument('region'));
        $type = $this->option('type');

        // Mapeo para el toque local
        $cityNames = [
            'STGO' => 'Santiago',
            'ANTOF' => 'Antofagasta'
        ];
        $cityName = $cityNames[$region] ?? $region;
        $horaActual = Carbon::now('America/Santiago')->format('H:i');

        $this->info("Iniciando proceso para: {$cityName} a las {$horaActual}...");

        try {
            $temp = $this->weather->getTemperature($region);
            if (!$temp) {
                throw new \Exception("No se obtuvo temperatura para {$region}");
            }

            $sunData = $this->astro->getSunData($region);
            $moonData = $this->weather->getMoonData($region);

            $text = "";

            if ($type === 'sunrise') {
                $text = "🌅 ¡Buenos días, {$cityName}!\n";
                $text .= "Temperatura actual a las {$horaActual}: {$temp}°C\n";
                $text .= "Faltan 30 min para el amanecer ({$sunData['sunrise']}).\n";
                $text .= "#Amanecer #Chile #{$cityName}";

            } elseif ($type === 'sunset') {
                $text = "🌇 ¡Buenas tardes, {$cityName}!\n";
                $text .= "Temperatura actual a las {$horaActual}: {$temp}°C\n";
                $text .= "Faltan 30 min para el ocaso ({$sunData['sunset']}).\n";

                if ($moonData) {
                    $emoji = $moonData['fase_emoji'] ?? '🌙';
                    $fase = $moonData['fase_nombre'] ?? 'Luna';
                    $text .= "{$emoji} Esta noche: {$fase} (" . round($moonData['iluminacion_pct']) . "%).\n";
                }
                $text .= "#Atardecer #Chile #{$cityName}";

            } else {
                // TIPO: CLIMA (El formato que te dio éxito)
                $text = "🌡️ Temperatura actual en {$cityName} a las {$horaActual}: {$temp}°C\n\n";

                if ($moonData) {
                    $emoji = $moonData['fase_emoji'] ?? '🌙';
                    $text .= "Luna: {$emoji} " . round($moonData['iluminacion_pct']) . "% iluminada.\n";
                }
                $text .= "#Chile #Clima #{$cityName}";
            }

            $xService = new XService($region);

            if ($type === 'clima') {
                $this->info("Enviando reporte de texto local...");
                $xService->sendTweet($text);
            } else {
                $this->info("Generando imagen para evento especial...");
                $imagePath = $this->image->generate($region, $temp, $moonData, $sunData, $type);
                $xService->sendTweet($text, $imagePath);
            }

            $this->info("¡Éxito! Publicado en cuenta de {$cityName}.");

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Fallo PostWeather ({$region}): " . $e->getMessage());
        }

        return 0;
    }
}
