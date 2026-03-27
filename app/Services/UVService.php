<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class UVService
{
    protected $config = [
        'STGO' => [
            'url' => 'https://www.meteochile.gob.cl/PortalDMC-web/index.xhtml',
            'type' => 'table_class'
        ],
        'ANTOF' => [
            'url' => 'https://www.meteochile.gob.cl/PortalDMC-web/otros_pronosticos/radiacion_uv_region.xhtml?estacion=230001',
            'type' => 'id_based'
        ]
    ];

    public function getUVData(string $region = 'STGO'): ?array
    {
        $region = strtoupper($region);
        if (!isset($this->config[$region])) return null;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ])->timeout(12)->get($this->config[$region]['url']);

            if ($response->failed()) return null;

            $crawler = new Crawler($response->body());
            $indice = null;
            $riesgo = 'N/A';

            if ($this->config[$region]['type'] === 'id_based') {
                // Lógica Antofagasta (Intentar ID primero, luego posición)
                $nodeIndice = $crawler->filter('#indice_obs');
                if ($nodeIndice->count() > 0) {
                    $indice = trim($nodeIndice->text());
                    $riesgo = $crawler->filter('#riesgo')->count() > 0 ? trim($crawler->filter('#riesgo')->text()) : 'N/A';
                } else {
                    // Fallback: Buscar en la tabla de observación por celdas
                    $tabla = $crawler->filter('table#obsTable');
                    if ($tabla->count() > 0) {
                        $celdas = $tabla->filter('td');
                        // En Antofagasta, el índice suele ser la 5ta celda (index 4)
                        $indice = $celdas->count() >= 5 ? trim($celdas->at(4)->text()) : null;
                        $riesgo = $celdas->count() >= 6 ? trim($celdas->at(5)->text()) : 'N/A';
                    }
                }
            } else {
                // Lógica Santiago (Tabla por clases)
                $tabla = $crawler->filter('table.tablaObservado')->first();
                if ($tabla->count() > 0) {
                    $cells = $tabla->filter('td.tablaObservadoDatos');
                    if ($cells->count() >= 3) {
                        $indice = trim($cells->at(1)->text());
                        $riesgo = trim($cells->at(2)->text());
                    }
                }
            }

            // Limpieza final: Si el índice es "-", "s/i" o vacío, no sirve.
            if (empty($indice) || !is_numeric(substr($indice, 0, 1))) {
                return null;
            }

            $valorNumerico = (int)$indice;

            return [
                'valor'  => $valorNumerico,
                'riesgo' => $riesgo,
                'emoji'  => $this->getUVEmoji($valorNumerico),
            ];

        } catch (\Exception $e) {
            Log::error("UVService Error ({$region}): " . $e->getMessage());
            return null;
        }
    }

    private function getUVEmoji(int $indice): string
    {
        if ($indice <= 2) return '🟢';
        if ($indice <= 5) return '🟡';
        if ($indice <= 7) return '🟠';
        if ($indice <= 10) return '🔴';
        return '🟣';
    }
}
