<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeatherService;
use App\Services\MeteoDataService;
use App\Services\ImageService;
use App\Services\AstroService;
use App\Services\XService;
use App\Services\UVService;
use App\Services\UVImageService; // Nuevo Servicio
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PostWeatherUpdate extends Command
{
    protected $signature = 'weather:post {region=STGO} {--type=clima}';
    protected $description = 'Publica clima, astronomía y reportes UV con gráficos dinámicos.';

    protected $weather;
    protected $meteo;
    protected $image;
    protected $astro;
    protected $uv;
    protected $uvImage;

    public function __construct(
        WeatherService $weather,
        MeteoDataService $meteo,
        ImageService $image,
        AstroService $astro,
        UVService $uv,
        UVImageService $uvImage // Inyectado
    ) {
        parent::__construct();
        $this->weather = $weather;
        $this->meteo = $meteo;
        $this->image = $image;
        $this->astro = $astro;
        $this->uv = $uv;
        $this->uvImage = $uvImage;
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
            // 1. Obtención de datos
            $extras = $this->meteo->getStationDetails($region);

            // Fallback de temperatura
            $temp = $extras ? $extras['temperatura'] : $this->weather->getTemperature($region);

            if (!is_numeric($temp)) {
                throw new \Exception("No se pudo obtener la temperatura para {$region}");
            }

            $sunData = $this->astro->getSunData($region);
            $moonMessage = $this->weather->getMoonMessage($region);
            $moonDataRaw = $this->weather->getMoonData($region);
            $uvData = $this->uv->getUVData($region);

            $text = "";
            $imagePath = null;

            // 2. Lógica de Mensajes y Generación de Imágenes
            switch ($type) {
// ... dentro del switch ($type) en handle() ...

                case 'sunrise':
                    $text = "🌅 ¡Buenos días, {$cityName}!\n";
                    $text .= "🌡️ Temp. actual: {$temp}°C\n";

                    // 1. Intentamos obtener la máxima proyectada desde el nuevo servicio de riesgo
                    $datosRiesgo = $this->meteo->getFireRiskData($region); // Asumiendo que implementas este método
                    $maximaHoy = $datosRiesgo['temperaturaMaximaHoy'] ?? null;
                    $vientoProyectado = $datosRiesgo['intensidadVientoMaximoHoy'] ?? null;

                    if ($maximaHoy) {
                        $text .= "🔺 Máxima esperada: {$maximaHoy}°C\n";
                    } elseif (isset($extras['maxima_12h'])) {
                        // Fallback al dato histórico si no hay proyectado
                        $text .= "🔺 Máxima reciente: {$extras['maxima_12h']}°C\n";
                    }

                    if ($vientoProyectado && $vientoProyectado > 30) {
                        $text .= "🌬️ Ojo: Ráfagas de hasta {$vientoProyectado} km/h\n";
                    }

                    $text .= "☀️ Amanecer: {$sunData['sunrise']} hrs\n\n";
                    $text .= "¡Que tengan un excelente día! ☕\n";
                    $text .= "#Amanecer #Chile #{$cityName} #Clima";

                    // Generar la imagen del amanecer (la que ya tenías)
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
                        if (isset($extras['humedad'])) $text .= "💧 Humedad: {$extras['humedad']}% ";
                        if (isset($extras['viento']))  $text .= "🌬️ Viento: {$extras['viento']} km/h\n";
                        if (isset($extras['presion'])) $text .= "⏲️ Presión: {$extras['presion']} hPa\n";
                    }

                    if ($uvData) {
                        $text .= "☀️ UV: {$uvData['valor']} ({$uvData['riesgo']}) {$uvData['emoji']}\n";

                        // Si tenemos historial, generamos el gráfico UV para adjuntar
                        if (!empty($uvData['historico'])) {
                            $this->info("Generando gráfico de historial UV...");
                            $imagePath = $this->uvImage->generate($uvData['historico'], $region);
                        }
                    }

                    $text .= "\n{$moonMessage}\n";
                    $text .= "#Chile #Clima #{$cityName}";
                    break;
            }

            // 3. Envío a X
            $xService = new XService($region);

            if ($imagePath && file_exists($imagePath)) {
                $this->info("Enviando tweet con imagen: " . basename($imagePath));
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
