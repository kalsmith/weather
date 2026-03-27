<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Client;

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
            ])->timeout(15)->get($url);

            if ($response->failed()) {
                throw new \Exception("Error al conectar con MeteoChile: " . $response->status());
            }

            $crawler = new Crawler($response->body());
            // El nodo display-1 es el que contiene la temperatura grande en MeteoChile
            $tempNode = $crawler->filter('h1.text-center.mt-4.mb-5.display-1');

            if ($tempNode->count() > 0) {
                // Limpiamos el texto por si trae caracteres extraños o espacios
                $temp = trim($tempNode->text());
                return str_replace('°', '', $temp); // Retornamos solo el número
            }

            Log::warning("No se encontró el nodo de temperatura para {$region}");
            return null;

        } catch (\Exception $e) {
            Log::error("Error Scraping ({$region}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Consulta la API de Python para obtener datos lunares (Fase, Emoji, Iluminación).
     */
    public function getMoonData(string $region): ?array
    {
        $coords = (new AstroService())->getCoords($region);

        // CORRECCIÓN: Validamos que la URL exista en el .env para evitar "URI must be a string"
        $url = env('MOON_API_URL');

        if (empty($url)) {
            Log::error("Error API Luna: La variable MOON_API_URL no está definida en el .env");
            return null;
        }

        try {
            $client = new Client();
            $response = $client->post($url, [
                'json' => [
                    'secret_key' => '8Y++wM>9bI9C',
                    'lat' => $coords['lat'],
                    'lon' => $coords['lon'],
                ],
                'timeout' => 20, // Aumentamos a 20s por si Skyfield está cargando el archivo BSP
                'http_errors' => false // Para capturar errores 401/500 sin que explote Guzzle
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::error("Error API Luna ({$region}): El servidor Python respondió con código " . $response->getStatusCode());
                return null;
            }

            return json_decode($response->getBody()->getContents(), true);

        } catch (\Exception $e) {
            Log::error("Error API Luna ({$region}): " . $e->getMessage());
            return null;
        }
    }
}
