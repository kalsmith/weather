<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeteoDataService
{
    protected $baseUrl = 'https://climatologia.meteochile.gob.cl/application/servicios/getRecienteRecienteEstacion';
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
            $response = Http::timeout(15)->get($this->baseUrl, [
                'usuario' => $this->usuario,
                'token'   => $this->token,
                'codigo'  => $codigo
            ]);

            if ($response->failed()) return null;

            $data = $response->json();

            // Tomamos el registro más reciente (índice 0)
            $actual = $data['datosEstaciones']['datos'][0] ?? null;

            if (!$actual) return null;

            return [
                'temperatura' => (float)$actual['temperatura'],
                'humedad'     => (int)$actual['humedadRelativa'],
                'viento'      => $this->knotsToKmh($actual['fuerzaDelVientoPromedio10Minutos']),
                'presion'     => (float)$actual['presionEstacion'],
                'maxima_12h'  => (float)$actual['temperaturaMaxima12Horas'],
                'minima_12h'  => (float)$actual['temperaturaMinima12Horas'],
                'radiacion'   => (float)$actual['radiacionGlobalInst'], // Watt/m2
                'momento'     => $actual['momento'],
            ];

        } catch (\Exception $e) {
            Log::error("MeteoDataService Error ({$region}): " . $e->getMessage());
            return null;
        }
    }

    private function knotsToKmh($knots): float
    {
        // La DMC entrega el viento en nudos (kt). 1 kt = 1.852 km/h
        return round((float)$knots * 1.852, 1);
    }
}
