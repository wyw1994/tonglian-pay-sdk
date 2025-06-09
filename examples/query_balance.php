<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TonglianPay\TonglianPay;

// 配置信息
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
    // 初始化支付客户端
    $pay = new TonglianPay($config);

    // 收银宝渠道余额查询示例
    $params = [
        'chanNo' => 'allinpay',
    ];

    // 发起余额查询
    $result = $pay->queryBalance($params);
    echo "余额查询成功：\n";
    /*余额查询成功示例：
Array
(
    [amount] => 0.05
    [cusid] => 66******M0C
)
*/
    print_r($result);

} catch (\Exception $e) {
    echo "余额查询失败：" . $e->getMessage() . "\n";
    if ($e->getCode()) {
        echo "错误代码：" . $e->getCode() . "\n";
    }
    if ($e->getData()) {
        echo "错误数据：\n";
        print_r($e->getData());
    }
} 