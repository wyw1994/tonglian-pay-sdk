<?php

return [
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