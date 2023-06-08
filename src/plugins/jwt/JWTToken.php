<?php

declare(strict_types=1);

namespace herosphp\plugins\jwt;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * @note composer install "firebase/php-jwt"
 */
class JWTToken
{
    /**
     * @param $key string 秘钥
     * @param $data array 需要保存的业务数据
     * @param $expire int 失效时间
     * @return string
     */
    public static function encode(string $key, array $data, int $expire): string
    {
        $payload = [
            'iss' => 'mondaphp_token',
            'iat' => time(),  // 签发时间
            'nbf' => time(),  // 生效时间
            'exp' => $expire, // 失效时间
        ];
        $payload = array_merge($payload, $data);

        return JWT::encode($payload, $key, 'HS256');
    }

    /**
     * @note 解析错误会抛出异常，开发者需要自己try..catch
     *
     * @param $key string 秘钥
     * @param $token string 客户端传递的token
     * @param $leeway int 这是一个缓冲值，单位为秒。在验证的时候，用来延长当前时间JWT::$timestamp ($leeway这个时间尽量短，或者不设置)
     * @return array
     */
    public static function decode(string $key, string $token, int $leeway = 60): array
    {
        JWT::$leeway = $leeway;
        $decoded = JWT::decode($token, new Key($key, 'HS256'));

        return (array) $decoded;
    }
}
