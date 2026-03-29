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

    public function getStationDetails(string $region): array
    {
        try {
            $url = $this->urls[$region] ?? null;

            if (!$url) {
                return ['status' => 'error', 'message' => "Región {$region} no mapeada"];
            }

            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->timeout(10)->get($url);

            if ($response->failed()) return [];

            $crawler = new Crawler($response->body());

            // 1. Humedad: Limpiamos también posibles ceros si fuera necesario
            $humedadRaw = $crawler->filter('.text-humedad')->count() > 0
                ? trim($crawler->filter('.text-humedad')->text())
                : null;

            // Convertimos a int para quitar ceros a la izquierda (ej: "08%" -> "8")
            $humedad = is_numeric($humedadRaw) ? (int)$humedadRaw : $humedadRaw;

            // 2. Viento
            $vientoFinal = null;
            $vientoTable = $crawler->filter('table');

            foreach ($vientoTable as $table) {
                $rows = (new Crawler($table))->filter('tr');
                foreach ($rows as $row) {
                    $rowText = $row->nodeValue;
                    if (str_contains($rowText, 'Promedio 2 Min.')) {
                        $cells = (new Crawler($row))->filter('td');
                        if ($cells->count() >= 3) {
                            $rawViento = trim($cells->eq(2)->text());

                            // Extraemos lo que hay después del "/"
                            $parts = explode('/', $rawViento);
                            $vientoValue = trim(end($parts));

                            // Lógica de limpieza:
                            // Si es numérico (ej: "05"), lo convertimos a int para que sea "5"
                            // Si es "Calma", se mantiene como string
                            if (is_numeric($vientoValue)) {
                                $vientoFinal = (int)$vientoValue;
                            } else {
                                $vientoFinal = $vientoValue;
                            }
                        }
                    }
                }
            }

            return [
                'humedad' => $humedad,
                'viento'  => $vientoFinal,
                'status'  => 'ok'
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
