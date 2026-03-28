<?php

namespace App\Services;

class ImageService
{
    public function generate(string $region, string $temp, ?array $moonData, array $sunData, string $type = 'clima'): string
    {
        $region = strtoupper($region);
        $width = 800;
        $height = 800; // Formato cuadrado para mejor visibilidad en X

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
        $yellow = imagecolorallocate($canvas, 255, 215, 0);
        $pillBg = imagecolorallocatealpha($canvas, 255, 255, 255, 45); // Fondo blanco suave

        // 4. LA PARÁBOLA DINÁMICA (Trigonometría aplicada)
        $this->drawSmartCurve($canvas, $width, $height, $isNight ? $white : $yellow, $isNight);

        // 5. Cuadros Superiores con Píldora
        $this->drawTextPill($canvas, "TEMP: {$temp} C", 20, 30, 5, $black, $pillBg);
        if ($moonData) {
            $ilum = round($moonData['iluminacion_pct'] ?? 0, 1);
            $this->drawTextPill($canvas, "LUNA: {$ilum}%", $width - 180, 30, 5, $black, $pillBg);
        }

        // 6. Textos Inferiores con Píldora (Legibilidad 100%)
        $yPos = $height - 80;
        if ($type === 'sunrise') {
            $this->drawTextPill($canvas, "Amanecer: " . $sunData['sunrise'], 40, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Cenit: " . $sunData['transit'], ($width/2)-60, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Ocaso: " . $sunData['sunset'], $width-200, $yPos, 4, $black, $pillBg);
        } else {
            $fase = $moonData['fase_nombre'] ?? 'Luna';
            $this->drawTextPill($canvas, "Fase: " . $fase, 40, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Ocaso: " . $sunData['sunset'], ($width/2)-60, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Cielo Nocturno", $width-200, $yPos, 4, $black, $pillBg);
        }

        // 7. Guardar
        $fileName = "reporte_{$region}_" . time() . ".png";
        $savePath = storage_path("app/public/reports/{$fileName}");
        imagepng($canvas, $savePath);
        imagedestroy($canvas);

        return $savePath;
    }

    private function drawSmartCurve($canvas, $w, $h, $color, $isNight)
    {
        $steps = 200;
        $baseline = $h * 0.75; // Línea del horizonte

        for ($i = 0; $i < $steps; $i++) {
            $x1 = ($i / $steps) * $w;
            $x2 = (($i + 1) / $steps) * $w;

            if ($isNight) {
                // Noche: Línea plana en el horizonte
                imageline($canvas, $x1, $baseline, $x2, $baseline, $color);
            } else {
                // Día: Parábola de arco real usando Seno
                $y1 = $baseline - sin(($i / $steps) * M_PI) * ($h * 0.35);
                $y2 = $baseline - sin((($i + 1) / $steps) * M_PI) * ($h * 0.35);
                imageline($canvas, $x1, $y1, $x2, $y2, $color);
            }
        }
    }

    private function drawTextPill($canvas, $text, $x, $y, $font, $textColor, $bgColor)
    {
        $fw = imagefontwidth($font);
        $fh = imagefontheight($font);
        $tw = strlen($text) * $fw;

        // Dibujar el fondo de la píldora
        imagefilledrectangle($canvas, $x-10, $y-10, $x+$tw+10, $y+$fh+10, $bgColor);
        // Dibujar el texto
        imagestring($canvas, $font, $x, $y, $text, $textColor);
    }
}
