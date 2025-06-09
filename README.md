# 通联支付 SDK

## 安装

```bash
composer require wyw1994/tonglian-pay-sdk
```

## 配置说明

```php
$config = [
    'api_base_url' => 'https://tp.allinpay.com/pay/api', // 必填，API基础地址，用于构建完整的API请求URL
    'merchant_id' => 'E2******073',                      // 必填，商户号，用于标识商户身份
    'app_id' => '68******036',                          // 必填，应用ID，用于标识应用
    'merchant_key' => '2t******hk2jh9x5suw9lf1xfdnzwxixx', // 必填，商户密钥，用于签名和验签
    'sign_type' => 'MD5',                                // 必填，签名类型，支持：MD5/RSA/SM2
    'log_path' => '/path/to/logs/tonglianpay.log',       // 选填，日志路径，默认输出到标准输出
    'log_level' => \Monolog\Logger::DEBUG,               // 选填，日志级别，默认INFO
    'channelExtra' => [                                  // 选填，渠道公共参数，用于设置所有接口的默认渠道参数
        'cusid' => '66******M0C',                        // 收银宝子商户号，用于收银宝渠道接口
        'orgid' => '66******M3J',                        // 集团商户号，用于收银宝渠道接口
    ]
];
```

> 注意：`channelExtra` 公共参数可以在各个接口的 `params` 中通过 `channelExtra` 参数进行覆盖，接口中的参数优先级高于公共配置。

## 功能列表

### 1. 统一下单

```php
use TonglianPay\Utils\PayWayCode;

$params = [
    'mchOrderNo' => 'ORDER_202403210001',    // 必填，商户订单号，商户系统唯一标识
    'wayCode' => PayWayCode::WX_JSAPI,       // 必填，支付方式，使用PayWayCode常量
    'amount' => '100',                       // 必填，支付金额，单位分
    'currency' => 'CNY',                     // 选填，货币代码，默认CNY
    'clientIp' => '127.0.0.1',              // 选填，客户端IP，最大长度32个字符
    'notifyUrl' => 'https://example.com/notify', // 选填，支付结果通知地址，最大长度256个字符
    'body' => '商品描述',                    // 选填，商品描述，最大长度128个字符
    'channelExtra' => [                      // 选填，渠道参数，会覆盖公共配置
        'openid' => 'wx_openid',             // 微信支付必填，用户openid
        'subAppid' => 'wx_sub_appid'         // 微信支付选填，子商户appid
    ]
];

$result = $pay->unifiedOrder($params);
```

> 支付方式 `wayCode` 必须使用 `TonglianPay\Utils\PayWayCode` 类中定义的常量，常用常量包括：
> - `PayWayCode::WX_JSAPI`：微信JSAPI支付
> - `PayWayCode::WX_NATIVE`：微信扫码支付
> - `PayWayCode::WX_APP`：微信APP支付
> - `PayWayCode::WX_H5`：微信H5支付
> - `PayWayCode::ALI_JSAPI`：支付宝JSAPI支付
> - `PayWayCode::ALI_QR`：支付宝扫码支付

### 2. 获取小程序链接

```php
$params = [
    'mchOrderNo' => 'ORDER_202403210001',    // 必填，商户订单号，商户系统唯一标识
    'amount' => '100',                       // 必填，支付金额，单位分
    'notifyUrl' => 'https://example.com/notify', // 选填，支付结果通知地址，最大长度256个字符
    'body' => '商品描述'                     // 选填，商品描述，最大长度128个字符
];

$result = $pay->appletUrl($params);
```

### 3. 订单查询

```php
$params = [
    'mchOrderNo' => 'ORDER_202403210001',    // 与payOrderId二选一，商户订单号
    'payOrderId' => 'PAY_202403210001',      // 与mchOrderNo二选一，支付订单号
    'busiType' => '1'                        // 选填，业务类型，默认1：1-支付订单查询
];

$result = $pay->queryOrder($params);
```

### 4. 退款

