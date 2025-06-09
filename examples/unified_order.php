<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TonglianPay\TonglianPay;
use TonglianPay\Utils\PayWayCode;

// 配置信息
$config = [
    'api_base_url' => 'https://tp.allinpay.com/pay/api', // 替换为实际的API地址
    'merchant_id' => 'E2******073',                      // 商户号
    'app_id' => '68******036',                          // 应用ID
    'merchant_key' => '2t******hk2jh9x5suw9lf1xfdnzwxixx', // 商户密钥
    'sign_type' => 'MD5',
    'channelExtra' => [
        'cusid' => '66******M0C', // 收银宝子商户号cusid
        'orgid' => '66******M3J', // 集团商户号orgid
    ],
    'log_path' => __DIR__ . '/logs/tonglianpay.log',
    'log_level' => \Monolog\Logger::DEBUG
];

try {
    // 初始化支付客户端
    $pay = new TonglianPay($config);

    // 微信公众号支付示例
    $params = [
        'mchOrderNo' => 'TEST' . date('YmdHis') . rand(1000, 9999),
        'wayCode' => PayWayCode::WX_NATIVE,
        'amount' => '10',
        'currency' => 'CNY',
        'clientIp' => '127.0.0.1',
        'notifyUrl' => 'http://example.com/notify.php', // 替换为实际的回调地址
        'body' => '测试商品',
        'channelExtra' => [
            'openid' => 'wx_******', // 微信公众号openid
            'subAppid' => 'wx_******', // 微信公众号APPID
        ]
    ];

    // 发起统一下单
    $result = $pay->unifiedOrder($params);
    echo "统一下单成功：\n";
    print_r($result);

} catch (\Exception $e) {
    echo "统一下单失败：" . $e->getMessage() . "\n";
    if ($e->getCode()) {
        echo "错误代码：" . $e->getCode() . "\n";
    }
    if ($e->getData()) {
        echo "错误数据：\n";
        print_r($e->getData());
    }
} 