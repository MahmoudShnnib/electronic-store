<?php

declare(strict_types=1);

namespace App\Services\Pos;

use GdImage;
use RuntimeException;

/**
 * A receipt bitmap in the one shape a thermal head understands: one bit per
 * dot, packed eight to a byte, left to right, top to bottom, 1 = burn.
 *
 * The image arrives as a PNG the browser rasterised from the receipt markup.
 * Doing the typesetting there rather than here is what makes Arabic work: the
 * browser already shapes, joins and bidi-orders the text, so the printer never
 * has to be told about a code page and the slip is guaranteed to match what the
 * cashier saw on screen.
 */
final class Raster
{
    private function __construct(
        public readonly int $width,
        public readonly int $height,
        /** Packed rows, `width / 8` bytes each. */
        public readonly string $bits,
    ) {}

    /**
     * @param  string  $png  Raw image bytes (any format GD can read).
     * @param  int  $dotWidth  Printable dots across the head; must be a multiple of 8.
     * @param  int  $threshold  Luminance at or below which a dot is burned.
     */
    public static function fromImage(string $png, int $dotWidth, int $threshold = 160): self
    {
        if ($dotWidth <= 0 || $dotWidth % 8 !== 0) {
            throw new RuntimeException("Printer width must be a positive multiple of 8, got {$dotWidth}.");
        }

        $source = @imagecreatefromstring($png);

        if ($source === false) {
            throw new RuntimeException('The receipt image could not be decoded.');
        }

        $canvas = self::flattenOntoWhite($source, $dotWidth);
        imagedestroy($source);

        $height = imagesy($canvas);
        $bytesPerRow = intdiv($dotWidth, 8);
        $bits = '';

        for ($y = 0; $y < $height; $y++) {
            $row = array_fill(0, $bytesPerRow, 0);

            for ($x = 0; $x < $dotWidth; $x++) {
                $rgb = imagecolorat($canvas, $x, $y);
                $luma = (int) (((($rgb >> 16) & 0xFF) * 299 + (($rgb >> 8) & 0xFF) * 587 + ($rgb & 0xFF) * 114) / 1000);

                if ($luma <= $threshold) {
                    $row[$x >> 3] |= 0x80 >> ($x & 7);
                }
            }

            $bits .= pack('C*', ...$row);
        }

        imagedestroy($canvas);

        return new self($dotWidth, $height, $bits);
    }

    /** Bytes in one packed row. */
    public function bytesPerRow(): int
    {
        return intdiv($this->width, 8);
    }

    /**
     * The packed rows of one horizontal band, for feeding the head in slices
     * rather than one buffer-busting blob.
     */
    public function band(int $firstRow, int $rows): string
    {
        return substr($this->bits, $firstRow * $this->bytesPerRow(), $rows * $this->bytesPerRow());
    }

    /**
     * Scale to the head's width and drop the alpha channel against white.
     *
     * A PNG from a canvas is truecolour with alpha; leaving it composited over
     * nothing would threshold every transparent pixel to black and burn a solid
     * slip. Narrower images are centred rather than stretched — a 58mm slip on
     * an 80mm roll should look narrow, not blurry.
     */
    private static function flattenOntoWhite(GdImage $source, int $dotWidth): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width > $dotWidth) {
            $height = max(1, (int) round($height * $dotWidth / $width));
            $width = $dotWidth;
        }

        $canvas = imagecreatetruecolor($dotWidth, $height);
        imagefilledrectangle($canvas, 0, 0, $dotWidth, $height, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, intdiv($dotWidth - $width, 2), 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $canvas;
    }
}
