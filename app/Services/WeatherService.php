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
            // Usamos un User-Agent real para evitar bloqueos básicos
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->timeout(10)->get($url);

            if ($response->failed()) {
                throw new \Exception("Error al conectar con MeteoChile: " . $response->status());
            }

            $crawler = new Crawler($response->body());
            $tempNode = $crawler->filter('h1.text-center.mt-4.mb-5.display-1');

            if ($tempNode->count() > 0) {
                return trim($tempNode->text());
            }

            Log::warning("No se encontró el nodo de temperatura para {$region}");
            return null;

        } catch (\Exception $e) {
            Log::error("Error Scraping ({$region}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Consulta la API de Python (Hosting) para obtener datos astronómicos.
     */
    public function getMoonData(string $region = 'STGO'): ?array
    {
        // Coordenadas base (puedes moverlas al config o .env)
        $coords = [
            'STGO'  => ['lat' => -33.4489, 'lon' => -70.6693],
            'ANTOF' => ['lat' => -23.65,   'lon' => -70.4],
        ];

        $pos = $coords[strtoupper($region)] ?? $coords['STGO'];

        try {
            $response = Http::withOptions(['verify' => false]) // Por si el SSL del hosting da líos
                ->timeout(5)
                ->post(env('MOON_API_URL', 'https://pythonweather.soltys.cl/luna'), [
                    'secret_key' => env('MOON_API_SECRET', '8Y++wM>9bI9C'),
                    'lat' => $pos['lat'],
                    'lon' => $pos['lon']
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("API Luna falló para {$region}: " . $response->status());
            return null;

        } catch (\Exception $e) {
            Log::error("Error conexión API Luna ({$region}): " . $e->getMessage());
            return null;
        }
    }
}
