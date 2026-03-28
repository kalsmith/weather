<?php

namespace App\Services;

class ImageService
{
    public function generate(string $region, string $temp, ?array $moonData, array $sunData, string $type = 'clima'): string
    {
        $region = strtoupper($region);
        $width = 800;
        $height = 600;

        // 1. Lógica de fondo: PRIORIDAD ABSOLUTA AL TIPO
        if ($type === 'sunrise') {
            $isNight = false; // Forza fondo_ANTOF.png
        } elseif ($type === 'sunset') {
            $isNight = true;  // Forza moon_ANTOF.png
        } else {
            // Solo para reportes de clima estándar usamos el reloj
            $now = time();
            $isNight = ($now > $sunData['sunset_raw'] || $now < $sunData['sunrise_raw']);
        }

        $backgroundName = $isNight ? "moon_{$region}.png" : "fondo_{$region}.png";
        $backgroundPath = public_path("assets/backgrounds/{$backgroundName}");

        if (!file_exists($backgroundPath)) {
            $backgroundPath = public_path("assets/backgrounds/fondo_STGO.png");
        }

        // 2. Crear Lienzo
        $background = imagecreatefrompng($backgroundPath);
        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $background, 0, 0, 0, 0, $width, $height, imagesx($background), imagesy($background));
        imagedestroy($background);

        // 3. Colores y Texto Dinámico
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $yellow = imagecolorallocate($canvas, 255, 215, 0);

        // Si NO es noche (Sunrise/Día), usamos texto Negro y curva Amarilla
        $textColor = $isNight ? $white : $black;
        $curveColor = $isNight ? $white : $yellow;

        $this->drawSolarCurve($canvas, $width, $height, $curveColor);

        // 4. Textos Inferiores
        $font = 4;
        $yPos = $height - 40;

        if ($type === 'sunrise') {
            // Layout de Mañana (Texto Negro sobre fondo claro)
            imagestring($canvas, $font, 50, $yPos, "Amanecer: " . $sunData['sunrise'], $textColor);
            imagestring($canvas, $font, ($width / 2) - 60, $yPos, "Cenit: " . $sunData['transit'], $textColor);
            imagestring($canvas, $font, $width - 200, $yPos, "Ocaso: " . $sunData['sunset'], $textColor);
        } else {
            // Layout de Tarde/Noche
            $fase = $moonData['fase_nombre'] ?? 'Luna';
            imagestring($canvas, $font, 50, $yPos, "Fase: " . $fase, $textColor);
            imagestring($canvas, $font, ($width / 2) - 60, $yPos, "Ocaso: " . $sunData['sunset'], $textColor);
            imagestring($canvas, $font, $width - 200, $yPos, "Cielo Nocturno", $textColor);
        }

        // 5. Guardar
        $fileName = "reporte_{$region}_" . time() . ".png";
        $savePath = storage_path("app/public/reports/{$fileName}");
        imagepng($canvas, $savePath);
        imagedestroy($canvas);

        return $savePath;
    }

    private function drawSolarCurve($canvas, $w, $h, $color) { /* ... misma lógica de curva ... */ }
}
