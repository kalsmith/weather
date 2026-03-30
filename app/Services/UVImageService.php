<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class UVImageService
{
    protected $width = 1200;
    protected $height = 675;
    protected $margin = 110;
    protected $fontPath;

    public function __construct()
    {
        $this->fontPath = public_path('fonts/Roboto-Bold.ttf');
    }

    public function generate(array $historicoUV, string $region): string
    {
        // 1. Directorio
        $directory = storage_path("app/public/reports");
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // 2. Crear lienzo con gradiente de fondo oscuro (Elegante)
        $img = imagecreatetruecolor($this->width, $this->height);
        imagesavealpha($img, true);

        // Definimos gradiente: de negro a un gris azulado muy oscuro
        for ($i = 0; $i < $this->height; $i++) {
            $gradientColor = imagecolorallocate($img, 20 + ($i / $this->height * 10), 23 + ($i / $this->height * 10), 26 + ($i / $this->height * 10));
            imageline($img, 0, $i, $this->width, $i, $gradientColor);
        }

        // 3. Colores y Configuración
        $ultimoDato = collect($historicoUV)->last();
        $valorActual = (int)($ultimoDato['indiceUV'] ?? 0);
        $colorCurva = $this->getColorForUV($valorActual, $img);
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocatealpha($img, 255, 255, 255, 127); // Transparente para líneas de guía
        $subtleGray = imagecolorallocatealpha($img, 150, 150, 150, 40); // Muy sutil para fondo

        // Parámetros de la gráfica
        $graphWidth = $this->width - ($this->margin * 2);
        $graphHeight = $this->height - ($this->margin * 2);
        $maxUVScale = 12; // Eje Y tope visual para que la curva suba

        // 4. Dibujar Rejilla y Guías de Color
        imagesetthickness($img, 1);
        for ($i = 0; $i <= $maxUVScale; $i += 3) {
            $yLine = ($this->height - $this->margin) - ($i * ($graphHeight / $maxUVScale));
            imageline($img, $this->margin, $yLine, $this->width - $this->margin, $yLine, $subtleGray);

            // Texto del eje Y (0, 3, 6, 9)
            if (file_exists($this->fontPath)) {
                imagettftext($img, 14, 0, $this->margin - 40, $yLine + 7, $white, $this->fontPath, $i);
            }
        }

        // 5. Dibujar la "Curva" de Puntos (Mejora estética radical)
        $puntos = count($historicoUV);
        $prevX = null;
        $prevY = null;

        // Parámetros de dibujo: Radio del punto y grosor de línea
        $dotRadius = 10;
        $lineThickness = 5;
        imagesetthickness($img, $lineThickness);

        foreach ($historicoUV as $index => $dato) {
            // Calcular X
            $x = $this->margin + ($index * ($graphWidth / ($puntos - 1)));
            // Calcular Y
            $uv = (float)$dato['indiceUV'];
            $y = ($this->height - $this->margin) - ($uv * ($graphHeight / $maxUVScale));

            // Dibujamos el punto de datos como un círculo sólido (más limpio que una línea sola)
            $colorPunto = $this->getColorForUV((int)$uv, $img);
            imagefilledellipse($img, $x, $y, $dotRadius, $dotRadius, $colorPunto);

            // Si queremos, podemos dibujar una línea MUY delgada y gris entre puntos
            // (Comentado para diseño más limpio, pero si lo prefieres, descomenta el bloque if ($prevX !== null))
            /*
            if ($prevX !== null) {
                imagesetthickness($img, 1);
                imageline($img, $prevX, $prevY, $x, $y, $subtleGray);
                imagesetthickness($img, $lineThickness);
            }
            */

            $prevX = $x;
            $prevY = $y;
        }

        // 6. Títulos y Composición
        if (file_exists($this->fontPath)) {
            // Corregimos Título y Región
            imagettftext($img, 28, 0, $this->margin, 60, $white, $this->fontPath, "Radiación UV: Evolución del Día");
            imagettftext($img, 16, 0, $this->margin, 90, $subtleGray, $this->fontPath, "Región de " . ($region == 'STGO' ? 'Santiago' : 'Antofagasta'));

            // Valor Actual Gigante (Reorganizado)
            $textStartX = $this->width - 250;
            $textStartY = 160;
            imagettftext($img, 100, 0, $textStartX, $textStartY, $colorCurva, $this->fontPath, $valorActual);

            $riesgoText = $ultimoDato['riesgo'] ?? 'N/A';
            imagettftext($img, 18, 0, $textStartX + 10, $textStartY + 40, $white, $this->fontPath, "ÍNDICE ACTUAL");
            imagettftext($img, 18, 0, $textStartX + 10, $textStartY + 70, $colorCurva, $this->fontPath, $riesgoText);

            // Footer (Hora de actualización)
            $footerText = "Última lectura: " . ($ultimoDato['hora'] ?? now()->format('H:i')) . " hrs";
            imagettftext($img, 12, 0, $this->width / 2 - 100, $this->height - 40, $gray, $this->fontPath, $footerText);
        }

        // 7. Guardado
        $fileName = "uv_design_{$region}_" . time() . ".png";
        $path = $directory . "/" . $fileName;

        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    private function getColorForUV(int $valor, $img) {
        if ($valor <= 2) return imagecolorallocate($img, 46, 204, 113); // Verde
        if ($valor <= 5) return imagecolorallocate($img, 241, 196, 15); // Amarillo
        if ($valor <= 7) return imagecolorallocate($img, 230, 126, 34); // Naranja
        if ($valor <= 10) return imagecolorallocate($img, 231, 76, 60); // Rojo
        return imagecolorallocate($img, 155, 89, 182); // Púrpura (Extremo)
    }
}
