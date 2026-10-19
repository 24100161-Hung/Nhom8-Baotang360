<?php

namespace Database\Seeders\Support;

/**
 * Sinh ảnh equirectangular mô phỏng (JPEG 2:1, gradient + lưới + tên phòng) bằng GD
 * cho phòng chưa có ảnh 360 thật; được đánh dấu is_simulated = true, license simulated.
 */
class SimulatedPanorama
{
    public static function jpeg(string $title, int $seed = 1, int $width = 2048): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }
        $height = intdiv($width, 2);
        $img = imagecreatetruecolor($width, $height);
        [$r, $g, $b] = [40 + ($seed * 37) % 90, 60 + ($seed * 53) % 80, 90 + ($seed * 29) % 100];
        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $color = imagecolorallocate($img, (int) ($r * (1 - $t) + 15 * $t), (int) ($g * (1 - $t) + 15 * $t), (int) ($b * (1 - $t) + 25 * $t));
            imageline($img, 0, $y, $width, $y, $color);
        }
        $grid = imagecolorallocatealpha($img, 255, 255, 255, 100);
        for ($x = 0; $x < $width; $x += intdiv($width, 16)) {
            imageline($img, $x, 0, $x, $height, $grid);
        }
        imageline($img, 0, intdiv($height, 2), $width, intdiv($height, 2), $grid);
        $white = imagecolorallocate($img, 255, 255, 255);
        foreach ([0, 1, 2, 3] as $i) {
            imagestring($img, 5, (int) ($i * $width / 4 + 40), intdiv($height, 2) - 20, self::ascii($title).' (BaoTang360 simulated)', $white);
        }
        ob_start();
        imagejpeg($img, null, 82);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private static function ascii(string $text): string
    {
        return (string) preg_replace('/[^\x20-\x7E]/', '', \Illuminate\Support\Str::ascii($text));
    }
}
