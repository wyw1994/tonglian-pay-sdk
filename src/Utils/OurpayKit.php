<?php

namespace TonglianPay\Utils;

use TonglianPay\Exception\PayException;

class OurpayKit
{
    private const AES_KEY = 'cUbPFYFNVG}*3}5[m3|K9kfL';
    private const ENCODING_CHARSET = 'UTF-8';

    /**
     * AES加密
     * @param string $str 待加密字符串
     * @return string
     */
    public static function aesEncode(string $str): string
    {
        $key = substr(hash('sha256', self::AES_KEY, true), 0, 32);
        $iv = substr(hash('sha256', self::AES_KEY, true), 0, 16);
        $encrypted = openssl_encrypt($str, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return bin2hex($encrypted);
    }

    /**
     * AES解密
     * @param string $str 待解密字符串
     * @return string
     */
    public static function aesDecode(string $str): string
    {
        $key = substr(hash('sha256', self::AES_KEY, true), 0, 32);
        $iv = substr(hash('sha256', self::AES_KEY, true), 0, 16);
        $decrypted = openssl_decrypt(hex2bin($str), 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted;
    }

    /**
     * 生成签名
     * @param array $params 参数
     * @param string $signType 签名类型
     * @param string $key 密钥
     * @return string
     */
    public static function getSign(array $params, string $signType, string $key): string
    {
        return self::unionSign($params, $key, $signType);
    }

    /**
     * 验证签名
     * @param array $params 参数
     * @param string $signType 签名类型
     * @param string $key 密钥
     * @param string|null $certId 证书ID（SM2签名时需要）
     * @return bool
     */
    public static function checkSign(array $params, string $signType, string $key, ?string $certId = null): bool
    {
        if (!isset($params['sign'])) {
            return false;
        }

        $sign = $params['sign'];
        unset($params['sign']);

        return self::validSign($params, $key, $signType, $certId, $sign);
    }

    /**
     * 生成签名
     * @param array $params 参数
     * @param string $key 密钥
     * @param string $signType 签名类型
     * @return string
     */
    private static function unionSign(array $params, string $key, string $signType): string
    {
        // 1. 参数排序（按照参数名ASCII码从小到大排序）
        ksort($params);
        
        // 2. 构建签名字符串
        $stringToSign = '';
        foreach ($params as $k => $v) {
            // 参数值为空不参与签名
            if ($v !== null && $v !== '') {
                $stringToSign .= $k . '=' . $v . '&';
            }
        }

        // 3. 拼接密钥
        if ($signType === SignType::MD5) {
            $stringToSign .= 'key=' . $key;
        } else {
            $stringToSign = rtrim($stringToSign, '&');
        }
        //echo '拼接字符串：'.$stringToSign."\n";
        //var_dump($params);
       // exit;
        // 4. 根据签名类型生成签名
        switch ($signType) {
            case SignType::MD5:
                // MD5签名需要转大写
                return strtoupper(md5($stringToSign));
            case SignType::RSA:
                return self::rsaSign($stringToSign, $key);
            case SignType::SM2:
                // TODO: 实现SM2签名
                throw new PayException('SM2签名暂未实现');
            default:
                throw new PayException('不支持的签名类型：' . $signType);
        }
    }

    /**
     * 验证签名
     * @param array $params 参数
     * @param string $key 密钥
     * @param string $signType 签名类型
     * @param string|null $certId 证书ID
     * @param string $sign 签名
     * @return bool
     */
    private static function validSign(array $params, string $key, string $signType, ?string $certId, string $sign): bool
    {
        // 1. 参数排序（按照参数名ASCII码从小到大排序）
        ksort($params);
        
        // 2. 构建签名字符串
        $stringToSign = '';
        foreach ($params as $k => $v) {
            // 参数值为空不参与签名
            if ($v !== null && $v !== '') {
                $stringToSign .= $k . '=' . $v . '&';
            }
        }

        // 3. 拼接密钥
        if ($signType === SignType::MD5) {
            $stringToSign .= 'key=' . $key;
        } else {
            $stringToSign = rtrim($stringToSign, '&');
        }

        // 4. 根据签名类型验证签名
        switch ($signType) {
            case SignType::MD5:
                // MD5签名需要转大写
                return strtoupper($sign) === strtoupper(md5($stringToSign));
            case SignType::RSA:
                return self::rsaVerify($stringToSign, $sign, $key);
            case SignType::SM2:
                // TODO: 实现SM2签名验证
                throw new PayException('SM2签名验证暂未实现');
            default:
                throw new PayException('不支持的签名类型：' . $signType);
        }
    }

    /**
     * RSA签名
     * @param string $data 待签名数据
     * @param string $privateKey 私钥
     * @return string
     */
    private static function rsaSign(string $data, string $privateKey): string
    {
        $privateKey = "-----BEGIN PRIVATE KEY-----\n" .
            wordwrap($privateKey, 64, "\n", true) .
            "\n-----END PRIVATE KEY-----";

        $key = openssl_pkey_get_private($privateKey);
        if (!$key) {
            throw new PayException('无效的RSA私钥');
        }

        openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);

        return base64_encode($signature);
    }

    /**
     * RSA签名验证
     * @param string $data 待验证数据
     * @param string $signature 签名
     * @param string $publicKey 公钥
     * @return bool
     */
    private static function rsaVerify(string $data, string $signature, string $publicKey): bool
    {
        $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($publicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        $key = openssl_pkey_get_public($publicKey);
        if (!$key) {
            throw new PayException('无效的RSA公钥');
        }

        $result = openssl_verify(
            $data,
            base64_decode($signature),
            $key,
            OPENSSL_ALGO_SHA256
        );
        openssl_free_key($key);

        return $result === 1;
    }

    /**
     * 生成URL参数
     * @param array $params 参数
     * @return string
     */
    public static function genUrlParams(array $params): string
    {
        if (empty($params)) {
            return '';
        }

        $urlParams = [];
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $urlParams[] = $key . '=' . self::doEncode((string)$value);
            }
        }

        return implode('&', $urlParams);
    }

    /**
     * URL编码
     * @param string $str 待编码字符串
     * @return string
     */
    private static function doEncode(string $str): string
    {
        if (str_contains($str, '+')) {
            return urlencode($str);
        }
        return $str;
    }
} 