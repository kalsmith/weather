<?php

namespace App\Services;

class ImageService
{
    public function generate(string $region, string $temp, ?array $moonData, array $sunData, string $type = 'clima'): string
    {
        $region = strtoupper($region);
        $width = 800;
        $height = 600;

        // 1. Lógica de fondo dinámico con Prioridad de Tipo
        $now = time();

        if ($type === 'sunrise') {
            // Reporte de la mañana: Forzamos fondo de día (fondo_REGION.png)
            $isNight = false;
        } elseif ($type === 'sunset') {
            // Reporte de la tarde: Forzamos fondo de noche (moon_REGION.png)
            $isNight = true;
        } else {
            // Reporte estándar: Manda el reloj
            $isNight = ($now > $sunData['sunset_raw'] || $now < $sunData['sunrise_raw']);
        }

        // Selección de archivo según disponibilidad
        $backgroundName = $isNight ? "moon_{$region}.png" : "fondo_{$region}.png";
        $backgroundPath = public_path("assets/backgrounds/{$backgroundName}");

        if (!file_exists($backgroundPath)) {
            $backgroundPath = public_path("assets/backgrounds/fondo_STGO.png");
        }

        // 2. Crear Lienzo y Cargar Fondo
        $background = imagecreatefrompng($backgroundPath);
        $canvas = imagecreatetruecolor($width, $height);

        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled($canvas, $background, 0, 0, 0, 0, $width, $height, imagesx($background), imagesy($background));
        imagedestroy($background);

        // 3. Definición de Colores
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $yellow = imagecolorallocate($canvas, 255, 215, 0);
        $boxColor = imagecolorallocatealpha($canvas, 255, 255, 255, 40);

        // El texto inferior cambia según el fondo para legibilidad
        $textColor = $isNight ? $white : $black;

        // 4. Dibujar Parábola
        $curveColor = $isNight ? $white : $yellow;
        $this->drawSolarCurve($canvas, $width, $height, $curveColor);

        // 5. Cuadros Superiores (Info persistente)
        imagefilledrectangle($canvas, 20, 20, 240, 70, $boxColor);
        imagestring($canvas, 5, 40, 35, "TEMP: {$temp} C", $black);

        if ($moonData) {
            $ilum = round($moonData['iluminacion_pct'] ?? 0, 1);
            imagefilledrectangle($canvas, $width - 220, 20, $width - 20, 70, $boxColor);
            imagestring($canvas, 5, $width - 200, 35, "LUNA: {$ilum}%", $black);
        }

        // 6. Textos Inferiores Dinámicos
        $font = 4;
        $yPos = $height - 40;

        if ($type === 'sunrise') {
            // Layout Mañana: Información completa del día
            imagestring($canvas, $font, 50, $yPos, "Amanecer: " . $sunData['sunrise'], $textColor);
            imagestring($canvas, $font, ($width / 2) - 60, $yPos, "Cenit: " . $sunData['transit'], $textColor);
            imagestring($canvas, $font, $width - 200, $yPos, "Ocaso: " . $sunData['sunset'], $textColor);
        } else {
            // Layout Noche/Ocaso: Información enfocada en la visibilidad nocturna
            $fase = $moonData['fase_nombre'] ?? 'Luna';
            imagestring($canvas, $font, 50, $yPos, "Fase: " . $fase, $textColor);
            imagestring($canvas, $font, ($width / 2) - 60, $yPos, "Ocaso: " . $sunData['sunset'], $textColor);
            imagestring($canvas, $font, $width - 200, $yPos, "Cielo Nocturno", $textColor);
        }

        // 7. Guardado de Imagen
        $fileName = "reporte_{$region}_" . time() . ".png";
        $savePath = storage_path("app/public/reports/{$fileName}");

        if (!is_dir(storage_path("app/public/reports"))) {
            mkdir(storage_path("app/public/reports"), 0755, true);
        }

        imagepng($canvas, $savePath);
        imagedestroy($canvas);

        return $savePath;
    }

    private function drawSolarCurve($canvas, $w, $h, $color)
    {
        $steps = 200;
        for ($i = 0; $i < $steps; $i++) {
            $x1 = ($i / $steps) * $w;
            $y1 = $h - sin(($i / $steps) * M_PI) * $h * 0.4 - 100;
            $x2 = (($i + 1) / $steps) * $w;
            $y2 = $h - sin((($i + 1) / $steps) * M_PI) * $h * 0.4 - 100;
            imageline($canvas, $x1, $y1, $x2, $y2, $color);
        }
    }
}