```php
$params = [
    'mchRefundNo' => 'REFUND_202403210001',  // 必填，商户退款单号，商户系统唯一标识
    'mchOrderNo' => 'ORDER_202403210001',    // 与payOrderId二选一，商户订单号
    'payOrderId' => 'PAY_202403210001',      // 与mchOrderNo二选一，支付订单号
    'refundAmount' => 100,                   // 必填，退款金额，单位分
    'refundReason' => '退款原因',            // 必填，退款原因，最大长度64个字符
    'clientIp' => '127.0.0.1',              // 选填，客户端IP，最大长度32个字符
    'notifyUrl' => 'https://example.com/notify', // 选填，退款结果通知地址，最大长度128个字符
    'channelExtra' => [                      // 选填，渠道参数，会覆盖公共配置
        'divisionRefundInfo' => [            // 分账退款参数
            'refundAmount' => 100,           // 必填，退款金额
            'divisionBizMemNo' => '11',      // 必填，分账商户号
            'refundInfoList' => [            // 必填，分账退款列表
                [
                    'refundAmount' => 100,   // 必填，退款金额
                    'divisionBizMemNo' => '12' // 必填，分账商户号
                ]
            ]
        ]
    ]
];

$result = $pay->refund($params);
```

### 5. 退款订单查询

```php
$params = [
    'refundOrderId' => 'REFUND_202403210001', // 与mchRefundNo二选一，退款订单号
    'mchRefundNo' => 'REFUND_202403210001'    // 与refundOrderId二选一，商户退款单号
];

$result = $pay->queryRefund($params);
```

### 6. 统一余额查询

```php
$params = [
    'chanNo' => 'allinpay',                  // 必填，渠道类型：allinpay/yunst2isv
    'channelExtra' => [                      // 必填，渠道参数
        'orgid' => '66045xxxxxxx',          // 收银宝渠道必填，集团商户号
        'cusid' => '66046xxxxxxxxx'         // 收银宝渠道必填，子商户号
    ]
];

$result = $pay->queryBalance($params);
```

### 7. 对账单下载

```php
$params = [
    'day' => '2024-03-20'                    // 必填，交易日期，格式：yyyy-MM-dd，D+1日11点后可下载
];

$result = $pay->downloadBill($params);
```

### 8. 支付回调处理

```php
// 处理支付回调
$pay->handlePayNotify(function($data) {
    // 处理业务逻辑
    // $data 包含支付订单信息和回调数据
});

// 处理退款回调
$pay->handleRefundNotify(function($data) {
    // 处理业务逻辑
    // $data 包含退款订单信息和回调数据
});
```

#### 支付回调数据字段说明

```json
{
    "ifCode": "allinpay",                    // 接口代码
    "payOrderId": "P1930151278452752385",    // 支付订单号
    "mchOrderNo": "TEST202506041434439003",  // 商户订单号
    "sign": "3583DC7A0DD09A880B7C0BF28519DA6C", // 签名
    "channelOrderNo": "4200002411202506042388610282", // 渠道订单号
    "reqTime": "1749018906284",              // 请求时间，13位时间戳
    "body": "测试商品",                      // 商品描述
    "createdAt": "1749018900000",           // 订单创建时间，13位时间戳
    "channelFeeAmount": "0",                // 渠道手续费金额
    "appId": "68******036",    // 应用ID
    "successTime": "1749018906000",         // 支付成功时间，13位时间戳
    "signType": "MD5",                      // 签名类型
    "currency": "CNY",                      // 货币代码
    "state": "2",                           // 订单状态：2-支付成功
    "mchNo": "E2******073",              // 商户号
    "amount": "1",                          // 支付金额，单位分
    "mchName": "XXXXXXX有限责任公司", // 商户名称
    "wayCode": "WX_LITE",                   // 支付方式
    "channelAcctType": "99",                // 渠道账户类型
    "expiredTime": "2025-06-04 15:35:00",   // 订单过期时间
    "clientIp": "111.55.145.13",            // 客户端IP
    "channelUser": "oOZUg5UMCgXQ4dv_fA1Sj381hUWg" // 渠道用户标识（如微信openid）
}
```

