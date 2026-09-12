<?php

declare(strict_types=1);

namespace App\Services\Pos;

use GdImage;
use RuntimeException;

/**
 * Turns a shop's logo into something a thermal head can actually print.
 *
 * A receipt printer has no greys and no colour: every dot is burned or it is
 * not. Handing it a normal logo — light artwork on a coloured field, say —
 * thresholds the *field* to black and knocks the artwork out of it, so the slip
 * opens with a solid block that soaks heat, slows the cut and looks nothing like
 * the brand.
 *
 * So the logo is converted once, on upload, and stored ready to print: scaled to
 * the head, reduced to one bit, and flipped if it came out mostly black.
 */
final class ReceiptLogo
{
    /**
     * Dots across. Deliberately narrower than the 576-dot head: a logo that runs
     * edge to edge leaves no margin and reads as a printing fault.
     */
    private const WIDTH = 360;

    /** Dots down. A taller logo is scaled to fit — a receipt is not a poster. */
    private const MAX_HEIGHT = 240;

    /**
     * @param  string  $image  Raw uploaded bytes.
     * @return string A `data:image/png;base64,…` URI, ready to drop in an <img>.
     *
     * @throws RuntimeException when the upload is not a readable image.
     */
    public static function prepare(string $image): string
    {
        $source = @imagecreatefromstring($image);

        if ($source === false) {
            throw new RuntimeException('That file could not be read as an image.');
        }

        $canvas = self::fit($source);
        imagedestroy($source);

        self::monochrome($canvas);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /** Scale into the receipt's box, flattened onto white so alpha cannot burn. */
    private static function fit(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(self::WIDTH / $width, self::MAX_HEIGHT / $height, 1);

        $target = [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];

        $canvas = imagecreatetruecolor(...$target);
        imagefilledrectangle($canvas, 0, 0, $target[0], $target[1], imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $target[0], $target[1], $width, $height);

        return $canvas;
    }

    /**
     * One bit per dot, and the right way round.
     *
     * Which way round cannot be assumed from the source: a logo may arrive as
     * dark marks on white or light marks on colour, and both are normal. What is
     * *not* normal on a receipt is a mostly-black image — paper is the light part.
     * So the coverage decides: if thresholding leaves more than half the box
     * burned, the image is inverted.
     */
    private static function monochrome(GdImage $canvas): void
    {
        $width = imagesx($canvas);
        $height = imagesy($canvas);

        // First pass decides which way round the image is; second pass writes it.
        // Counting before writing avoids holding a coordinate list for what can
        // be ninety thousand pixels.
        $burned = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (self::isDark($canvas, $x, $y)) {
                    $burned++;
                }
            }
        }

        $invert = $burned * 2 > $width * $height;
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $white = imagecolorallocate($canvas, 255, 255, 255);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $dark = self::isDark($canvas, $x, $y);

                imagesetpixel($canvas, $x, $y, $dark === $invert ? $white : $black);
            }
        }
    }

    private static function isDark(GdImage $canvas, int $x, int $y): bool
    {
        $rgb = imagecolorat($canvas, $x, $y);

        return ((($rgb >> 16) & 0xFF) * 299 + (($rgb >> 8) & 0xFF) * 587 + ($rgb & 0xFF) * 114) / 1000 <= 160;
    }
}
