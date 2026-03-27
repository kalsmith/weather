<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class MeteoDataService
{
    // Cambiamos el nombre del parámetro de $url a $region para que sea claro
    protected $urls = [
        'STGO'  => 'https://climatologia.meteochile.gob.cl/application/diariob/visorDeDatosEma/330020',
        'ANTOF' => 'https://climatologia.meteochile.gob.cl/application/diariob/visorDeDatosEma/230002',
    ];

    public function getStationDetails(string $region): array
    {
        try {
            // 1. Obtener la URL real usando la región
            $url = $this->urls[$region] ?? null;

            if (!$url) {
                return ['status' => 'error', 'message' => "Región {$region} no mapeada"];
            }

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(10)->get($url);

            if ($response->failed()) return [];

            $crawler = new Crawler($response->body());

            // 2. Humedad: .text-humedad
            $humedad = $crawler->filter('.text-humedad')->count() > 0
                ? trim($crawler->filter('.text-humedad')->text())
                : null;

            // 3. Viento
            $vientoKmh = null;
            $vientoTable = $crawler->filter('table');

            foreach ($vientoTable as $table) {
                $rows = (new Crawler($table))->filter('tr');
                foreach ($rows as $row) {
                    $rowText = $row->nodeValue;
                    if (str_contains($rowText, 'Promedio 2 Min.')) {
                        $cells = (new Crawler($row))->filter('td');
                        if ($cells->count() >= 3) {
                            $rawViento = trim($cells->eq(2)->text());
                            // Usamos explode y end para sacar el número tras el "/"
                            $parts = explode('/', $rawViento);
                            $vientoKmh = trim(end($parts));
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
