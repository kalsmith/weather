<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeatherService;
use App\Services\UVService; // Inyectamos el nuevo servicio
use App\Services\ImageService;
use App\Services\AstroService;
use App\Services\XService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PostWeatherUpdate extends Command
{
    protected $signature = 'weather:post {region=STGO} {--type=clima}';
    protected $description = 'Publica el clima y radiación UV con un toque local.';

    protected $weather;
    protected $uv; // Nuevo
    protected $image;
    protected $astro;

    public function __construct(WeatherService $weather, UVService $uv, ImageService $image, AstroService $astro)
    {
        parent::__construct();
        $this->weather = $weather;
        $this->uv = $uv;
        $this->image = $image;
        $this->astro = $astro;
    }

    public function handle()
    {
        $region = strtoupper($this->argument('region'));
        $type = $this->option('type');

        $cityNames = [
            'STGO' => 'Santiago',
            'ANTOF' => 'Antofagasta'
        ];

        $cityName = $cityNames[$region] ?? $region;
        $now = Carbon::now('America/Santiago');
        $horaActual = $now->format('H:i');
        $horaInt = $now->hour;

        $this->info("Iniciando proceso para: {$cityName} a las {$horaActual}...");

        try {
            // 1. Obtener Temperatura
            $temp = $this->weather->getTemperature($region);
            if (!$temp) {
                throw new \Exception("No se obtuvo temperatura para {$region}");
            }

            // 2. Obtener Datos Astronómicos y Luna
            $sunData = $this->astro->getSunData($region);
            $moonMessage = $this->weather->getMoonMessage($region);

            // 3. Lógica UV (Solo entre 10:00 y 18:00)
            $uvMessage = "";
            if ($horaInt >= 10 && $horaInt <= 18) {
                $uvData = $this->uv->getUVData($region);
                if ($uvData) {
                    $uvMessage = "☀️ UV: {$uvData['valor']} {$uvData['emoji']} ({$uvData['riesgo']})\n";
                }
            }

            $text = "";

            if ($type === 'sunrise') {
                $text = "🌅 ¡Buenos días, {$cityName}!\n";
                $text .= "Temperatura actual: {$temp}°C\n";
                $text .= "Faltan 30 min para el amanecer ({$sunData['sunrise']}).\n";
                $text .= "#Amanecer #Chile #{$cityName}";

            } elseif ($type === 'sunset') {
                $text = "🌇 ¡Buenas tardes, {$cityName}!\n";
                $text .= "Temperatura actual: {$temp}°C\n";
                $text .= "Faltan 30 min para el ocaso ({$sunData['sunset']}).\n";
                $text .= "{$moonMessage}\n";
                $text .= "#Atardecer #Chile #{$cityName}";

            } else {
                // TIPO: CLIMA (Reporte estándar)
                $text = "🌡️ Temperatura en {$cityName} a las {$horaActual}: {$temp}°C\n";

                // Inyectamos el UV si estamos en el horario
                if ($uvMessage) {
                    $text .= $uvMessage;
                }

                $text .= "\n{$moonMessage}\n";
                $text .= "#Chile #Clima #{$cityName}";
            }

            // 4. Envío a X (Twitter)
            $xService = new XService($region);

            if ($type === 'clima') {
                $this->info("Enviando reporte de texto local...");
                $xService->sendTweet($text);
            } else {
                $this->info("Generando imagen para evento especial...");
                $moonDataRaw = $this->weather->getMoonData($region);
                $imagePath = $this->image->generate($region, $temp, $moonDataRaw, $sunData, $type);
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
