<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeatherService;
use App\Services\MeteoDataService;
use App\Services\ImageService;
use App\Services\AstroService;
use App\Services\XService;
use App\Services\UVService; // Nuevo
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PostWeatherUpdate extends Command
{
    protected $signature = 'weather:post {region=STGO} {--type=clima}';
    protected $description = 'Publica clima, astronomía y reportes UV con imágenes en hitos.';

    protected $weather;
    protected $meteo;
    protected $image;
    protected $astro;
    protected $uv; // Nuevo

    public function __construct(
        WeatherService $weather,
        MeteoDataService $meteo,
        ImageService $image,
        AstroService $astro,
        UVService $uv // Inyectamos
    ) {
        parent::__construct();
        $this->weather = $weather;
        $this->meteo = $meteo;
        $this->image = $image;
        $this->astro = $astro;
        $this->uv = $uv;
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

            // 2. Obtener datos UV (Siempre lo intentamos por si el reporte estándar lo necesita)
            $uvData = $this->uv->getUVData($region);

            $text = "";
            $imagePath = null;

            // 3. Lógica de Mensaje y Decisión de Imagen
            switch ($type) {
                case 'sunrise':
                    $text = "🌅 ¡Buenos días, {$cityName}!\n";
                    $text .= "Temperatura: {$temp}°C\n";
                    $text .= "Faltan 30 min para el amanecer ({$sunData['sunrise']}).\n";
                    $text .= "#Amanecer #Chile #{$cityName}";
                    $imagePath = $this->image->generate($region, $temp, $moonDataRaw, $sunData, $type);
                    break;

                case 'cenit':
                    $text = "☀️ ¡Cenit en {$cityName}!\n";
                    $text .= "El sol está en su punto máximo ({$sunData['transit']}).\n";

                    if ($uvData) {
                        $text .= "Radiación UV: {$uvData['valor']} ({$uvData['riesgo']}) {$uvData['emoji']}\n";
                        $text .= "¡Usa protección solar! 🧴🕶️\n";
                    }

                    $text .= "Temperatura: {$temp}°C\n";
                    $text .= "#Cenit #Astronomía #RadiacionUV #{$cityName}";

                    // Aquí podrías usar un generador específico para UV o el general
                    // Por ahora usamos el general que ya conoce el evento 'cenit'
                    $imagePath = $this->image->generate($region, $temp, $moonDataRaw, $sunData, $type, $uvData);
                    break;

                case 'sunset':
                    $text = "🌇 ¡Buenas tardes, {$cityName}!\n";
                    $text .= "Temperatura: {$temp}°C\n";
                    $text .= "Faltan 30 min para el ocaso ({$sunData['sunset']}).\n";
                    $text .= "{$moonMessage}\n";
                    $text .= "#Atardecer #Chile #{$cityName}";
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

                    // Añadimos UV al reporte estándar si hay datos
                    if ($uvData && $uvData['valor'] > 0) {
                        $text .= "☀️ UV: {$uvData['valor']} ({$uvData['riesgo']}) {$uvData['emoji']}\n";
                    }

                    $text .= "{$moonMessage}\n";
                    $text .= "#Chile #Clima #{$cityName}";
                    break;
            }

            // 4. Envío a X (Twitter)
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
