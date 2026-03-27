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
            ])->timeout(10)->get($this->config[$region]['url']);

            if ($response->failed()) return null;

            $crawler = new Crawler($response->body());

            if ($this->config[$region]['type'] === 'id_based') {
                // Lógica Antofagasta (IDs)
                $indice = $crawler->filter('#indice_obs')->count() > 0 ? trim($crawler->filter('#indice_obs')->text()) : null;
                $riesgo = $crawler->filter('#riesgo')->count() > 0 ? trim($crawler->filter('#riesgo')->text()) : 'N/A';
            } else {
                // Lógica Santiago (Tabla por clases)
                // Buscamos la primera tabla "tablaObservado", y sus celdas de datos
                $cells = $crawler->filter('table.tablaObservado')->first()->filter('td.tablaObservadoDatos');

                if ($cells->count() >= 3) {
                    $indice = trim($cells->at(1)->text()); // Segunda celda es el índice
                    $riesgo = trim($cells->at(2)->text()); // Tercera celda es el riesgo
                } else {
                    $indice = null;
                }
            }

            if ($indice === null || $indice === '') return null;

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
