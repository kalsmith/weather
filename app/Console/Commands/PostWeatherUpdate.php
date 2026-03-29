<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeatherService;
use App\Services\MeteoDataService;
use App\Services\ImageService;
use App\Services\AstroService;
use App\Services\XService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PostWeatherUpdate extends Command
{
    protected $signature = 'weather:post {region=STGO} {--type=clima}';
    protected $description = 'Publica el clima y eventos astronómicos con gráficos de 24h solo en hitos.';

    protected $weather;
    protected $meteo;
    protected $image;
    protected $astro;

    public function __construct(
        WeatherService $weather,
        MeteoDataService $meteo,
        ImageService $image,
        AstroService $astro
    ) {
        parent::__construct();
        $this->weather = $weather;
        $this->meteo = $meteo;
        $this->image = $image;
        $this->astro = $astro;
    }

    public function handle()
    {
        $region = strtoupper($this->argument('region'));
        $type = $this->option('type');

        $config = [
            'STGO'  => ['name' => 'Santiago', 'tz' => 'America/Santiago'],
            'ANTOF' => ['name' => 'Antofagasta', 'tz' => 'America/Santiago'],
        ];

        $cityName = $config[$region]['name'] ?? $region;
        $timezone = $config[$region]['tz'] ?? 'America/Santiago';

        $now = Carbon::now($timezone);
        $horaActual = $now->format('H:i');

        try {
            // 1. Datos base
            $temp = $this->weather->getTemperature($region);
            if (!$temp) throw new \Exception("No se obtuvo temperatura para {$region}");

            $extras = $this->meteo->getStationDetails($region);
            $sunData = $this->astro->getSunData($region);
            $moonMessage = $this->weather->getMoonMessage($region);
            $moonDataRaw = $this->weather->getMoonData($region);

            $text = "";
            $imagePath = null; // Por defecto no hay imagen

            // 2. Lógica de Mensaje y Decisión de Imagen
            switch ($type) {
                case 'sunrise':
                    $text = "🌅 ¡Buenos días, {$cityName}!\n";
                    $text .= "Temperatura: {$temp}°C\n";
                    $text .= "Faltan 30 min para el amanecer ({$sunData['sunrise']}).\n";
                    $text .= "#Amanecer #Chile #{$cityName}";
                    // Generar imagen para el hito
                    $imagePath = $this->image->generate($region, $temp, $moonDataRaw, $sunData, $type);
                    break;

                case 'cenit':
                    $text = "☀️ ¡Cenit en {$cityName}!\n";
                    $text .= "El sol está en su punto máximo ({$sunData['transit']}).\n";
                    $text .= "Temperatura actual: {$temp}°C\n";
                    $text .= "#Cenit #Astronomía #{$cityName}";
                    // Generar imagen para el hito
                    $imagePath = $this->image->generate($region, $temp, $moonDataRaw, $sunData, $type);
                    break;

                case 'sunset':
                    $text = "🌇 ¡Buenas tardes, {$cityName}!\n";
                    $text .= "Temperatura: {$temp}°C\n";
                    $text .= "Faltan 30 min para el ocaso ({$sunData['sunset']}).\n";
                    $text .= "{$moonMessage}\n";
                    $text .= "#Atardecer #Chile #{$cityName}";
                    // Generar imagen para el hito
                    $imagePath = $this->image->generate($region, $temp, $moonDataRaw, $sunData, $type);
                    break;

                default: // CLIMA ESTÁNDAR (Cada 4 horas)
                    $text = "🌡️ Reporte de Clima: {$cityName}\n";
                    $text .= "Hora: {$horaActual} | Temp: {$temp}°C\n";

                    if (!empty($extras['humedad']) || !empty($extras['viento'])) {
                        if ($extras['humedad']) $text .= "💧 Humedad: {$extras['humedad']}% ";
                        if ($extras['viento'])  $text .= "🌬️ Viento: {$extras['viento']} km/h";
                        $text .= "\n";
                    }
                    $text .= "{$moonMessage}\n";
                    $text .= "#Chile #Clima #{$cityName}";
                    // NO se genera imagen aquí
                    break;
            }

            // 3. Envío a X (Twitter)
            $xService = new XService($region);

            if ($imagePath) {
                $this->info("Enviando tweet con imagen para evento: {$type}");
                $xService->sendTweet($text, $imagePath);
            } else {
                $this->info("Enviando tweet de solo texto para reporte estándar");
                $xService->sendTweet($text);
            }

            $this->info("¡Éxito! Publicado en cuenta de {$cityName}.");

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            Log::error("Fallo PostWeather ({$region}): " . $e->getMessage());
        }

        return 0;
    }
}
