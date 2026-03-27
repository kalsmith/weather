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

        $cityNames = [
            'STGO' => 'Santiago',
            'ANTOF' => 'Antofagasta'
        ];

        $cityName = $cityNames[$region] ?? $region;
        $horaActual = Carbon::now('America/Santiago')->format('H:i');

        $this->info("Iniciando proceso para: {$cityName} a las {$horaActual}...");

        try {
            // 1. Obtener Temperatura
            $temp = $this->weather->getTemperature($region);
            if (!$temp) {
                throw new \Exception("No se obtuvo temperatura para {$region}");
            }

            // 2. Obtener Datos Astronómicos (para imágenes o cálculos extra)
            $sunData = $this->astro->getSunData($region);

            // 3. Obtener el mensaje inteligente de la Luna (Cordillera / Visibilidad)
            $moonMessage = $this->weather->getMoonMessage($region);

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
                $text .= "{$moonMessage}\n"; // Aquí inyectamos el mensaje inteligente
                $text .= "#Atardecer #Chile #{$cityName}";

            } else {
                // TIPO: CLIMA (Reporte estándar)
                $text = "🌡️ Temperatura en {$cityName} a las {$horaActual}: {$temp}°C\n\n";
                $text .= "{$moonMessage}\n"; // Aquí inyectamos el mensaje inteligente
                $text .= "#Chile #Clima #{$cityName}";
            }

            // 4. Envío a X (Twitter)
            $xService = new XService($region);

            if ($type === 'clima') {
                $this->info("Enviando reporte de texto local...");
                $xService->sendTweet($text);
            } else {
                $this->info("Generando imagen para evento especial...");
                // Para la imagen seguimos pasando el array crudo por si el ImageService lo necesita
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
