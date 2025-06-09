<?php

namespace TonglianPay\Utils;

class RequestParams
{

    /**
     * @var array 需要转义的特殊字符
     */
    private array $escapeChars = [
        '&' => '&amp;',
        '"' => '&quot;',
        "'" => '&#39;',
        '<' => '&lt;',
        '>' => '&gt;',
        '\'' => '\\\'',
        '\\' => '\\\\',
        ';' => '\\;',
        '--' => '\\--',
        '/*' => '\\/*',
        '*/' => '\\*/'
    ];

    /**
     * 构造函数
     */
    public function __construct()
    {
    }

    /**
     * 获取GET参数
     * @param string|null $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * 获取POST参数
     * @param string|null $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function post(?string $key = null, $default = null)
    {

        $postData = [];

        // 判断是form提交还是json提交
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            // 获取原始POST数据
            $rawPost = $this->rawPost();
            if ($rawPost !== '') {
                $postData = json_decode($rawPost, true);
            }
        } else {
            // Form提交
            $postData = $_POST;
        }

        if ($key === null) {
            return $postData;
        }
        return $postData[$key] ?? $default;
    }

    /**
     * 获取REQUEST参数（GET、POST、JSON的合并）
     * @param string|null $key 参数名
     * @param mixed $default 默认值
     * @return mixed
     */
    public function params(?string $key = null, $default = null)
    {
        $getParams = $this->get();
        $postParams = $this->post();
        $params = array_merge($getParams, $postParams);
        if ($key === null) {
            return $params;
        }
        return $params[$key] ?? $default;
    }

    /**
     * 获取原始POST数据
     * @return string
     */
    public function rawPost(): string
    {
        return file_get_contents('php://input');
    }

    /**
     * 设置参数
     * @param string $key 参数名
     * @param mixed $value 参数值
     */
    public function set(string $key, $value): void
    {
        $this->params[$key] = $value;
    }

    /**
     * 设置转义字符
     * @param array $chars 转义字符映射
     */
    public function setEscapeChars(array $chars): void
    {
        $this->escapeChars = array_merge($this->escapeChars, $chars);
    }

    /**
     * 转义特殊字符
     * @param string $value 需要转义的值
     * @return string
     */
    private function escape(string $value): string
    {
        return strtr($value, $this->escapeChars);
    }

} 