<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UVService
{
    /**
     * Códigos Nacionales oficiales de la DMC
     */
    protected $config = [
        'STGO' => [
            'codigo' => 330020, // Quinta Normal
            'nombre' => 'Santiago'
        ],
        'ANTOF' => [
            'codigo' => 230002, // Cerro Moreno (Confirmar en el JSON completo)
            'nombre' => 'Antofagasta'
        ]
    ];

    // URL base con tus credenciales
    protected $baseUrl = 'https://climatologia.meteochile.gob.cl/application/servicios/getRecienteUvb';
    protected $usuario = 'celabarcassi@gmail.com';
    protected $token   = '3432820e92bb80947ae7943f';

    public function getUVData(string $region = 'STGO'): ?array
    {
        $region = strtoupper($region);
        if (!isset($this->config[$region])) return null;

        try {
            // 1. Petición a la API oficial
            $response = Http::timeout(15)->get($this->baseUrl, [
                'usuario' => $this->usuario,
                'token'   => $this->token
            ]);

            if ($response->failed()) return null;

            $data = $response->json();
            $codigoBuscado = $this->config[$region]['codigo'];

            // 2. Buscar la estación en el array de datosRecientes
            $estacionData = collect($data['datosRecientes'] ?? [])
                ->firstWhere('estacion.codigoNacional', $codigoBuscado);

            if (!$estacionData || empty($estacionData['indiceUV'])) {
                Log::warning("UVService: No se encontraron datos para la estación {$codigoBuscado}");
                return null;
            }

            // 3. Obtener la última lectura (el final del array es lo más reciente)
            $ultimaLectura = collect($estacionData['indiceUV'])->last();

            $valorNumerico = (int) ($ultimaLectura['indiceUV'] ?? 0);
            $riesgo = $this->getUVLevel($valorNumerico);

            return [
                'valor'  => $valorNumerico,
                'riesgo' => $riesgo,
                'emoji'  => $this->getUVEmoji($valorNumerico),
                'hora_utc' => $ultimaLectura['hora'] ?? null, // Útil para debug
            ];

        } catch (\Exception $e) {
            Log::error("UVService Error ({$region}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mapea el valor a la categoría de riesgo de la DMC/OMS
     */
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
        if ($indice <= 2) return '🟢'; // Bajo
        if ($indice <= 5) return '🟡'; // Moderado
        if ($indice <= 7) return '🟠'; // Alto
        if ($indice <= 10) return '🔴'; // Muy Alto
        return '🟣'; // Extremo
    }
}
