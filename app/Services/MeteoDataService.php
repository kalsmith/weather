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
        'PUQ'   => '520014', // Carlos Ibáñez del Campo (Punta Arenas)
    ];

    public function getStationDetails(string $region): ?array
    {
        $region = strtoupper($region);
        $codigo = $this->estaciones[$region] ?? null;

        if (!$codigo) return null;

        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/{$codigo}", [
                'usuario' => $this->usuario,
                'token'   => $this->token,
            ]);

            if ($response->failed()) return null;

            $data = $response->json();
            $actual = $data['datosEstaciones']['datos'][0] ?? null;

            if (!$actual) {
                Log::warning("MeteoDataService: No hay datos para la estación {$codigo}");
                return null;
            }

            return [
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

    public function getFireRiskData(string $region): ?array
    {
        $region = strtoupper($region);
        $codigoBuscado = $this->estaciones[$region] ?? null;

        if (!$codigoBuscado) return null;

        try {
            $urlRiesgo = 'https://climatologia.meteochile.gob.cl/application/geoservicios/getRiesgoIncendio';

            $response = Http::timeout(15)->get($urlRiesgo, [
                'usuario' => $this->usuario,
                'token'   => $this->token,
            ]);

            if ($response->failed()) return null;

            $data = $response->json();

            $feature = collect($data['features'])->first(function ($f) use ($codigoBuscado) {
                return (string) ($f['properties']['CodigoNacional'] ?? '') === (string) $codigoBuscado;
            });

            if (!$feature) {
                Log::warning("MeteoDataService (Riesgo): No se encontró la estación {$codigoBuscado} en el mapa de riesgos.");
                return null;
            }

            $p = $feature['properties'];

            return [
                'temperaturaMaximaHoy'      => $this->cleanValue($p['temperaturaMaximaHoy'] ?? null),
                'temperaturaMaximaManana'   => $this->cleanValue($p['temperaturaMaximaManana'] ?? null),
                'humedadMinimaHoy'          => $this->cleanValue($p['humedadMinimaHoy'] ?? null),
                'intensidadVientoMaximoHoy' => $this->cleanValue($p['intensidadVientoMaximoHoy'] ?? null),
                'momento'                   => $p['momento'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error("MeteoDataService Riesgo Error ({$region}): " . $e->getMessage());
            return null;
        }
    }

    private function cleanValue($value): float
    {
        if (is_null($value)) return 0.0;
        $cleaned = preg_replace('/[^0-9\.\-]/', '', $value);
        return (float) $cleaned;
    }

    private function knotsToKmh($knots): float
    {
        return round((float)$knots * 1.852, 1);
    }
}
