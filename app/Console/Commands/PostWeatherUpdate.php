<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WeatherService;
use App\Services\ImageService;
use App\Services\XService;
use Illuminate\Support\Facades\Log;

class PostWeatherUpdate extends Command
{
    /**
     * El nombre y la firma del comando.
     * Uso: php artisan weather:post {region}
     */
    protected $signature = 'weather:post {region=STGO}';

    protected $description = 'Obtiene clima, datos de la luna y publica en X por región.';

    protected $weather;
    protected $image;

    public function __construct(WeatherService $weather, ImageService $image)
    {
        parent::__construct();
        $this->weather = $weather;
        $this->image = $image;
    }

    public function handle()
    {
        $region = strtoupper($this->argument('region'));
        $this->info("Iniciando proceso para: {$region}...");

        try {
            // 1. Obtener Temperatura (Scraping MeteoChile)
            $temp = $this->weather->getTemperature($region);
            if (!$temp) {
                $this->error("No se pudo obtener la temperatura. Abortando.");
                return 1;
            }
            $this->info("Temperatura obtenida: {$temp}°C");

            // 2. Obtener Datos Lunares (API Python Hosting)
            $moonData = $this->weather->getMoonData($region);
            $this->info($moonData ? "Datos lunares listos." : "Sin datos lunares (continuando solo con clima).");

            // 3. Generar la Imagen
            $this->info("Generando imagen...");
            $imagePath = $this->image->generate($region, $temp, $moonData);

            if (!file_exists($imagePath)) {
                $this->error("Fallo al generar la imagen.");
                return 1;
            }

            // 4. Publicar en X (Twitter)
            $this->info("Publicando en X...");
            $xService = new XService($region); // Pasamos la región para cargar sus Keys

            $text = "🌡️ Reporte Actualizado ({$region})\n";
            $text .= "Temperatura: {$temp}°C\n";
            if ($moonData) {
                $text .= "Luna: " . round($moonData['iluminacion_pct'] ?? 0) . "% iluminada.\n";
            }
            $text .= "#Chile #Clima #{$region}";

            $result = $xService->sendTweet($text, $imagePath);

            // 5. Limpieza (Opcional: borrar imagen después de subir)
            // unlink($imagePath);

            $this->info("¡Éxito! Tweet publicado en la cuenta de {$region}.");

        } catch (\Exception $e) {
            $this->error("Error crítico: " . $e->getMessage());
            Log::error("Fallo en PostWeatherUpdate ({$region}): " . $e->getMessage());
        }

        return 0;
    }
}
