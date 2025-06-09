<?php

namespace TonglianPay\Exception;

class NotifyException extends TonglianPayException
{
    public function __construct(string $message = "回调通知处理失败", int $code = 4001, array $data = [])
    {
        parent::__construct($message, $code, $data);
    }
} 