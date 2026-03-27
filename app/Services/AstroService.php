<?php

namespace App\Services;

class AstroService
{
    /**
     * Obtiene los hitos solares del día para una región.
     */
    public function getSunData(string $region): array
    {
        $coords = $this->getCoords($region);

        // date_sun_info usa el timestamp actual para calcular según el día del año
        $sunInfo = date_sun_info(time(), $coords['lat'], $coords['lon']);

        return [
            'sunrise'     => date("H:i", $sunInfo['sunrise']),
            'transit'     => date("H:i", $sunInfo['transit']), // Cenit
            'sunset'      => date("H:i", $sunInfo['sunset']),
            'sunrise_raw' => $sunInfo['sunrise'],
            'sunset_raw'  => $sunInfo['sunset'],
            'transit_raw' => $sunInfo['transit'],
        ];
    }

    /**
     * Validador para el Scheduler: ¿Faltan 30 min para el amanecer?
     */
    public function isThirtyMinsBeforeSunrise(string $region): bool
    {
        $data = $this->getSunData($region);
        $thirtyMinsBefore = $data['sunrise_raw'] - (30 * 60);
        return date('H:i') === date('H:i', $thirtyMinsBefore);
    }

    /**
     * Validador para el Scheduler: ¿Faltan 30 min para el ocaso?
     */
    public function isThirtyMinsBeforeSunset(string $region): bool
    {
        $data = $this->getSunData($region);
        $thirtyMinsBeforeSunset = $data['sunset_raw'] - (30 * 60);
        return date('H:i') === date('H:i', $thirtyMinsBeforeSunset);
    }

    /**
     * Diccionario de coordenadas por región.
     */
    public function getCoords(string $region): array
    {
        $data = [
            'STGO'  => ['lat' => -33.4489, 'lon' => -70.6693], // Santiago Centro
            'ANTOF' => ['lat' => -23.6509, 'lon' => -70.3975], // Antofagasta
        ];

        return $data[strtoupper($region)] ?? $data['STGO'];
    }
}
