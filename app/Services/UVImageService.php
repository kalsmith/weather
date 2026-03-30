<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class UVImageService
{
    protected $width = 1200;
    protected $height = 675;
    protected $margin = 120;
    protected $fontPath;

    public function __construct()
    {
        $this->fontPath = public_path('fonts/Roboto-Bold.ttf');
    }

    public function generate(array $historicoUV, string $region): string
    {
        $directory = storage_path("app/public/reports");
        if (!File::exists($directory)) File::makeDirectory($directory, 0755, true);

        $img = imagecreatetruecolor($this->width, $this->height);
        imagesavealpha($img, true);

        // Fondo Oscuro Profundo
        $bgColor = imagecolorallocate($img, 15, 15, 18);
        imagefill($img, 0, 0, $bgColor);

        // Tomar solo los últimos 24-48 puntos para evitar el amontonamiento de días
        $datos = collect($historicoUV)->take(-48)->values();
        $ultimoDato = $datos->last();
        $valorActual = (float)($ultimoDato['indiceUV'] ?? 0);
        $colorPrincipal = $this->getColorForUV((int)$valorActual, $img);
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocatealpha($img, 200, 200, 200, 50);

        $graphWidth = $this->width - ($this->margin * 2);
        $graphHeight = $this->height - ($this->margin * 2.5);

        // 1. Dibujar Área Sombreada (Fill)
        $puntos = count($datos);
        if ($puntos > 1) {
            $polyPoints = [];
            // Punto inicial (base)
            $polyPoints[] = $this->margin;
            $polyPoints[] = $this->height - $this->margin;

            foreach ($datos as $index => $dato) {
                $x = $this->margin + ($index * ($graphWidth / ($puntos - 1)));
                $uv = (float)$dato['indiceUV'];
                $y = ($this->height - $this->margin) - ($uv * ($graphHeight / 12));
                $polyPoints[] = $x;
                $polyPoints[] = $y;
            }

            // Punto final (base)
            $polyPoints[] = $this->margin + $graphWidth;
            $polyPoints[] = $this->height - $this->margin;

            $fillColor = imagecolorallocatealpha($img,
                $this->getRgb($valorActual)[0],
                $this->getRgb($valorActual)[1],
                $this->getRgb($valorActual)[2],
                90);
            imagefilledpolygon($img, $polyPoints, count($polyPoints) / 2, $fillColor);

            // 2. Dibujar Línea Superior Gruesa
            imagesetthickness($img, 8);
            $prevX = null; $prevY = null;
            foreach ($datos as $index => $dato) {
                $x = $this->margin + ($index * ($graphWidth / ($puntos - 1)));
                $uv = (float)$dato['indiceUV'];
                $y = ($this->height - $this->margin) - ($uv * ($graphHeight / 12));
                if ($prevX !== null) imageline($img, $prevX, $prevY, $x, $y, $colorPrincipal);
                $prevX = $x; $prevY = $y;
            }
        }

        // 3. Textos e Info
        if (file_exists($this->fontPath)) {
            // Título Superior Izquierda
            imagettftext($img, 24, 0, $this->margin, 80, $white, $this->fontPath, "Índice UV en " . ($region == 'STGO' ? 'Santiago' : 'Antofagasta'));

            // Valor Gigante Derecha
            imagettftext($img, 140, 0, $this->width - 320, 220, $colorPrincipal, $this->fontPath, round($valorActual, 1));
            imagettftext($img, 20, 0, $this->width - 290, 260, $white, $this->fontPath, "NIVEL ACTUAL");

            // Etiquetas de tiempo (Inicio y Fin del gráfico)
            imagettftext($img, 14, 0, $this->margin, $this->height - $this->margin + 40, $gray, $this->fontPath, $datos->first()['hora'] ?? '');
            imagettftext($img, 14, 0, $this->width - $this->margin - 50, $this->height - $this->margin + 40, $gray, $this->fontPath, $datos->last()['hora'] ?? '');
        }

        $fileName = "uv_clean_{$region}_" . time() . ".png";
        $path = $directory . "/" . $fileName;
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    private function getRgb($valor) {
        if ($valor <= 2) return [46, 204, 113];
        if ($valor <= 5) return [241, 196, 15];
        if ($valor <= 7) return [230, 126, 34];
        if ($valor <= 10) return [231, 76, 60];
        return [155, 89, 182];
    }

    private function getColorForUV(int $valor, $img) {
        $rgb = $this->getRgb($valor);
        return imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    }
}
