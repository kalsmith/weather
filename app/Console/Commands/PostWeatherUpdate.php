<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeatherService;
use App\Services\ImageService;
use App\Services\AstroService;
use App\Services\XService;
use Illuminate\Support\Facades\Log;

class PostWeatherUpdate extends Command
{
    /**
     * Firma que acepta región y tipo de reporte.
     * Tipos válidos: clima, sunrise, sunset
     */
    protected $signature = 'weather:post {region=STGO} {--type=clima}';

    protected $description = 'Obtiene clima, datos astronómicos y publica en X por región (con imagen solo en eventos especiales).';

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
        $type = $this->option('type'); // clima, sunrise o sunset

        $this->info("Iniciando proceso para: {$region} (Tipo: {$type})...");

        try {
            // 1. Obtener Temperatura
            $temp = $this->weather->getTemperature($region);
            if (!$temp) {
                $this->error("No se pudo obtener la temperatura. Abortando.");
                return 1;
            }

            // 2. Obtener Datos Astronómicos
            $sunData = $this->astro->getSunData($region);

            // 3. Obtener Datos Lunares
            $moonData = $this->weather->getMoonData($region);

            // 4. Personalizar el texto según el evento
            $text = "";
            if ($type === 'sunrise') {
                $text = "🌅 ¡Buenos días, {$region}!\n";
                $text .= "Faltan 30 min para el amanecer ({$sunData['sunrise']}).\n";
                $text .= "Temp actual: {$temp}°C\n";
                $text .= "#Amanecer #Chile #{$region}";

            } elseif ($type === 'sunset') {
                $text = "🌇 ¡Buenas tardes, {$region}!\n";
                $text .= "Faltan 30 min para el ocaso ({$sunData['sunset']}).\n";

                if ($moonData) {
                    $emoji = $moonData['fase_emoji'] ?? '🌙';
                    $fase = $moonData['fase_nombre'] ?? 'Luna';
                    $ilum = round($moonData['iluminacion_pct'] ?? 0);
                    $text .= "{$emoji} Esta noche: {$fase} ({$ilum}% iluminada).\n";
                }

                $text .= "Temp actual: {$temp}°C\n";
                $text .= "#Atardecer #Chile #{$region}";

            } else {
                // TIPO: CLIMA (Solo Texto)
                $text = "🌡️ Reporte Actualizado ({$region})\n";
                $text .= "Temperatura: {$temp}°C\n";

                if ($moonData) {
                    $emoji = $moonData['fase_emoji'] ?? '🌙';
                    $text .= "Luna: {$emoji} " . round($moonData['iluminacion_pct'] ?? 0) . "% iluminada.\n";
                }
                $text .= "#Chile #Clima #{$region}";
            }

            // 5. Lógica de Publicación Dinámica
            $xService = new XService($region);

            if ($type === 'clima') {
                // PUBLICAR SOLO TEXTO
                $this->info("Publicando reporte de texto (sin imagen)...");
                $xService->sendTweet($text); // Asegúrate que sendTweet acepte un solo parámetro
            } else {
                // GENERAR IMAGEN Y PUBLICAR (Sunrise / Sunset)
                $this->info("Generando imagen con estilo: {$type}...");
                $imagePath = $this->image->generate($region, $temp, $moonData, $sunData, $type);

                if (!file_exists($imagePath)) {
                    throw new \Exception("Fallo al generar la imagen en {$imagePath}");
                }

                $this->info("Publicando con media...");
                $xService->sendTweet($text, $imagePath);
            }

            $this->info("¡Éxito! Tweet publicado como {$type} en la cuenta de {$region}.");

        } catch (\Exception $e) {
            $this->error("Error crítico: " . $e->getMessage());
            Log::error("Fallo en PostWeatherUpdate ({$region}): " . $e->getMessage());
        }

        return 0;
    }
}
