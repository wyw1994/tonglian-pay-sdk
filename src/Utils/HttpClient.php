<?php

namespace TonglianPay\Utils;

use Exception;

class HttpResponse
{
    private array $headers = [];
    private string $content = '';
    private array $info = [];
    private array $errors = [];

    public function __construct(string $content, array $headers, array $info, array $errors = [])
    {
        $this->content = $content;
        $this->headers = $headers;
        $this->info = $info;
        $this->errors = $errors;
    }

    public function isSuccess(): bool
    {
        return empty($this->errors) && $this->getStatusCode() >= 200 && $this->getStatusCode() < 300;
    }

    public function getStatusCode(): int
    {
        return $this->info['http_code'] ?? 0;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        $name = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }
        return null;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getBody()
    {
        $contentType = $this->getHeader('content-type');
        if (strpos($contentType, 'application/json') !== false) {
            return json_decode($this->content, true);
        }
        if (strpos($contentType, 'application/xml') !== false) {
            return simplexml_load_string($this->content);
        }
        return $this->content;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getInfo(): array
    {
        return $this->info;
    }
}

class HttpClient
{
    private array $config;
    private array $defaultOptions = [
        'timeout' => 5,
        'verify_ssl' => false,
        'headers' => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: */*'
        ]
    ];

    /**
     * @param array $config 配置参数
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->defaultOptions, $config);
    }

    /**
     * 发送POST请求（原始数据）
     * @param string $url 请求地址
     * @param string $rawData 原始数据
     * @param array $headers 请求头
     * @return HttpResponse
     */
    public function postRaw(string $url, string $rawData, array $headers = []): HttpResponse
    {
        return $this->request('POST', $url, $rawData, $headers);
    }

    /**
     * 发送POST请求（form-data）
     * @param string $url 请求地址
     * @param array $data 表单数据
     * @param array $headers 请求头
     * @return HttpResponse
     */
    public function postFormData(string $url, array $data, array $headers = []): HttpResponse
    {
        // 构建 multipart/form-data 格式的数据
        $boundary = uniqid();
        $delimiter = '-------------' . $boundary;
        
        $postData = '';
        foreach ($data as $name => $content) {
            $postData .= "--" . $delimiter . "\r\n"
                . 'Content-Disposition: form-data; name="' . $name . "\"\r\n\r\n"
                . $content . "\r\n";
        }
        $postData .= "--" . $delimiter . "--\r\n";

        $headers[] = 'Content-Type: multipart/form-data; boundary=' . $delimiter;
        $headers[] = 'Content-Length: ' . strlen($postData);
        
        return $this->request('POST', $url, $postData, $headers);
    }

    /**
     * 发送POST请求（JSON）
     * @param string $url 请求地址
     * @param array|string $data JSON数据
     * @param array $headers 请求头
     * @return HttpResponse
     */
    public function postJson(string $url, $data, array $headers = []): HttpResponse
    {
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($data);
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * 发送GET请求
     * @param string $url 请求地址
     * @param array $query 查询参数
     * @param array $headers 请求头
     * @return HttpResponse
     */
    public function get(string $url, array $query = [], array $headers = []): HttpResponse
    {
        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $this->request('GET', $url, null, $headers);
    }

    /**
     * 发送HTTP请求
     * @param string $method 请求方法
     * @param string $url 请求地址
     * @param mixed $data 请求数据
     * @param array $headers 请求头
     * @return HttpResponse
     */
    private function request(string $method, string $url, $data = null, array $headers = []): HttpResponse
    {
        $ch = curl_init();
        
        // 合并请求头
        $headers = array_merge($this->config['headers'], $headers);
        
        // 设置curl选项
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => $this->config['verify_ssl'] ? 2 : 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true
        ];

        // 设置请求方法和数据
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            if ($data !== null) {
                $options[CURLOPT_POSTFIELDS] = $data;
            }
        }

        curl_setopt_array($ch, $options);

        // 执行请求
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        
        curl_close($ch);

        // 分离响应头和响应体
        $headerSize = $info['header_size'];
        $headerStr = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        // 解析响应头
        $headers = [];
        foreach (explode("\r\n", $headerStr) as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        // 收集错误信息
        $errors = [];
        if ($error) {
            $errors[] = "CURL错误: {$error} (错误码: {$errno})";
        }
        if ($info['http_code'] >= 400) {
            $errors[] = "HTTP错误: 状态码 {$info['http_code']}";
        }

        return new HttpResponse($body, $headers, $info, $errors);
    }
}