#### 退款回调数据字段说明
```json
{
	"payOrderId": "P1930473932518940673",
	"extParam": "",
	"sign": "9500368F548125A81CA109A54A8373AF",
	"channelOrderNo": "4200002747202506054101758990",
	"reqTime": "1749096645942",
	"orgId": "",
	"refundOrderId": "R1930477364675960834",
	"createdAt": "1749096644930",
	"payAmount": "10",
	"channelFeeAmount": "0",
	"appId": "68******036",
	"mchRefundNo": "REFUND202506051210462389",
	"successTime": "1749096646000",
	"signType": "MD5",
	"currency": "cny",
	"state": "2",
	"channelPayOrderNo": "4200002747202506054101758990",
	"mchNo": "E2******073",
	"refundAmount": "9"
}

```
```json
{
    "payOrderId": "P1930151278452752385",    // 支付订单号
    "extParam": "",                          // 扩展参数
    "errMsg": "账户66******M0C00 贷方余额不足", // 错误信息
    "sign": "EE4BDABFD4727F42A6241107616F135B", // 签名
    "channelOrderNo": "4200002411202506042388610282", // 渠道订单号
    "reqTime": "1749091531746",              // 请求时间，13位时间戳
    "refundOrderId": "R1930455917696741377", // 退款订单号
    "createdAt": "1749091531572",           // 退款创建时间，13位时间戳
    "payAmount": "1",                        // 原支付金额，单位分
    "errCode": "3008",                       // 错误代码
    "appId": "68******036",    // 应用ID
    "mchRefundNo": "REFUND202506051045324593", // 商户退款单号
    "successTime": "1749091532000",         // 退款成功时间，13位时间戳
    "signType": "MD5",                      // 签名类型
    "currency": "cny",                      // 货币代码
    "state": "3",                           // 退款状态：2-退款成功，3-退款失败
    "channelPayOrderNo": "4200002411202506042388610282", // 渠道支付订单号
    "mchNo": "E2******073",              // 商户号
    "refundAmount": "1",                    // 退款金额，单位分
    "channelFeeAmount": "0"                 // 渠道手续费金额
}
```

> 注意：
> 1. 回调处理函数需要返回 'success' 字符串，否则系统会认为处理失败并重试
> 2. 建议在回调处理中进行幂等性处理，避免重复处理同一笔订单
> 3. 支付回调状态 `state`：2-支付成功
> 4. 退款回调状态 `state`：2-退款成功，3-退款失败
> 5. 所有金额单位为分
> 6. 时间戳使用13位毫秒级时间戳

## 错误处理

所有接口都会抛出异常，您需要捕获并处理这些异常：

```php
try {
    $result = $pay->unifiedOrder($params);
} catch (\Exception $e) {
    echo "操作失败：" . $e->getMessage() . "\n";
    if ($e->getCode()) {
        echo "错误代码：" . $e->getCode() . "\n";
    }
    if ($e->getData()) {
        echo "错误数据：\n";
        print_r($e->getData());
    }
}
```

## 日志记录

SDK 使用 Monolog 进行日志记录，您可以在配置中指定日志路径和级别：

```php
$config = [
    'log_path' => '/path/to/logs/tonglianpay.log', // 选填，日志路径，默认输出到标准输出
    'log_level' => \Monolog\Logger::DEBUG          // 选填，日志级别，默认INFO
];
```

日志级别说明：
- DEBUG：调试信息
- INFO：一般信息
- WARNING：警告信息
- ERROR：错误信息

## 注意事项

1. 所有金额单位为分
2. 时间戳使用13位毫秒级时间戳
3. 签名类型支持：MD5、RSA、SM2
4. 回调处理需要返回 'success' 字符串
5. 对账单下载需要在D+1日11点后进行

## 示例代码

更多示例代码请参考 `examples` 目录：

- `examples/unified_order.php`：统一下单示例
- `examples/applet_url.php`：获取小程序链接示例
- `examples/query_order.php`：订单查询示例
- `examples/refund.php`：退款示例
- `examples/query_refund.php`：退款订单查询示例
- `examples/query_balance.php`：余额查询示例
- `examples/download_bill.php`：对账单下载示例 