<?php

namespace herosphp\plugins\captcha;

/**
 * Generates random phrase
 *
 * @author Gregwar <g.passault@gmail.com>
 */
class PhraseBuilder
{
    /**
     * @var int
     */
    public $length;

    /**
     * @var string
     */
    public $charset;

    /**
     * Constructs a PhraseBuilder with given parameters
     */
    public function __construct($length = 4, $charset = '0123456789')
    {
        $this->length = $length;
        $this->charset = $charset;
    }

    /**
     * Generates  random phrase of given length with given charset
     */
    public function build($length = null, $charset = null): string
    {
        if ($length !== null) {
            $this->length = $length;
        }
        if ($charset !== null) {
            $this->charset = $charset;
        }

        $phrase = '';
        $chars = str_split($this->charset);

        for ($i = 0; $i < $this->length; $i++) {
            $phrase .= $chars[array_rand($chars)];
        }

        return $phrase;
    }

    /**
     * "Niceize" a code
     */
    public function niceize($str): string
    {
        return self::doNiceize($str);
    }

    /**
     * A static helper to niceize
     */
    public static function doNiceize($str): string
    {
        return strtr(strtolower($str), '01', 'ol');
    }


}
