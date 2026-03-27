<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class MeteoDataService
{
    protected $urls = [
            'STGO'  => 'https://climatologia.meteochile.gob.cl/application/diariob/visorDeDatosEma/330020',
            'ANTOF' => 'https://climatologia.meteochile.gob.cl/application/diariob/visorDeDatosEma/230002',
    ];

    public function getStationDetails(string $url): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(10)->get($url);

            if ($response->failed()) return [];

            $crawler = new Crawler($response->body());

            // 1. Humedad: Buscamos la clase específica .text-humedad
            $humedad = $crawler->filter('.text-humedad')->count() > 0
                ? trim($crawler->filter('.text-humedad')->text())
                : null;

            // 2. Viento: Buscamos en la tabla de Viento Superficie
            $vientoKmh = null;
            $vientoTable = $crawler->filter('table');

            foreach ($vientoTable as $table) {
                $rows = (new Crawler($table))->filter('tr');
                foreach ($rows as $row) {
                    $rowText = $row->nodeValue;
                    if (str_contains($rowText, 'Promedio 2 Min.')) {
                        $cells = (new Crawler($row))->filter('td');
                        if ($cells->count() >= 3) {
                            $rawViento = trim($cells->at(2)->text()); // Formato "284/14"
                            $vientoKmh = last(explode('/', $rawViento));
                        }
                    }
                }
            }

            return [
                'humedad' => $humedad,
                'viento'  => $vientoKmh,
                'status'  => 'ok'
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
