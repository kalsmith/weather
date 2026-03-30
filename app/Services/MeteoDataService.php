<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeteoDataService
{
    // URL para datos de las últimas 12 horas (más completo)
    protected $baseUrl = 'https://climatologia.meteochile.gob.cl/application/servicios/getDatosRecientesEma';
    protected $usuario = 'celabarcassi@gmail.com';
    protected $token   = '3432820e92bb80947ae7943f';

    protected $estaciones = [
        'STGO'  => '330020', // Quinta Normal
        'ANTOF' => '230002', // Cerro Moreno
    ];

    public function getStationDetails(string $region): ?array
    {
        $region = strtoupper($region);
        $codigo = $this->estaciones[$region] ?? null;

        if (!$codigo) return null;

        try {
            // El endpoint correcto para el JSON que pasaste es /getDatosRecientesEma/{codigo}
            $response = Http::timeout(15)->get("{$this->baseUrl}/{$codigo}", [
                'usuario' => $this->usuario,
                'token'   => $this->token,
            ]);

            if ($response->failed()) return null;

            $data = $response->json();

            // La estructura es datosEstaciones -> datos -> [0]
            $actual = $data['datosEstaciones']['datos'][0] ?? null;

            if (!$actual) {
                Log::warning("MeteoDataService: No hay datos para la estación {$codigo}");
                return null;
            }

            return [
                // Usamos cleanValue para quitar "°C", "%", "hPas.", etc.
                'temperatura' => $this->cleanValue($actual['temperatura']),
                'humedad'     => (int) $this->cleanValue($actual['humedadRelativa']),
                'viento'      => $this->knotsToKmh($this->cleanValue($actual['fuerzaDelViento'])),
                'presion'     => $this->cleanValue($actual['presionEstacion']),
                'maxima_12h'  => $this->cleanValue($actual['temperaturaMaxima12Horas']),
                'minima_12h'  => $this->cleanValue($actual['temperaturaMinima12Horas']),
                'radiacion'   => $this->cleanValue($actual['radiacionGlobalInst']),
                'momento'     => $actual['momento'],
            ];

        } catch (\Exception $e) {
            Log::error("MeteoDataService Error ({$region}): " . $e->getMessage());
            return null;
        }
    }

    /**
     * Limpia el string de la DMC para convertirlo en un número válido.
     * Ejemplo: "27.6 °C" -> 27.6 | "27 %" -> 27 | "954.1 hPas." -> 954.1
     */
    private function cleanValue($value): float
    {
        if (is_null($value)) return 0.0;

        // Quita cualquier cosa que no sea número, punto o signo negativo
        $cleaned = preg_replace('/[^0-9\.\-]/', '', $value);

        return (float) $cleaned;
    }

    private function knotsToKmh($knots): float
    {
        // 1 kt = 1.852 km/h
        return round((float)$knots * 1.852, 1);
    }
}
