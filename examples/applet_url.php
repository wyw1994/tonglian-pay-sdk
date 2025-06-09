<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TonglianPay\TonglianPay;

// 配置信息
$config = [
    'api_base_url' => 'https://tp.allinpay.com/pay/api', // 替换为实际的API地址
    'merchant_id' => 'E2******073',                      // 商户号
    'app_id' => '68******036',                          // 应用ID
    'channelExtra'=>[
        'cusid' => '66******M0C', // 收银宝子商户号cusid
        'orgid' => '66******M3J', // 集团商户号orgid
    ],
    'merchant_key' => '2t******hk2jh9x5suw9lf1xfdnzwxixx', // 商户密钥
    'sign_type' => 'MD5',
    'log_path' => __DIR__ . '/logs/tonglianpay.log',
    'log_level' => \Monolog\Logger::DEBUG
];

try {
    // 初始化支付客户端
    $pay = new TonglianPay($config);

    // 获取小程序链接参数
    $params = [
        'mchOrderNo' => 'TEST' . date('YmdHis') . rand(1000, 9999),
        'amount' => '5',
        'notifyUrl' => 'http://example.com/notify.php', // 替换为实际的回调地址
        'body' => '测试商品',
        'urlType' => 'URL_Common',
    ];

    // 获取小程序链接
    $result = $pay->appletUrl($params);
    echo "获取小程序链接成功：\n";
    print_r($result);

} catch (\Exception $e) {
    echo "获取小程序链接失败：" . $e->getMessage() . "\n";
    if ($e->getCode()) {
        echo "错误代码：" . $e->getCode() . "\n";
    }
    if ($e->getData()) {
        echo "错误数据：\n";
        print_r($e->getData());
    }
} 