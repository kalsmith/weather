<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeatherService;
use App\Services\MeteoDataService; // El nuevo servicio
use App\Services\ImageService;
use App\Services\AstroService;
use App\Services\XService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PostWeatherUpdate extends Command
{
    protected $signature = 'weather:post {region=STGO} {--type=clima}';
    protected $description = 'Publica el clima, viento y humedad con un toque local.';

    protected $weather;
    protected $meteo; // Nuevo
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

        // Configuración de ciudades y zonas horarias
        $config = [
            'STGO'  => ['name' => 'Santiago', 'tz' => 'America/Santiago'],
            'ANTOF' => ['name' => 'Antofagasta', 'tz' => 'America/Santiago'],
            // 'PUQ'   => ['name' => 'Punta Arenas', 'tz' => 'America/Punta_Arenas'],
        ];

        $cityName = $config[$region]['name'] ?? $region;
        $timezone = $config[$region]['tz'] ?? 'America/Santiago';

        $now = Carbon::now($timezone);
        $horaActual = $now->format('H:i');

        $this->info("Iniciando proceso para: {$cityName} a las {$horaActual} ({$timezone})...");

        try {
            // 1. Obtener Temperatura Principal
            $temp = $this->weather->getTemperature($region);
            if (!$temp) {
                throw new \Exception("No se obtuvo temperatura para {$region}");
            }

            // 2. Obtener Extras (Viento y Humedad) desde el nuevo servicio
            // Usamos la URL que ya tienes mapeada en el WeatherService para esa región
            $extras = $this->meteo->getStationDetails($region);

            // 3. Obtener Datos Astronómicos y Luna
            $sunData = $this->astro->getSunData($region);
            $moonMessage = $this->weather->getMoonMessage($region);

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
                // TIPO: CLIMA (Reporte estándar enriquecido)
                $text = "🌡️ Temperatura en {$cityName} a las {$horaActual}: {$temp}°C\n";

                // Inyectamos Humedad y Viento si existen
                if (!empty($extras['humedad']) || !empty($extras['viento'])) {
                    $lineaExtras = "";
                    if ($extras['humedad']) $lineaExtras .= "💧 Humedad: {$extras['humedad']}% ";
                    if ($extras['viento'])  $lineaExtras .= "🌬️ Viento: {$extras['viento']} km/h";
                    $text .= trim($lineaExtras) . "\n";
                }

                $text .= "{$moonMessage}\n";
                $text .= "#Chile #Clima #{$cityName}";
            }

            // 4. Envío a X (Twitter)
            $xService = new XService($region);

            if ($type === 'clima') {
                $this->info("Enviando reporte de texto con extras...");
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
