<?php

namespace herosphp\plugins\captcha;

use AllowDynamicProperties;

#[AllowDynamicProperties]
class CaptchaBuilder
{
    /**
     * @var array
     */
    protected $fingerprint = [];

    /**
     * @var array
     */
    protected $lineColor = null;

    /**
     * @var resource
     */
    protected $contents = null;

    /**
     * @var string
     */
    protected $phrase = null;

    /**
     * @var PhraseBuilder
     */
    protected $builder;

    /**
     * @var bool
     */
    protected $distortion = true;

    /**
     * The maximum number of lines to draw in front of
     * the image. null - use default algorithm
     */
    protected $maxFrontLines = null;

    /**
     * The maximum number of lines to draw behind
     * the image. null - use default algorithm
     */
    protected $maxBehindLines = null;

    /**
     * The maximum angle of char
     */
    protected int $maxAngle = 8;

    /**
     * The maximum offset of char
     */
    protected int $maxOffset = 5;

    /**
     * The image contents
     */
    public function getContents()
    {
        return $this->contents;
    }

    public function __construct($length = null, $charset = null)
    {
        $this->builder = new PhraseBuilder;
        $this->phrase = $this->builder->build($length, $charset);
    }

    /**
     * Gets the captcha phrase
     */
    public function getPhrase()
    {
        return $this->phrase;
    }

    /**
     * Draw lines over the image
     */
    protected function drawLine($image, $width, $height, $tcol = null): void
    {
        $red = $this->rand(200, 255);
        $green = $this->rand(200, 255);
        $blue = $this->rand(200, 255);
        if ($tcol === null) {
            $tcol = imagecolorallocate($image, $red, $green, $blue);
        }
        if ($this->rand(0, 1)) { // Horizontal
            $Xa = $this->rand(0, $width / 2);
            $Ya = $this->rand(0, $height);
            $Xb = $this->rand($width / 2, $width);
            $Yb = $this->rand(0, $height);
        } else { // Vertical
            $Xa = $this->rand(0, $width);
            $Ya = $this->rand(0, $height / 2);
            $Xb = $this->rand(0, $width);
            $Yb = $this->rand($height / 2, $height);
        }
        imagesetthickness($image, $this->rand(1, 3));
        imageline($image, $Xa, $Ya, $Xb, $Yb, $tcol);
    }

    /**
     * Apply some post effects
     */
    protected function postEffect($image)
    {
        if (! function_exists('imagefilter')) {
            return;
        }
        // Contrast
        imagefilter($image, IMG_FILTER_CONTRAST, $this->rand(-50, 10));
        // Colorize
        if ($this->rand(0, 5) == 0) {
            imagefilter($image, IMG_FILTER_COLORIZE, $this->rand(-30, 50), $this->rand(-30, 50), $this->rand(-30, 50));
        }
    }

    /**
     * Writes the phrase on the image
     */
    protected function writePhrase($image, $phrase, $font, $width, $height)
    {
        $length = mb_strlen($phrase);
        if ($length === 0) {
            return \imagecolorallocate($image, 0, 0, 0);
        }

        // Gets the text size and start position
        $size = intval($width / $length) - $this->rand(0, 3) - 1;
        $box = \imagettfbbox($size, 0, $font, $phrase);
        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        $x = intval(($width - $textWidth) / 2);
        $y = intval(($height - $textHeight) / 2) + $size;

        $textColor = [$this->rand(0, 150), $this->rand(0, 150), $this->rand(0, 150)];

        $col = \imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);

        // Write the letters one by one, with random angle
        for ($i = 0; $i < $length; $i++) {
            $symbol = mb_substr($phrase, $i, 1);
            $box = \imagettfbbox($size, 0, $font, $symbol);
            $w = $box[2] - $box[0];
            $angle = $this->rand(-$this->maxAngle, $this->maxAngle);
            $offset = $this->rand(-$this->maxOffset, $this->maxOffset);
            \imagettftext($image, $size, $angle, $x, $y + $offset, $col, $font, $symbol);
            $x += $w;
        }

