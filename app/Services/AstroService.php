<?php

namespace App\Services;

class AstroService
{
    public function getSunData(string $region): array
    {
        $coords = $this->getCoords($region);
        $sunInfo = date_sun_info(time(), $coords['lat'], $coords['lon']);

        return [
            'sunrise' => date("H:i", $sunInfo['sunrise']),
            'transit' => date("H:i", $sunInfo['transit']),
            'sunset'  => date("H:i", $sunInfo['sunset']),
            'sunrise_raw' => $sunInfo['sunrise'],
            'sunset_raw'  => $sunInfo['sunset'],
        ];
    }

    public function isThirtyMinsBeforeSunrise(string $region): bool
    {
        $data = $this->getSunData($region);
        $thirtyMinsBefore = $data['sunrise_raw'] - (30 * 60);
        return date('H:i') === date('H:i', $thirtyMinsBefore);
    }

    public function getCoords(string $region): array
    {
        $data = [
            'STGO'  => ['lat' => -33.4489, 'lon' => -70.6693],
            'ANTOF' => ['lat' => -23.65,   'lon' => -70.4],
        ];
        return $data[strtoupper($region)] ?? $data['STGO'];
    }
}
