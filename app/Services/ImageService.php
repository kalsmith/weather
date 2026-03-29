<?php

namespace App\Services;

class ImageService
{
    public function generate(string $region, string $temp, ?array $moonData, array $sunData, string $type = 'clima'): string
    {
        $region = strtoupper($region);
        $width = 800;
        $height = 800;

        // 1. Lógica de fondo y estado
        if ($type === 'sunrise') {
            $isNight = false;
        } elseif ($type === 'sunset') {
            $isNight = true;
        } else {
            $now = time();
            $isNight = ($now > $sunData['sunset_raw'] || $now < $sunData['sunrise_raw']);
        }

        $backgroundName = $isNight ? "moon_{$region}.png" : "fondo_{$region}.png";
        $backgroundPath = public_path("assets/backgrounds/{$backgroundName}");

        // 2. Crear Lienzo
        $background = imagecreatefrompng($backgroundPath);
        $canvas = imagecreatetruecolor($width, $height);
        imagecopyresampled($canvas, $background, 0, 0, 0, 0, $width, $height, imagesx($background), imagesy($background));
        imagedestroy($background);

        // 3. Colores
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $gray = imagecolorallocate($canvas, 200, 200, 200);
        $pillBg = imagecolorallocatealpha($canvas, 255, 255, 255, 50);

        // 4. EL GRAFICADOR DE HORIZONTE 24H
        $this->drawHorizonGraficador($canvas, $width, $height, $sunData, $white, $gray);

        // 5. Cuadros Superiores
        $this->drawTextPill($canvas, "TEMP: {$temp} C", 20, 30, 5, $black, $pillBg);
        if ($moonData) {
            $ilum = round($moonData['iluminacion_pct'] ?? 0, 1);
            $this->drawTextPill($canvas, "LUNA: {$ilum}%", $width - 180, 30, 5, $black, $pillBg);
        }

        // 6. Textos Inferiores (Legibilidad 100%)
        $yPos = $height - 80;
        if ($type === 'sunrise') {
            $this->drawTextPill($canvas, "Amanecer: " . $sunData['sunrise'], 40, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Cenit: " . $sunData['transit'], ($width / 2) - 60, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Ocaso: " . $sunData['sunset'], $width - 200, $yPos, 4, $black, $pillBg);
        } else {
            $fase = $moonData['fase_nombre'] ?? 'Luna';
            $this->drawTextPill($canvas, "Fase: " . $fase, 40, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Ocaso: " . $sunData['sunset'], ($width / 2) - 60, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Cielo Nocturno", $width - 200, $yPos, 4, $black, $pillBg);
        }

        // 7. Guardar
        $fileName = "reporte_{$region}_" . time() . ".png";
        $savePath = storage_path("app/public/reports/{$fileName}");
        imagepng($canvas, $savePath);
        imagedestroy($canvas);

        return $savePath;
    }

    private function drawHorizonGraficador($canvas, $w, $h, $sunData, $whiteColor, $curveColor)
    {
        $baseline = $h * 0.75;
        $amplitude = $h * 0.35;

        // A. Dibujar línea del horizonte (Día completo)
        imagesetthickness($canvas, 2);
        imageline($canvas, 0, $baseline, $w, $baseline, $whiteColor);

        // B. Cálculos de tiempo (24 horas)
        $startOfDay = strtotime("today 00:00");
        $endOfDay = strtotime("today 23:59:59");
        $totalSeconds = $endOfDay - $startOfDay;

        // Mapeo de Amanecer y Ocaso al eje X
        $sunriseX = (($sunData['sunrise_raw'] - $startOfDay) / $totalSeconds) * $w;
        $sunsetX = (($sunData['sunset_raw'] - $startOfDay) / $totalSeconds) * $w;
        $arcWidth = $sunsetX - $sunriseX;

        // C. Dibujar la curva SOLO donde hay sol
        imagesetthickness($canvas, 3);
        $steps = 100;
        for ($i = 0; $i < $steps; $i++) {
            $x1 = $sunriseX + ($i / $steps) * $arcWidth;
            $x2 = $sunriseX + (($i + 1) / $steps) * $arcWidth;

            $y1 = $baseline - sin(($i / $steps) * M_PI) * $amplitude;
            $y2 = $baseline - sin((($i + 1) / $steps) * M_PI) * $amplitude;

            imageline($canvas, (int)$x1, (int)$y1, (int)$x2, (int)$y2, $curveColor);
        }

        // D. Dibujar el "Punto Blanco" (Slider de hora actual)
        $this->drawSliderPoint($canvas, $w, $h, $sunData, $baseline, $amplitude, $startOfDay, $totalSeconds, $whiteColor);
    }

    // Dentro de drawSliderPoint en ImageService.php
    private function drawSliderPoint($canvas, $w, $h, $sunData, $baseline, $amplitude, $startOfDay, $totalSeconds, $color, $type = 'clima')
    {
        // Si es tipo cenit, forzamos el timestamp al tránsito solar exacto
        $now = ($type === 'cenit') ? $sunData['transit_raw'] : time();

        $progressDay = ($now - $startOfDay) / $totalSeconds;
        $pointX = $progressDay * $w;

        if ($now >= $sunData['sunrise_raw'] && $now <= $sunData['sunset_raw']) {
            $dayDuration = $sunData['sunset_raw'] - $sunData['sunrise_raw'];
            $timeInDay = $now - $sunData['sunrise_raw'];
            $progressSun = $timeInDay / $dayDuration;
            $pointY = $baseline - sin($progressSun * M_PI) * $amplitude;
        } else {
            $pointY = $baseline;
        }

        imagefilledellipse($canvas, (int)$pointX, (int)$pointY, 20, 20, $color);
    }


    private function drawTextPill($canvas, $text, $x, $y, $font, $textColor, $bgColor)
    {
        $fw = imagefontwidth($font);
        $fh = imagefontheight($font);
        $tw = strlen($text) * $fw;
        imagefilledrectangle($canvas, $x - 10, $y - 10, $x + $tw + 10, $y + $fh + 10, $bgColor);
        imagestring($canvas, $font, $x, $y, $text, $textColor);
    }
}
