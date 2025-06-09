<?php

namespace TonglianPay\Exception;

class TonglianPayException extends \Exception
{
    protected array $data = [];

    public function __construct(string $message = "", int $code = 0, array $data = [])
    {
        parent::__construct($message, $code);
        $this->data = $data;
    }

    public function getData(): array
    {
        return $this->data;
    }
} 