<?php

namespace TonglianPay\Exception;

class PayException extends TonglianPayException
{
    public function __construct(string $message = "支付失败", int $code = 2001, array $data = [])
    {
        parent::__construct($message, $code, $data);
    }
} 