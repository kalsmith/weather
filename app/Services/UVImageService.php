<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class UVImageService
{
    protected $width = 1200;
    protected $height = 675;
    protected $margin = 100;
    protected $fontPath;

    public function __construct()
    {
        // Ruta a una fuente TTF (asegúrate de tenerla en esa ruta o cámbiala)
        $this->fontPath = public_path('fonts/Roboto-Bold.ttf');
    }

    public function generate(array $historicoUV, string $region): string
    {
        // 1. Asegurar que el directorio existe
        $directory = storage_path("app/public/reports");
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // 2. Crear lienzo con fondo oscuro elegante
        $img = imagecreatetruecolor($this->width, $this->height);
        imagesavealpha($img, true);

        $bgColor = imagecolorallocate($img, 20, 23, 26); // Fondo estilo "Dark Mode" de X
        imagefill($img, 0, 0, $bgColor);

        // Colores
        $ultimoDato = collect($historicoUV)->last();
        $valorActual = (int)($ultimoDato['indiceUV'] ?? 0);
        $colorCurva = $this->getColorForUV($valorActual, $img);
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocatealpha($img, 255, 255, 255, 80); // Líneas de guía

        $graphWidth = $this->width - ($this->margin * 2);
        $graphHeight = $this->height - ($this->margin * 2);

        // 3. Dibujar Rejilla de Referencia (Líneas horizontales)
        imagesetthickness($img, 1);
        for ($i = 0; $i <= 11; $i += 3) {
            $yLine = ($this->height - $this->margin) - ($i * ($graphHeight / 15));
            imageline($img, $this->margin, $yLine, $this->width - $this->margin, $yLine, $gray);
            if (file_exists($this->fontPath)) {
                imagettftext($img, 12, 0, $this->margin - 30, $yLine + 5, $white, $this->fontPath, $i);
            }
        }

        // 4. Dibujar la Curva
        $puntos = count($historicoUV);
        if ($puntos > 1) {
            $prevX = null;
            $prevY = null;
            imagesetthickness($img, 6);

            foreach ($historicoUV as $index => $dato) {
                $x = $this->margin + ($index * ($graphWidth / ($puntos - 1)));
                $uv = (float)$dato['indiceUV'];
                $y = ($this->height - $this->margin) - ($uv * ($graphHeight / 15));

                if ($prevX !== null) {
                    imageline($img, $prevX, $prevY, $x, $y, $colorCurva);
                }
                $prevX = $x;
                $prevY = $y;
            }

            // Punto final destacado
            imagefilledellipse($img, $prevX, $prevY, 24, 24, $white);
            imagefilledellipse($img, $prevX, $prevY, 16, 16, $colorCurva);
        }

        // 5. Títulos y Textos
        if (file_exists($this->fontPath)) {
            // Región y Título
            imagettftext($img, 25, 0, $this->margin, 60, $white, $this->fontPath, "Índice UV Histórico: " . ($region == 'STGO' ? 'Santiago' : 'Antofagasta'));

            // Valor Actual Gigante
            imagettftext($img, 80, 0, $this->width - 250, 150, $colorCurva, $this->fontPath, $valorActual);
            imagettftext($img, 15, 0, $this->width - 245, 180, $white, $this->fontPath, "ÍNDICE ACTUAL");

            // Fecha/Hora
            $footerText = "Última actualización: " . ($ultimoDato['hora'] ?? now()->format('H:i')) . " hrs";
            imagettftext($img, 12, 0, $this->margin, $this->height - 40, $gray, $this->fontPath, $footerText);
        }

        // 6. Guardado
        $fileName = "uv_{$region}_" . time() . ".png";
        $path = $directory . "/" . $fileName;

        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    private function getColorForUV(int $valor, $img) {
        if ($valor <= 2) return imagecolorallocate($img, 46, 204, 113); // Bajo - Verde
        if ($valor <= 5) return imagecolorallocate($img, 241, 196, 15); // Moderado - Amarillo
        if ($valor <= 7) return imagecolorallocate($img, 230, 126, 34); // Alto - Naranja
        if ($valor <= 10) return imagecolorallocate($img, 231, 76, 60); // Muy Alto - Rojo
        return imagecolorallocate($img, 155, 89, 182); // Extremo - Púrpura
    }
}
