<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeatherService;
use App\Services\MeteoDataService;
use App\Services\ImageService;
use App\Services\AstroService;
use App\Services\XService;
use App\Services\UVService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PostWeatherUpdate extends Command
{
    protected $signature = 'weather:post {region=STGO} {--type=clima}';
    protected $description = 'Publica clima, astronomía y reportes UV usando la API oficial de la DMC.';

    protected $weather;
    protected $meteo;
    protected $image;
    protected $astro;
    protected $uv;

    public function __construct(
        WeatherService $weather,
        MeteoDataService $meteo,
        ImageService $image,
        AstroService $astro,
        UVService $uv
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
            // 1. Datos base desde la DMC (MeteoDataService)
            $extras = $this->meteo->getStationDetails($region);

            // Fallback si la DMC falla
            $temp = $extras ? $extras['temperatura'] : $this->weather->getTemperature($region);

            if (!$temp && !is_numeric($temp)) {
                throw new \Exception("No se pudo obtener la temperatura para {$region}");
            }

            // 2. Datos complementarios
            $sunData = $this->astro->getSunData($region);
            $moonMessage = $this->weather->getMoonMessage($region);
            $moonDataRaw = $this->weather->getMoonData($region);
            $uvData = $this->uv->getUVData($region);

            $text = "";
            $imagePath = null;

            // 3. Lógica de Mensajes
            switch ($type) {
                case 'sunrise':
                    $text = "🌅 ¡Buenos días, {$cityName}!\n";
                    $text .= "Temp. actual: {$temp}°C\n";
                    if (isset($extras['minima_12h'])) {
                        $text .= "Mínima hoy: {$extras['minima_12h']}°C\n";
                    }
                    $text .= "Amanecer a las {$sunData['sunrise']}.\n";
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

                    if (isset($extras['maxima_12h'])) {
                        $text .= "Máxima hoy: {$extras['maxima_12h']}°C\n";
                    }

                    $text .= "Temp. actual: {$temp}°C\n";
                    $text .= "#Cenit #Astronomía #RadiacionUV #{$cityName}";
                    $imagePath = $this->image->generate($region, $temp, $moonDataRaw, $sunData, $type, $uvData);
                    break;

                case 'sunset':
                    $text = "🌇 ¡Buenas tardes, {$cityName}!\n";
                    $text .= "Temp. actual: {$temp}°C\n";
                    if (isset($extras['maxima_12h'])) {
                        $text .= "Máxima hoy: {$extras['maxima_12h']}°C\n";
                    }
                    $text .= "Ocaso a las {$sunData['sunset']}.\n\n";
                    $text .= "{$moonMessage}\n";
                    $text .= "#Atardecer #Chile #{$cityName}";
                    $imagePath = $this->image->generate($region, $temp, $moonDataRaw, $sunData, $type);
                    break;

                default: // CLIMA ESTÁNDAR
                    $text = "🌡️ Reporte de Clima: {$cityName}\n";
                    $text .= "Hora: {$horaActual} | Temp: {$temp}°C\n";

                    if ($extras) {
                        if ($extras['humedad']) $text .= "💧 Humedad: {$extras['humedad']}% ";
                        if ($extras['viento'])  $text .= "🌬️ Viento: {$extras['viento']} km/h";
                        $text .= "\n";
                        if (isset($extras['presion'])) {
                            $text .= "⏲️ Presión: {$extras['presion']} hPa\n";
                        }
                    }

                    if ($uvData) {
                        $text .= "☀️ UV: {$uvData['valor']} ({$uvData['riesgo']}) {$uvData['emoji']}\n";
                    }

                    $text .= "\n{$moonMessage}\n";
                    $text .= "#Chile #Clima #{$cityName}";
                    break;
            }

            // 4. Envío a X
            $xService = new XService($region);

            if ($imagePath) {
                $this->info("Enviando tweet con imagen para evento: {$type}");
                $xService->sendTweet($text, $imagePath);
            } else {
                $this->info("Enviando tweet de solo texto");
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
