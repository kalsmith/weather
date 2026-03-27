<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class WeatherService
{
    /**
     * Obtiene la temperatura real mediante scraping de MeteoChile.
     */
    public function getTemperature(string $region = 'STGO'): ?string
    {
        $urls = [
            'STGO'  => 'https://climatologia.meteochile.gob.cl/application/diariob/visorDeDatosEma/330020',
            'ANTOF' => 'https://climatologia.meteochile.gob.cl/application/diariob/visorDeDatosEma/230002',
        ];

        $url = $urls[strtoupper($region)] ?? $urls['STGO'];

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->timeout(10)->get($url);

            if ($response->failed()) {
                throw new \Exception("Status: " . $response->status());
            }

            $crawler = new Crawler($response->body());
            $tempNode = $crawler->filter('h1.display-1');

            if ($tempNode->count() > 0) {
                $temp = trim($tempNode->text());
                return str_replace(['°', 'C', ' '], '', $temp);
            }

            Log::warning("WeatherService: No se encontró el nodo de temperatura para {$region}");
            return null;

        } catch (\Exception $e) {
            Log::error("WeatherService - Error Scraping ({$region}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Consulta la API de Python y retorna un mensaje contextualizado a la geografía chilena.
     */
    public function getMoonMessage(string $region): string
    {
        $moonData = $this->getMoonData($region);

        if (!$moonData) {
            return "Luna: Información no disponible.";
        }

        $altura = (float)($moonData['altura_grados'] ?? 0);
        $iluminacion = $moonData['iluminacion_pct'] ?? 0;
        $faseEmoji = $moonData['fase_emoji'] ?? '🌙';
        $faseNombre = $moonData['fase_nombre'] ?? 'Luna';
        $horaActual = now()->hour;

        // --- LÓGICA DE VISIBILIDAD CHILENA ---

        // 1. Si la altura es negativa, está bajo el horizonte
        if ($altura < 0) {
            return "Luna bajo el horizonte.";
        }

        // 2. Si está entre 0 y 18 grados, está detrás de la Cordillera de los Andes
        if ($altura >= 0 && $altura < 18) {
            return "🏔️ Luna tras la cordillera.";
        }

        // 3. Si ya superó los 18 grados, es visible
        if ($horaActual >= 7 && $horaActual < 19) {
            // Visibilidad diurna
            return "🔭 Luna visible de día: {$faseEmoji} ({$iluminacion}% iluminada).";
        }

        // Visibilidad nocturna estándar
        return "Luna: {$faseEmoji} {$faseNombre} ({$iluminacion}% iluminada).";
    }

    /**
     * Consulta base a la API de Python.
     */
    public function getMoonData(string $region): ?array
    {
        $astro = new AstroService();
        $coords = $astro->getCoords($region);
        $url = env('MOON_API_URL');
        $secret = "8Y++wM>9bI9C";

        if (empty($url)) {
            Log::error("WeatherService: MOON_API_URL no definida.");
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'secret_key' => $secret,
                    'lat'        => (float)$coords['lat'],
                    'lon'        => (float)$coords['lon'],
                ]);

            return $response->successful() ? $response->json() : null;

        } catch (\Exception $e) {
            Log::error("WeatherService - Exception API Luna: " . $e->getMessage());
            return null;
        }
    }
}
