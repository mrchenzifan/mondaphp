<?php

namespace herosphp\utils;

use RuntimeException;

class RSACrypt
{
    /**
     * 私钥加密.
     */
    public function encryptByPrivateKey(string $data, string $privateKey): string
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('please install openssl extension.');
        }
        $pi_key = openssl_pkey_get_private($privateKey);
        $encrypted = '';
        openssl_private_encrypt($data, $encrypted, $pi_key); //私钥加密
        //加密后的内容通常含有特殊字符，需要编码转换下，在网络间通过url传输时要注意base64编码是否是url安全的
        return self::urlSafeB64encode($encrypted);
    }

    /**
     * 公钥解密.
     */
    public function decryptByPublicKey(string $data, string $publicKey): string
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('please install openssl extension.');
        }
        $pu_key = openssl_pkey_get_public($publicKey);
        $decrypted = '';
        $data = self::urlSafeB64decode($data);
        openssl_public_decrypt($data, $decrypted, $pu_key); //公钥解密

        return $decrypted;
    }

    /**
     * 公钥加密.
     */
    public function encryptByPublicKey(string $data, string $publicKey): string
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('please install openssl extension.');
        }
        $pu_key = openssl_pkey_get_public($publicKey);
        $encrypted = '';
        openssl_public_encrypt($data, $encrypted, $pu_key); //公钥加密
        //加密后的内容通常含有特殊字符，需要编码转换下，在网络间通过url传输时要注意base64编码是否是url安全的
        return self::urlSafeB64encode($encrypted);
    }

    /**
     * 私钥解密.
     */
    public function decryptByPrivateKey(string $data, string $privateKey): string
    {
        if (! extension_loaded('openssl')) {
            throw new RuntimeException('please install openssl extension.');
        }
        $pi_key = openssl_pkey_get_private($privateKey);
        $decrypted = '';
        $data = self::urlSafeB64decode($data);
        openssl_private_decrypt($data, $decrypted, $pi_key); //私钥解密

        return $decrypted;
    }

    /**
     * 安全的b64encode.
     *
     * @param  string  $string
     * @return string
     */
    private static function urlSafeB64encode(string $string): string
    {
        $data = base64_encode($string);

        return str_replace(['+', '/', '='], ['-', '_', '@'], $data);
    }

    /**
     * 安全的b64decode.
     *
     * @param  string  $string
     * @return string
     */
    private static function urlSafeB64decode(string $string): string
    {
        $data = str_replace(['-', '_', '@'], ['+', '/', '='], $string);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }

        return base64_decode($data);
    }
}
