<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TonglianPay\TonglianPay;
use TonglianPay\Utils\SignType;

$config = [
    'api_base_url' => 'https://tp.allinpay.com/pay/api', // 替换为实际的API地址
    'merchant_id' => 'E2******073',                      // 商户号
    'app_id' => '68******036',                          // 应用ID
    'channelExtra' => [
        'orgid' => '66******M3J', // 集团商户号orgid
        'cusid' => '66******M0C'  // 收银宝子商户号cusid
    ],
    'merchant_key' => '2t******hk2jh9x5suw9lf1xfdnzwxixx', // 商户密钥
    'sign_type' => 'MD5',
    'log_path' => __DIR__ . '/logs/tonglianpay.log',
    'log_level' => \Monolog\Logger::DEBUG
];

try {
    // 初始化支付实例
    $pay = new TonglianPay($config);

    // 方式一：通过商户订单号查询
    $result1 = $pay->queryOrder([
        'mchOrderNo' => 'M202403210001' // 商户订单号
    ]);
    echo "通过商户订单号查询结果：\n";
    print_r($result1);

    // 方式二：通过支付订单号查询
    $result2 = $pay->queryOrder([
        'payOrderId' => 'P202403210001' // 支付订单号
    ]);
    echo "\n通过支付订单号查询结果：\n";
    print_r($result2);

    // 方式三：指定业务类型查询
    $result3 = $pay->queryOrder([
        'mchOrderNo' => 'M202403210001',
        'busiType' => '1' // 1-支付订单查询，4-提现订单查询，5-转账订单查询
    ]);
    echo "\n指定业务类型查询结果：\n";
    print_r($result3);

} catch (\Exception $e) {
    echo "查询失败：" . $e->getMessage() . "\n";
    if ($e->getCode()) {
        echo "错误代码：" . $e->getCode() . "\n";
    }
    if ($e->getPrevious()) {
        echo "原始错误：" . $e->getPrevious()->getMessage() . "\n";
    }
} 