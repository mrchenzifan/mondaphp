<?php

declare(strict_types=1);

namespace herosphp\plugins\math;

class Money
{
    private string $money;

    private array $uppers = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];

    private array $units = ['厘', '分', '角'];

    private array $grees = ['元', '拾', '佰', '仟', '万', '拾', '佰', '仟', '亿', '拾', '佰', '仟', '万', '拾', '佰'];

    private bool $thanOne = false;

    /**
     * Money constructor.
     *
     * @param  float|int|string  $money  default 0
     */
    public function __construct(float|int|string $money = 0)
    {
        $this->setMoney($money);
    }

    /**
     * Get Init Money.
     *
     * @return string
     */
    public function getMoney(): string
    {
        return $this->money;
    }

    /**
     * @param  float|int|string  $money  default 0
     */
    public function setMoney(float|int|string $money = 0): void
    {
        if (! (is_float($money) || is_numeric($money) || is_int($money))) {
            throw new \InvalidArgumentException($money);
        }
        if ($money > 1) {
            $this->thanOne = true;
        }
        $this->money = number_format((float) $money, 3, '.', '');
    }

    /**
     * Convert to Capital.
     *
     * @return string
     */
    public function toCapital(): string
    {
        @[$intPart, $decimalPart] = explode('.', $this->money, 2);
        if (0.0 === (float) $this->money) {
            return '零元';
        }
        $result = $this->getIntPart($intPart);
        $result .= $this->getDecimalPart($decimalPart);

        return $result;
    }

    /**
     * Parse to Capital.
     *
     * @param  float|int|string  $money  default 0
     * @return string
     */
    public function parse(float|int|string $money): string
    {
        $this->setMoney($money);

        return $this->toCapital();
    }

    /**
     * Get Int Part.
     *
     * @param $intPart
     * @return string
     */
    private function getIntPart($intPart): string
    {
        $result = '';
        $gree = strlen($intPart) - 1;
        if ($intPart > 0) {
            for ($i = 0, $iMax = strlen($intPart); $i < $iMax; $i++) {
                $num = $intPart[$i];
                $result .= $this->uppers[$num].$this->grees[$gree--];
            }
        }

        $result = str_replace('零亿', '亿零', $result);
        $result = str_replace('零万', '万零', $result);

        $result = str_replace('零拾', '零', $result);
        $result = str_replace('零佰', '零', $result);
        $result = str_replace('零仟', '零', $result);

        $result = str_replace('零零', '零', $result);
        $result = str_replace('零零', '零', $result);

        $result = str_replace('零亿', '亿', $result);
        $result = str_replace('零万', '万', $result);

        return str_replace('零元', '元', $result);
    }

    /**
     * Get Decimal Part.
     *
     * @param $decimalPart
     * @return string
     */
    private function getDecimalPart($decimalPart): string
    {
        $result = '';
        if ($decimalPart > 0) {
            $unit = strlen($decimalPart) - 1;
            for ($i = 0, $iMax = strlen($decimalPart); $i < $iMax; $i++) {
                $num = $decimalPart[$i];
                $result .= $this->uppers[$num].$this->units[$unit--];
            }
        }
        $result = str_replace('零分', '', $result);
        if ($this->thanOne) {
            $result = str_replace('零角', '零', $result);
        } else {
            $result = str_replace('零角', '', $result);
        }

        return $result;
    }
}
