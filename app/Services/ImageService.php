<?php

namespace App\Services;

class ImageService
{
    public function generate(string $region, string $temp, ?array $moonData, array $sunData, string $type = 'clima'): string
    {
        $region = strtoupper($region);
        $width = 800; // Mantenemos formato cuadrado para X
        $height = 800;

        // 1. Lógica de fondo y estado (Prioridad absoluta al tipo de evento)
        if ($type === 'sunrise') {
            $isNight = false; // Forza fondo claro
        } elseif ($type === 'sunset') {
            $isNight = true;  // Forza fondo oscuro
        } else {
            // Reporte estándar de clima
            $now = time();
            $isNight = ($now > $sunData['sunset_raw'] || $now < $sunData['sunrise_raw']);
        }

        $backgroundName = $isNight ? "moon_{$region}.png" : "fondo_{$region}.png";
        $backgroundPath = public_path("assets/backgrounds/{$backgroundName}");

        // 2. Crear Lienzo
        $background = imagecreatefrompng($backgroundPath);
        $canvas = imagecreatetruecolor($width, $height);

        // Cargar y redimensionar el fondo cuadrado (800x800)
        imagecopyresampled($canvas, $background, 0, 0, 0, 0, $width, $height, imagesx($background), imagesy($background));
        imagedestroy($background);

        // 3. Colores
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $yellow = imagecolorallocate($canvas, 255, 215, 0);
        $gray = imagecolorallocate($canvas, 200, 200, 200); // Color sutil para la curva

        // Píldora de fondo más sutil y limpia (alpha 50)
        $pillBg = imagecolorallocatealpha($canvas, 255, 255, 255, 50);

        // 4. EL GRAFICADOR DE HORIZONTE FIJO (Trigonometría Nivel 2)
        $this->drawHorizonGraficador($canvas, $width, $height, $sunData, $white, $gray);

        // 5. Cuadros Superiores con Píldora Limpia
        $this->drawTextPill($canvas, "TEMP: {$temp} C", 20, 30, 5, $black, $pillBg);
        if ($moonData) {
            $ilum = round($moonData['iluminacion_pct'] ?? 0, 1);
            $this->drawTextPill($canvas, "LUNA: {$ilum}%", $width - 180, 30, 5, $black, $pillBg);
        }

        // 6. Textos Inferiores Protegidos con Píldora (Legibilidad Total)
        $yPos = $height - 80;
        if ($type === 'sunrise') {
            // Layout de Mañana: Información completa del día
            $this->drawTextPill($canvas, "Amanecer: " . $sunData['sunrise'], 40, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Cenit: " . $sunData['transit'], ($width / 2) - 60, $yPos, 4, $black, $pillBg);
            $this->drawTextPill($canvas, "Ocaso: " . $sunData['sunset'], $width - 200, $yPos, 4, $black, $pillBg);
        } else {
            // Layout Nocturno/Ocaso
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
        $steps = 200;
        $baseline = $h * 0.75; // Línea fija del horizonte (75% de la altura)
        $amplitude = $h * 0.35; // Altura máxima del arco solar

        // A. Dibujar la línea del horizonte
        imagesetthickness($canvas, 2);
        imageline($canvas, 0, $baseline, $w, $baseline, $whiteColor);

        // B. Dibujar el Arco Solar Completo (Curva Sinusoidal Gris)
        // La curva representa la trayectoria completa del sol
        imagesetthickness($canvas, 3);
        for ($i = 0; $i < $steps; $i++) {
            $x1 = ($i / $steps) * $w;
            $x2 = (($i + 1) / $steps) * $w;

            // Función seno para crear el arco
            $y1 = $baseline - sin(($i / $steps) * M_PI) * $amplitude;
            $y2 = $baseline - sin((($i + 1) / $steps) * M_PI) * $amplitude;

            // Usamos un color gris para que el punto blanco destaque
            imageline($canvas, $x1, $y1, $x2, $y2, $curveColor);
        }

        // C. Calcular y dibujar el "Punto Blanco" (Slider)
        $this->drawSliderPoint($canvas, $w, $h, $sunData, $baseline, $amplitude, $whiteColor);
    }

    private function drawSliderPoint($canvas, $w, $h, $sunData, $baseline, $amplitude, $color)
    {
        $now = time();
        $start = $sunData['sunrise_raw']; // Timestamp Amanecer
        $end = $sunData['sunset_raw'];   // Timestamp Ocaso

        // Calcular el progreso del día (0 a 1)
        $dayDuration = $end - $start;
        $timeElapsed = $now - $start;
        $progress = $timeElapsed / $dayDuration;

        // Limitar progreso entre 0 y 1 para que el punto no se escape
        $progress = max(0, min(1, $progress));

        // Calcular posición X del punto blanco
        $pointX = $progress * $w;

        // Calcular posición Y del punto blanco (basada en la curva)
        $pointY = $baseline - sin($progress * M_PI) * $amplitude;

        // Dibujar el Punto Blanco (un círculo relleno)
        imagefilledellipse($canvas, $pointX, $pointY, 20, 20, $color);
    }

    /**
     * Dibuja una píldora de texto con padding limpio.
     */
    private function drawTextPill($canvas, $text, $x, $y, $font, $textColor, $bgColor)
    {
        $fw = imagefontwidth($font);
        $fh = imagefontheight($font);
        $tw = strlen($text) * $fw;

        // Píldora limpia
        imagefilledrectangle($canvas, $x - 10, $y - 10, $x + $tw + 10, $y + $fh + 10, $bgColor);

        imagestring($canvas, $font, $x, $y, $text, $textColor);
    }
}
