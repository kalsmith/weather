<?php

namespace App\Services;

class ImageService
{
    public function generate(string $region, string $temp, ?array $moonData, array $sunData): string
    {
        $region = strtoupper($region);
        $width = 800;
        $height = 600;

        $backgroundPath = public_path("assets/backgrounds/fondo_{$region}.png");
        if (!file_exists($backgroundPath)) {
            $backgroundPath = public_path("assets/backgrounds/fondo_STGO.png");
        }

        $background = imagecreatefrompng($backgroundPath);
        $canvas = imagecreatetruecolor($width, $height);

        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled($canvas, $background, 0, 0, 0, 0, $width, $height, imagesx($background), imagesy($background));
        imagedestroy($background);

        // Colores
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $yellow = imagecolorallocate($canvas, 255, 215, 0);
        $boxColor = imagecolorallocatealpha($canvas, 255, 255, 255, 40);

        // 1. Dibujar Parábola
        $this->drawSolarCurve($canvas, $width, $height, $yellow);

        // 2. Cuadro Temperatura
        imagefilledrectangle($canvas, 20, 20, 240, 70, $boxColor);
        imagestring($canvas, 5, 40, 35, "TEMP: {$temp} C", $black);

        // 3. Cuadro Luna
        if ($moonData) {
            $ilum = round($moonData['iluminacion_pct'] ?? 0, 1);
            imagefilledrectangle($canvas, $width - 220, 20, $width - 20, 70, $boxColor);
            imagestring($canvas, 5, $width - 200, 35, "LUNA: {$ilum}%", $black);
        }

        // 4. Pegar los 3 textos astronómicos abajo
        $font = 4;
        $yPos = $height - 40;
        imagestring($canvas, $font, 50, $yPos, "Amanecer: " . $sunData['sunrise'], $white);
        imagestring($canvas, $font, ($width / 2) - 60, $yPos, "Cenit: " . $sunData['transit'], $white);
        imagestring($canvas, $font, $width - 200, $yPos, "Ocaso: " . $sunData['sunset'], $white);

        // Guardar
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
