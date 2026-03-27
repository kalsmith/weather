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
            // Nodo display-1: temperatura grande en MeteoChile
            $tempNode = $crawler->filter('h1.display-1');

            if ($tempNode->count() > 0) {
                $temp = trim($tempNode->text());
                // Retornamos el número limpio (ej: 21.4)
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
     * Consulta la API de Python para obtener datos lunares (Fase, Emoji, Iluminación).
     */
    public function getMoonData(string $region): ?array
    {
        // Obtenemos coordenadas desde AstroService
        $astro = new AstroService();
        $coords = $astro->getCoords($region);

        $url = env('MOON_API_URL');
        $secret = "8Y++wM>9bI9C"; // Tu clave secreta configurada en Python

        if (empty($url)) {
            Log::error("WeatherService: MOON_API_URL no definida en .env");
            return null;
        }

        try {
            // Usamos el cliente Http de Laravel para mayor simplicidad y manejo de JSON
            $response = Http::timeout(15)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'secret_key' => $secret,
                    'lat'        => (float)$coords['lat'],
                    'lon'        => (float)$coords['lon'],
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("WeatherService - Error API Luna ({$region}): " . $response->status() . " - " . $response->body());
            return null;

        } catch (\Exception $e) {
            Log::error("WeatherService - Exception API Luna ({$region}): " . $e->getMessage());
            return null;
        }
    }
}
