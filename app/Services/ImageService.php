<?php

namespace App\Services;

class ImageService
{
    /**
     * Genera la imagen del reporte meteorológico/astronómico.
     */
    public function generate(string $region, string $temp, ?array $moonData): string
    {
        $region = strtoupper($region);

        // 1. Configuración de dimensiones y rutas
        $width = 800;
        $height = 600;
        $backgroundPath = public_path("assets/backgrounds/fondo_{$region}.png");

        if (!file_exists($backgroundPath)) {
            // Fallback por si no encuentra el fondo específico
            $backgroundPath = public_path("assets/backgrounds/fondo_STGO.png");
        }

        // 2. Crear Lienzo y Cargar Fondo
        $background = imagecreatefrompng($backgroundPath);
        $canvas = imagecreatetruecolor($width, $height);

        // Preservar transparencia (Crucial para PNGs)
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        // Redimensionar/Copiar fondo al lienzo
        imagecopyresampled(
            $canvas, $background,
            0, 0, 0, 0,
            $width, $height,
            imagesx($background), imagesy($background)
        );
        imagedestroy($background);

        // 3. Definir Colores
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $yellow = imagecolorallocate($canvas, 255, 215, 0);
        $boxColor = imagecolorallocatealpha($canvas, 255, 255, 255, 40); // Blanco semitransparente

        // 4. Dibujar Datos del Sol (Cálculo Nativo de PHP)
        $coords = $this->getCoords($region);
        $sunInfo = date_sun_info(time(), $coords['lat'], $coords['lon']);

        $this->drawSolarCurve($canvas, $width, $height, $yellow, $sunInfo);

        // 5. Dibujar Cuadros de Texto e Info
        // Cuadro Temperatura
        imagefilledrectangle($canvas, 20, 20, 280, 80, $boxColor);
        imagestring($canvas, 5, 40, 40, "Temp: {$temp} C", $black);

        // Datos de la Luna (si existen)
        if ($moonData) {
            $ilum = round($moonData['iluminacion_pct'] ?? 0, 1);
            imagefilledrectangle($canvas, $width - 250, 20, $width - 20, 80, $boxColor);
            imagestring($canvas, 5, $width - 230, 40, "Luna: {$ilum}%", $black);
        }

        // 6. Guardar y Retornar Ruta
        $fileName = "reporte_{$region}_" . time() . ".png";
        $savePath = storage_path("app/public/reports/{$fileName}");

        if (!is_dir(storage_path("app/public/reports"))) {
            mkdir(storage_path("app/public/reports"), 0755, true);
        }

        imagepng($canvas, $savePath);
        imagedestroy($canvas);

        return $savePath;
    }

    private function drawSolarCurve($canvas, $w, $h, $color, $sunInfo)
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

    private function getCoords(string $region): array
    {
        $data = [
            'STGO'  => ['lat' => -33.4489, 'lon' => -70.6693],
            'ANTOF' => ['lat' => -23.65,   'lon' => -70.4],
        ];
        return $data[$region] ?? $data['STGO'];
    }
}
