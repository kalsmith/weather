<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UVService
{
    /**
     * Códigos Nacionales oficiales de la DMC para UV
     */
    protected $config = [
        'STGO' => [
            'codigo' => '330020', // Quinta Normal
            'nombre' => 'Santiago'
        ],
        'ANTOF' => [
            'codigo' => '180016', // Cerro Moreno (UV Específico)
            'nombre' => 'Antofagasta'
        ],
        'PUQ' => [
            'codigo' => '520006', // Carlos Ibáñez (UV Específico)
            'nombre' => 'Punta Arenas'
        ]
    ];

    protected $baseUrl = 'https://climatologia.meteochile.gob.cl/application/servicios/getRecienteUvb';
    protected $usuario = 'celabarcassi@gmail.com';
    protected $token   = '3432820e92bb80947ae7943f';

    public function getUVData(string $region = 'STGO'): ?array
    {
        $region = strtoupper($region);
        if (!isset($this->config[$region])) return null;

        try {
            $response = Http::timeout(15)->get($this->baseUrl, [
                'usuario' => $this->usuario,
                'token'   => $this->token
            ]);

            if ($response->failed()) return null;

            $data = $response->json();
            $codigoBuscado = (string) $this->config[$region]['codigo'];

            // Buscamos la estación en el reporte general
            $estacionData = collect($data['datosRecientes'] ?? [])
                ->first(function ($item) use ($codigoBuscado) {
                    return isset($item['estacion']['codigoNacional']) &&
                           (string)$item['estacion']['codigoNacional'] === $codigoBuscado;
                });

            if (!$estacionData || empty($estacionData['indiceUV'])) {
                Log::warning("UVService: Sin datos UV para la estación {$codigoBuscado} ({$region})");
                return null;
            }

            // IMPORTANTE: Obtenemos TODO el historial para el gráfico
            $historico = $estacionData['indiceUV'];

            // Obtenemos la última lectura para los datos de texto
            $ultimaLectura = collect($historico)->last();

            // Si el índice no viene, asumimos 0
            $valorNumerico = isset($ultimaLectura['indiceUV']) ? (int)$ultimaLectura['indiceUV'] : 0;

            return [
                'valor'     => $valorNumerico,
                'riesgo'    => $this->getUVLevel($valorNumerico),
                'emoji'     => $this->getUVEmoji($valorNumerico),
                'hora'      => $ultimaLectura['hora'] ?? null,
                'historico' => $historico, // Alimenta al UVImageService
            ];

        } catch (\Exception $e) {
            Log::error("UVService Error ({$region}): " . $e->getMessage());
            return null;
        }
    }

    private function getUVLevel(int $valor): string
    {
        if ($valor <= 2) return 'BAJO';
        if ($valor <= 5) return 'MODERADO';
        if ($valor <= 7) return 'ALTO';
        if ($valor <= 10) return 'MUY ALTO';
        return 'EXTREMO';
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
