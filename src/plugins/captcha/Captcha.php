<?php

namespace herosphp\plugins\captcha;

use herosphp\exception\HeroException;

class Captcha
{
    /**
     * 位数
     *
     * @var int
     */
    protected int $digits = 1;

    /**
     * 验证码宽度
     *
     * @var int
     */
    protected int $width = 130;

    /**
     * 验证码高度
     *
     * @var int
     */
    protected int $height = 48;

    /**
     * 字体大小
     *
     * @var int
     */
    protected int $fontSize = 16;

    /**
     * 输出图片字符串
     *
     * @var string
     */
    private string $str;

    /**
     * 底色
     *
     * @var array|int[]
     */
    protected array $color = [255, 255, 255];

    /**
     * 设置透明色
     *
     * @var int
     */
    protected int $alpha = 127;

    /**
     * 是否添加干扰点
     *
     * @var int
     */
    private int $pointNum = 50;

    /**
     * 是否添加干扰线
     *
     * @var int
     */
    private int $lineNum = 3;

    /**
     * 字体文件
     *
     * @var string
     */
    protected string $font;

    /**
     * 图片
     *
     * @var false|\GdImage|resource
     */
    private $image;

    /**
     * math captcha init.
     */
    public function __construct()
    {

        if (! function_exists('imagecreate')) {
            throw new HeroException('Please install GD library ');
        }
        $this->font = __DIR__.DIRECTORY_SEPARATOR.'fonts'.DIRECTORY_SEPARATOR.'arial.ttf';
    }

    /**
     * 数字位数
     *
     * @param  int  $digits
     * @return $this
     */
    public function setDigits(int $digits): static
    {
        if ($digits > 0) {
            $this->digits = $digits;
        }

        return $this;
    }

    /**
     * 设置验证码尺寸
     *
     * @param  int  $width
     * @param  int  $height
     * @return $this
     */
    public function setSize(int $width, int $height): static
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    /**
     * 设置底色
     *
     * @param  array  $color
     * @return $this
     */
    public function setColor(array $color): static
    {
        if (count($color) >= 3) {
            $this->color = $color;
        }

        return $this;
    }

    /**
     * 设置干扰点数
     *
     * @param  int  $point
     * @return $this
     */
    public function setPoint(int $point): static
    {
        $this->pointNum = $point;

        return $this;
    }

    /**
     * 设置透明度
     *
     * @param  int  $alpha
     * @return $this
     */
    public function setAlpha(int $alpha): static
    {
        $this->alpha = $alpha;

        return $this;
    }

    /**
     * 设置字体大小 （1，2，3，4，5）
     *
     * @param  int  $fontSize
     * @return $this
     */
    public function setFontSize(int $fontSize): static
    {
        $this->fontSize = $fontSize;

        return $this;
    }

    /**
     * 设置字体
     *
     * @param  string  $font
     * @return $this
     */
    public function setFont(string $font): static
    {
        $this->font = $font;

        return $this;
    }

    /**
     * 设置干扰线数
     *
     * @param  int  $line
     * @return $this
     */
    public function setLine(int $line): static
    {
        $this->lineNum = $line;

        return $this;
    }

    /**
     * 获取验证码结果
     *
     * @return int
     */
    public function result(): int
    {
        // 获取随机操作运算符
        $operator = $this->getOperator();
        // 获取数字1
        $num1 = rand(pow(10, $this->digits - 1), pow(10, $this->digits) - 1);
        // 获取数字2
        $num2 = rand(pow(10, $this->digits - 1), pow(10, $this->digits) - 1);
        // 返回结果
        $this->str = $num1.$operator.$num2.'=';

        return (int) match ($operator) {
            '+' => $num1 + $num2,
            '*' => $num1 * $num2,
            default => 0,
        };
    }

    /**
     * 生成验证码
     *
     * @return void
     */
    private function build(): void
    {
        // 创建底板
        $image = imagecreate($this->width, $this->height);
        // 填充颜色
        $bgColor = imagecolorallocatealpha($image, $this->color[0], $this->color[1], $this->color[2], $this->alpha);
        imagefill($image, 0, 0, $bgColor);
        putenv('GDFONTPATH='.realpath('.'));
        // 垂直居中
        $bBox = imagettfbbox($this->fontSize, 0, $this->font, $this->str);
        $_y = ceil(($this->height - $bBox[1] - $bBox[7]) / 2);
        // 画验证码
        for ($i = 0; $i < strlen($this->str); $i++) {
            $fontColor = (int) imagecolorallocate($image, rand(20, 100), rand(30, 100), rand(10, 200));
            $fontContent = $this->str[$i]; //每次截取一个字符
            $x = (int) ($i * $this->width / strlen($this->str)) + rand(5, 10);
            $y = rand($_y - 5, $_y + 5);
            imagettftext($image, (float) $this->fontSize, 0.0, $x, $y, $fontColor, $this->font, $fontContent);
        }
        //添加干扰点
        for ($i = 0; $i < $this->pointNum; $i++) {
            $pointColor = imagecolorallocate($image, rand(50, 200), rand(50, 200), rand(50, 200));
            imagesetpixel($image, rand(0, $this->width), rand(0, $this->height), $pointColor);
        }
        //添加干扰线
        for ($i = 0; $i < $this->lineNum; $i++) {
            $lineColor = imagecolorallocate($image, rand(80, 200), rand(80, 200), rand(80, 200));
            imageline($image, rand(5, $this->width - 5), rand(5, $this->height - 5), rand(5, $this->width - 5),
                rand(5, $this->height - 5), $lineColor);
        }
        $this->image = $image;
    }

    /**
     * @return string
     */
    public function output(): string
    {
        $this->build();
        ob_start();
        imagepng($this->image);
        $data = ob_get_contents();
        ob_clean();
        ob_end_clean();
        imagedestroy($this->image);

        return $data;
    }

    /**
     * 获取随机操作运算符
     *
     * @return string
     */
    private function getOperator(): string
    {
        $arr = '+*';

        return substr($arr, rand(0, strlen($arr) - 1), 1);
    }
}
