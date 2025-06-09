<?php

namespace TonglianPay\Exception;

class InvalidConfigException extends TonglianPayException
{
    public function __construct(string $message = "配置参数无效", int $code = 1001, array $data = [])
    {
        parent::__construct($message, $code, $data);
    }
} 