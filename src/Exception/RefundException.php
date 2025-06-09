<?php

namespace TonglianPay\Exception;

class RefundException extends TonglianPayException
{
    public function __construct(string $message = "退款失败", int $code = 3001, array $data = [])
    {
        parent::__construct($message, $code, $data);
    }
} 