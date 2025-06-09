<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TonglianPay\TonglianPay;

// 配置信息
$config = [
    'api_base_url' => 'https://tp.allinpay.com/pay/api', // 替换为实际的API地址
    'merchant_id' => 'E2******073', // 商户号
    'app_id' => '68******036', // 应用ID
    'merchant_key' => '2t******hk2jh9x5suw9lf1xfdnzwxixx', // 商户密钥
    'sign_type' => 'MD5', // 签名类型：MD5/RSA/SM2
    'log_path' => __DIR__ . '/logs/tonglianpay.log', // 日志路径
    'log_level' => \Monolog\Logger::DEBUG // 日志级别
];

try {
    // 初始化支付客户端
    $pay = new TonglianPay($config);

    // 退款参数
    $params = [
        'mchRefundNo' => 'REFUND' . date('YmdHis') . rand(1000, 9999), // 商户退款单号，必填，商户系统唯一标识
        'mchOrderNo' => 'TEST202506051157079744', // 商户订单号，与payOrderId二选一，原支付订单的商户订单号
        // 'payOrderId' => 'P1789101791210803202', // 支付订单号，与mchOrderNo二选一，原支付订单的支付订单号
        'refundAmount' => 9, // 退款金额，必填，单位分，不能大于原订单金额
        'currency' => 'cny', // 货币代码，选填，默认人民币，支持：cny-人民币，usd-美元
        'refundReason' => '商品退款', // 退款原因，选填，最大长度128个字符
        'clientIp' => '127.0.0.1', // 客户端IP，选填，最大长度32个字符
        'notifyUrl' => 'http://example.com/refund_notify.php', // 退款结果通知地址，选填，最大长度256个字符
        'channelExtra' => '', // 渠道参数，选填，特定渠道发起的额外参数，JSON格式
        'extParam' => '' // 商户扩展参数，选填，回调时会原样返回，最大长度256个字符
    ];

    // 发起退款
    $result = $pay->refund($params);
    echo "退款申请成功：\n";
    print_r($result);

} catch (\TonglianPay\Exception\RefundException $e) {
    echo "退款申请失败：" . $e->getMessage() . "\n";
    if ($e->getCode()) {
        echo "错误代码：" . $e->getCode() . "\n";
    }
    if ($e->getData()) {
        echo "错误数据：\n";
        print_r($e->getData());
    }
} 