        return $col;
    }

    /**
     * Generate the image
     */
    public function build($width = 150, $height = 40): static
    {
        $this->fingerprint = [];

        $font = $this->getFontPath(__DIR__.'/Font/captcha'.$this->rand(0, 5).'.ttf');

        // if background images list is not set, use a color fill as a background
        $image = imagecreatetruecolor($width, $height);

        $bg = imagecolorallocate($image, 255, 252, 255);
        imagefill($image, 0, 0, $bg);

        $square = $width * $height;
        $effects = $this->rand($square / 3000, $square / 2000);
        for ($e = 0; $e < $effects; $e++) {
            $this->drawLine($image, $width, $height);
        }

        // Write CAPTCHA text
        $color = $this->writePhrase($image, $this->phrase, $font, $width, $height);

        // Apply effects

        $square = $width * $height;
        $effects = $this->rand($square / 3000, $square / 2000);

        // set the maximum number of lines to draw in front of the text
        if ($this->maxFrontLines != null && $this->maxFrontLines > 0) {
            $effects = min($this->maxFrontLines, $effects);
        }

        if ($this->maxFrontLines !== 0) {
            for ($e = 0; $e < $effects; $e++) {
                $this->drawLine($image, $width, $height, $color);
            }
        }

        // Distort the image
        if ($this->distortion) {
            $image = $this->distort($image, $width, $height, $bg);
        }

        // Post effects
        $this->postEffect($image);
        $this->contents = $image;

        return $this;
    }

    /**
     * @param $font
     * @return string
     */
    protected function getFontPath($font): string
    {
        static $fontPathMap = [];
        if (! \class_exists(\Phar::class, false) || ! \Phar::running()) {
            return $font;
        }

        $tmpPath = sys_get_temp_dir() ?: '/tmp';
        $filePath = "$tmpPath/".basename($font);
        clearstatcache();
        if (! isset($fontPathMap[$font]) || ! is_file($filePath)) {
            file_put_contents($filePath, file_get_contents($font));
            $fontPathMap[$font] = $filePath;
        }

        return $fontPathMap[$font];
    }

    /**
     * Distorts the image
     */
    public function distort($image, $width, $height, $bg)
    {
        $contents = imagecreatetruecolor($width, $height);
        $X = $this->rand(0, $width);
        $Y = $this->rand(0, $height);
        $phase = $this->rand(0, 10);
        $scale = 1.1 + $this->rand(0, 10000) / 30000;
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $Vx = $x - $X;
                $Vy = $y - $Y;
                $Vn = sqrt($Vx * $Vx + $Vy * $Vy);

                if ($Vn != 0) {
                    $Vn2 = $Vn + 4 * sin($Vn / 30);
                    $nX = $X + ($Vx * $Vn2 / $Vn);
                    $nY = $Y + ($Vy * $Vn2 / $Vn);
                } else {
                    $nX = $X;
                    $nY = $Y;
                }
                $nY = $nY + $scale * sin($phase + $nX * 0.2);

                $p = $this->interpolate(
                    $nX - floor($nX),
                    $nY - floor($nY),
                    $this->getCol($image, floor($nX), floor($nY), $bg),
                    $this->getCol($image, ceil($nX), floor($nY), $bg),
                    $this->getCol($image, floor($nX), ceil($nY), $bg),
                    $this->getCol($image, ceil($nX), ceil($nY), $bg)
                );

                if ($p == 0) {
                    $p = $bg;
                }

                imagesetpixel($contents, $x, $y, $p);
            }
        }

        return $contents;
    }

    /**
     * Gets the image contents
     */
    public function get($quality = 90): bool|string
    {
        ob_start();
        imagejpeg($this->contents, null, $quality);

        return ob_get_clean();
    }

    /**
     * Returns a random number or the next number in the
     * fingerprint
     */
    protected function rand($min, $max): int
    {
        if (! is_array($this->fingerprint)) {
            $this->fingerprint = [];
        }
        $value = mt_rand(intval($min), intval($max));
        $this->fingerprint[] = $value;

        return $value;
    }

    /**
     * @param $x
     * @param $y
     * @param $nw
     * @param $ne
     * @param $sw
     * @param $se
     * @return int
     */
    protected function interpolate($x, $y, $nw, $ne, $sw, $se)
    {
        list($r0, $g0, $b0) = $this->getRGB($nw);
        list($r1, $g1, $b1) = $this->getRGB($ne);
        list($r2, $g2, $b2) = $this->getRGB($sw);
        list($r3, $g3, $b3) = $this->getRGB($se);

        $cx = 1.0 - $x;
        $cy = 1.0 - $y;

        $m0 = $cx * $r0 + $x * $r1;
        $m1 = $cx * $r2 + $x * $r3;
        $r = (int) ($cy * $m0 + $y * $m1);

        $m0 = $cx * $g0 + $x * $g1;
        $m1 = $cx * $g2 + $x * $g3;
        $g = (int) ($cy * $m0 + $y * $m1);

        $m0 = $cx * $b0 + $x * $b1;
        $m1 = $cx * $b2 + $x * $b3;
        $b = (int) ($cy * $m0 + $y * $m1);

        return ($r << 16) | ($g << 8) | $b;
    }

    /**
     * @param $image
     * @param $x
     * @param $y
     * @param $background
     * @return int
     */
    protected function getCol($image, $x, $y, $background)
    {
        $L = imagesx($image);
        $H = imagesy($image);
        if ($x < 0 || $x >= $L || $y < 0 || $y >= $H) {
            return $background;
        }

        return imagecolorat($image, $x, $y);
    }

    /**
     * @param $col
     * @return array
     */
    protected function getRGB($col)
    {
        return [
            (int) ($col >> 16) & 0xFF,
            (int) ($col >> 8) & 0xFF,
            (int) ($col) & 0xFF,
        ];
    }
}
