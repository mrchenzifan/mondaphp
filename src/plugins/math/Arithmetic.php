<?php

declare(strict_types=1);

namespace herosphp\plugins\math;

/**
 * Class Arithmetic
 */
class Arithmetic
{
    /** @var string 末位小数向上取整 */
    public const CEIL = 'ceil';

    /** @var string 末位小数四舍五入 */
    public const ROUND = 'round';

    /** @var string 末位小数向下取整 */
    public const FLOOR = 'floor';

    /** @var string 末位小数截断 */
    public const TRUNCATE = 'truncate';

    /** @var int 计算过程精度 */
    private static int $precision = 10;

    private string $result;

    /**
     * @param $result
     */
    private function __construct($result)
    {
        $this->result = (string) $result;
    }

    /**
     * 初始运算结果
     *
     * @param  float|int|string|Arithmetic  $result
     * @return Arithmetic
     */
    public static function init(self|float|int|string $result): self
    {
        if ($result instanceof self) {
            return clone $result;
        }

        return new self($result);
    }

    /**
     * 解析表达式
     *
     * @param  string  $expression
     * @return Arithmetic
     */
    public static function parse(string $expression): self
    {
        return self::init(Calculate::parse($expression, self::$precision));
    }

    /**
     * 加法
     *
     * @param  float|int|string|Arithmetic  $num
     * @return $this
     */
    public function add(self|float|int|string $num): self
    {
        if ($num instanceof self) {
            $num = $num->result;
        }
        $this->result = bcadd($this->result, (string) $num, self::$precision);

        return $this;
    }

    /**
     * 减法
     *
     * @param  float|int|string|Arithmetic  $num
     * @return $this
     */
    public function sub(self|float|int|string $num): self
    {
        if ($num instanceof self) {
            $num = $num->result;
        }
        $this->result = bcsub($this->result, (string) $num, self::$precision);

        return $this;
    }

    /**
     * 乘法
     *
     * @param  float|int|string|Arithmetic  $num
     * @return $this
     */
    public function mul(self|float|int|string $num): self
    {
        if ($num instanceof self) {
            $num = $num->result;
        }
        $this->result = bcmul($this->result, (string) $num, self::$precision);

        return $this;
    }

    /**
     * 除法
     *
     * @param  float|int|string|Arithmetic  $num
     * @return $this
     */
    public function div(self|float|int|string $num): self
    {
        if ($num instanceof self) {
            $num = $num->result;
        }
        $this->result = bcdiv($this->result, (string) $num, self::$precision);

        return $this;
    }

    /**
     * 比较，result>num:1, result=num:0, result<num:-1
     *
     * @param  float|int|string|Arithmetic  $num
     * @param  int  $precision
     * @return int
     */
    public function comp(self|float|int|string $num, int $precision = 3): int
    {
        if ($num instanceof self) {
            $num = $num->result;
        }

        return bccomp($this->getResult($precision), (string) $num, $precision);
    }

    /**
     * @param  int  $precision  精度
     * @param  string  $format  末位小数取值方式，round:四舍五入，ceil:向上取整，floor:向下取整，truncate:截断
     * @return string
     */
    public function getResult(int $precision = 2, string $format = self::ROUND): string
    {
        return match ($format) {
            self::ROUND => number_format((float) $this->result, $precision, '.', ''),
            self::CEIL => bcdiv((string) ceil((float) bcmul($this->result, (string) (10 ** $precision),
                ($precision + 1))), (string) (10 ** $precision), $precision),
            self::FLOOR => bcdiv((string) floor((float) bcmul($this->result, (string) (10 ** $precision),
                ($precision + 1))), (string) (10 ** $precision), $precision),
            self::TRUNCATE => bcdiv((string) ((int) bcmul($this->result, (string) (10 ** $precision), ($precision))),
                (string) (10 ** $precision), $precision),
            default => '',
        };

    }
}
