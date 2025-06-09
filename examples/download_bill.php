<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TonglianPay\TonglianPay;

// 配置信息
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

    // 对账单下载示例
    $params = [
        'day' => date('Y-m-d', strtotime('-1 day')), // 下载昨天的对账单
    ];

    // 发起对账单下载
    $result = $pay->downloadBill($params);
    echo "对账单下载成功：\n";
    echo "文件下载地址：" . ($result['fileUrl'] ?? '') . "\n";
    // 下载文件示例
    if (!empty($result['fileUrl'])) {
        $savePath = __DIR__ . '/bills/' . date('Ymd') . '.csv';
        if (!is_dir(dirname($savePath))) {
            mkdir(dirname($savePath), 0777, true);
        }

        // 下载文件
        $fileContent = file_get_contents($result['fileUrl']);
        if ($fileContent !== false) {
            file_put_contents($savePath, $fileContent);
            echo "文件已保存到：" . $savePath . "\n";
        } else {
            echo "文件下载失败\n";
        }
    }

} catch (\Exception $e) {
    echo "对账单下载失败：" . $e->getMessage() . "\n";
    if ($e->getCode()) {
        echo "错误代码：" . $e->getCode() . "\n";
    }
    if ($e->getData()) {
        echo "错误数据：\n";
        print_r($e->getData());
    }
} 