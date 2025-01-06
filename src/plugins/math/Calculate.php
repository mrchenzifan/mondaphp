<?php

declare(strict_types=1);

namespace herosphp\plugins\math;

/**
 * 计算简单表达式
 * $tests = [
 *       "1 + 2 - 3 * ( 1 + 2)" => '-6',
 *       "(2 - 1) - (3 - 1)" => '-1',
 *       "1 + 2 * 2 - 2 / 2" => '4',
 *       '1 + (1 + (1 - (2 + 2)))' => '-1',
 *       "-1 + 1" => '0',
 *       "1 + (1 - -1)" => "3",
 *       "1 + (-1 * 2) / 2" => "0",
 *       "1 + 1 * 2 * 3 / 2" => "4",
 *       "(121.000/(1+11/100)-1210.000/(1+11/100))/(121.000/(1+11/100))" => "-9",
 *       "((121.000/(1+11/100))-(1210.000/(1+11/100)))/(121.000/(1+11/100))" => "-9",
 *   ];
 *
 *   foreach ($tests as $k => $v) {
 *       echo 'expect: ' . $k . '   => res:' . $v . "\n";
 *       echo 'actual: ' . Calculate::parse($k) . "\n";
 *   }
 *
 * expect: 1 + 2 - 3 * ( 1 + 2)   => res:-6
 * actual: -6.000000
 * expect: (2 - 1) - (3 - 1)   => res:-1
 * actual: -1.000000
 * expect: 1 + 2 * 2 - 2 / 2   => res:4
 * actual: 4.000000
 * expect: 1 + (1 + (1 - (2 + 2)))   => res:-1
 * actual: -1.000000
 * expect: -1 + 1   => res:0
 * actual: 0.000000
 * expect: 1 + (1 - -1)   => res:3
 * actual: 3.000000
 * expect: 1 + (-1 * 2) / 2   => res:0
 * actual: 0.000000
 * expect: 1 + 1 * 2 * 3 / 2   => res:4
 * actual: 4.000000
 * expect: (121.000/(1+11/100)-1210.000/(1+11/100))/(121.000/(1+11/100))   => res:-9
 * actual: -9.0000000000
 * expect: ((121.000/(1+11/100))-(1210.000/(1+11/100)))/(121.000/(1+11/100))   => res:-9
 * actual: -9.0000000000

 */
class Calculate
{
    private array $numberStack = [];

    private array $operateStack = [];

    private array $operateSymbols = ['+', '-', '*', '/', '(', ')'];

    /**
     * 解析并计算表达式并返回结果
     *
     * @param  string  $expression
     * @param  int  $precision
     * @return string
     */
    public static function parse(string $expression, int $precision = 10): string
    {
        return (new self)->calculate($expression, $precision);
    }

    /**
     * 计算表达式
     *
     * @param  string  $expression
     * @param  int  $precision
     * @return string
     */
    public function calculate(string $expression, int $precision = 10): string
    {
        bcscale($precision);

        $expression = str_replace(' ', '', $expression);
        $len = strlen($expression);
        for ($i = 0; $i < $len; $i++) {
            if (in_array($expression[$i], $this->operateSymbols, true)) {
                if (isset($expression[$i + 1]) && $expression[$i + 1] === '-') {
                    //()-()
                    array_unshift($this->operateStack, $expression[$i]);
                    if (isset($expression[$i + 2]) && $expression[$i + 2] != '(' && $expression[$i] != ')') {
                        $i = $this->readNextNumber($expression, $i + 1);
                    }
                } elseif ($i === 0 && $expression[$i] === '-') {
                    $i = $this->readNextNumber($expression, $i);
                } else {
                    array_unshift($this->operateStack, $expression[$i]);
                }
                $compareRes = true;
                while ($compareRes) {
                    $compareRes = $this->compareAndCalculate();
                }
            } else {
                $i = $this->readNextNumber($expression, $i);
            }
        }

        while ($op = array_shift($this->operateStack)) {
            $this->bcCalculate($op);
        }

        return array_shift($this->numberStack);
    }

    /**
     * 比较操作符优先级并计算
     *
     * @return bool
     */
    private function compareAndCalculate(): bool
    {
        if (count($this->operateStack) < 2) {
            return false;
        }
        $symbolCurrent = array_shift($this->operateStack);
        $symbolPre = array_shift($this->operateStack);

        if (in_array($symbolPre, ['+', '-'])) {
            if (in_array($symbolCurrent, ['*', '/', '('])) {
                array_unshift($this->operateStack, $symbolPre);
                array_unshift($this->operateStack, $symbolCurrent);

                return false;
            }

            if (in_array($symbolCurrent, ['+', '-', ')'])) {
                $this->bcCalculate($symbolPre);
                array_unshift($this->operateStack, $symbolCurrent);

                return true;
            }
        }
        if (in_array($symbolPre, ['*', '/']) && in_array($symbolCurrent, ['+', '-', '*', '/', ')'])) {
            $this->bcCalculate($symbolPre);
            array_unshift($this->operateStack, $symbolCurrent);

            return true;
        }
        if ($symbolPre === '(' && $symbolCurrent === ')') {
            return true;
        }
        array_unshift($this->operateStack, $symbolPre);
        array_unshift($this->operateStack, $symbolCurrent);

        return false;
    }

    /**
     * bc函数计算
     *
     * @param  string  $operate
     * @return void
     */
    private function bcCalculate(string $operate): void
    {
        $num2 = array_shift($this->numberStack);
        $num1 = array_shift($this->numberStack);
        $res = 0;
        switch ($operate) {
            case '+':
                $res = bcadd($num1, $num2);
                break;
            case '-':
                $res = bcsub($num1, $num2);
                break;
            case '*':
                $res = bcmul($num1, $num2);
                break;
            case '/':
                $res = bcdiv($num1, $num2);
                break;
        }
        array_unshift($this->numberStack, $res);
    }

    /**
     * 读取数字
     *
     * @param  string  $expr
     * @param  int  $idx
     * @return int
     */
    private function readNextNumber(string $expr, int $idx): int
    {
        $numStr = $expr[$idx];
        $i = $idx + 1;
        $iMax = strlen($expr);
        for (; $i < $iMax; $i++) {
            if (in_array($expr[$i], $this->operateSymbols)) {
                break;
            }
            $numStr .= $expr[$i];
        }
        array_unshift($this->numberStack, $numStr);

        return $i - 1;
    }
}
