<?php

namespace App\Services;

class UVImageService
{
    protected $width = 1200;
    protected $height = 675;
    protected $margin = 80;

    public function generate(array $historicoUV, string $region): string
    {
        // 1. Crear lienzo y colores
        $img = imagecreatetruecolor($this->width, $this->height);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $transparent);

        // Colores según índice (último valor)
        $ultimoDato = collect($historicoUV)->last();
        $valorActual = (int)$ultimoDato['indiceUV'];
        $colorCurva = $this->getColorForUV($valorActual, $img);
        $white = imagecolorallocate($img, 255, 255, 255);
        $gray = imagecolorallocatealpha($img, 200, 200, 200, 50);

        // 2. Dibujar Ejes (X: Tiempo, Y: Índice UV 0-15)
        // Mapeamos el ancho disponible (1200 - márgenes)
        // Mapeamos el alto (675 - márgenes) para el rango 0 a 15 de UV
        $graphWidth = $this->width - ($this->margin * 2);
        $graphHeight = $this->height - ($this->margin * 2);

        // 3. Dibujar la Curva (Line Chart)
        $puntos = count($historicoUV);
        $prevX = null;
        $prevY = null;

        foreach ($historicoUV as $index => $dato) {
            // Calcular X (distribuido uniformemente)
            $x = $this->margin + ($index * ($graphWidth / ($puntos - 1)));

            // Calcular Y (Invertido: GD 0,0 es arriba izquierda)
            // UV 0 = Base del gráfico, UV 15 = Tope
            $uv = (float)$dato['indiceUV'];
            $y = ($this->height - $this->margin) - ($uv * ($graphHeight / 15));

            if ($prevX !== null) {
                // Dibujar línea gruesa
                imagesetthickness($img, 5);
                imageline($img, $prevX, $prevY, $x, $y, $colorCurva);
            }

            $prevX = $x;
            $prevY = $y;
        }

        // 4. Círculo en el punto actual (el último)
        imagefilledellipse($img, $prevX, $prevY, 20, 20, $colorCurva);

        // 5. Textos (Valor grande y región)
        // Aquí usarías tu lógica de fuentes TTF

        $fileName = "uv_report_{$region}_" . time() . ".png";
        $path = storage_path("app/public/reports/{$fileName}");
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    private function getColorForUV(int $valor, $img) {
        if ($valor <= 2) return imagecolorallocate($img, 46, 204, 113); // Verde
        if ($valor <= 5) return imagecolorallocate($img, 241, 196, 15); // Amarillo
        if ($valor <= 7) return imagecolorallocate($img, 230, 126, 34); // Naranja
        if ($valor <= 10) return imagecolorallocate($img, 231, 76, 60);  // Rojo
        return imagecolorallocate($img, 155, 89, 182); // Púrpura (Extremo)
    }
}
