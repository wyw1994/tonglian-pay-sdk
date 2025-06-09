<?php

namespace TonglianPay\Utils;

/**
 * 支付方式枚举类
 */
class PayWayCode
{
    /**
     * 微信预消费
     */
    public const WX_TRANS = 'WX_TRANS';

    /**
     * 微信小程序支付
     */
    public const WX_LITE = 'WX_LITE';

    /**
     * 微信公众号支付
     */
    public const WX_JSAPI = 'WX_JSAPI';

    /**
     * 支付宝公众号支付
     */
    public const ALI_JSAPI = 'ALI_JSAPI';

    /**
     * 支付宝主扫（吱口令）
     */
    public const ALI_QR = 'ALI_QR';

    /**
     * 微信主扫
     */
    public const WX_NATIVE = 'WX_NATIVE';

    /**
     * 微企付
     */
    public const YUNST2_WQF_ZZHK = 'YUNST2_WQF_ZZHK';

    /**
     * H5收银台
     */
    public const H5_CASHIER = 'H5_CASHIER';

    /**
     * 付款码支付
     */
    public const AUTO_BAR = 'AUTO_BAR';

    /**
     * B2B订单付款
     */
    public const B2B_ORDER_PAY = 'B2B_ORDER_PAY';

    /**
     * 云微支付
     */
    public const YW_PAY = 'YW_PAY';

    /**
     * 白条分期
     */
    public const JDBT_PAY = 'JDBT_PAY';

    /**
     * 获取支付方式说明
     * @return array
     */
    public static function getPayWayDescriptions(): array
    {
        return [
            self::WX_TRANS => [
                'name' => '微信预消费',
                'channelExtra' => [
                    'openid' => '用户微信openid',
                    'subAppid' => '微信小程序appid'
                ]
            ],
            self::WX_LITE => [
                'name' => '微信小程序支付',
                'channelExtra' => [
                    'openid' => '用户微信openid',
                    'subAppid' => '微信小程序appid'
                ]
            ],
            self::WX_JSAPI => [
                'name' => '微信公众号支付',
                'channelExtra' => [
                    'openid' => '用户微信openid',
                    'subAppid' => '微信公众号appid'
                ]
            ],
            self::ALI_JSAPI => [
                'name' => '支付宝公众号支付',
                'channelExtra' => [
                    'buyerUserId' => '用户的支付宝user_id'
                ]
            ],
            self::ALI_QR => [
                'name' => '支付宝主扫（吱口令）',
                'channelExtra' => '支付宝扫码支付，可配置吱口令，或普通主扫'
            ],
            self::WX_NATIVE => [
                'name' => '微信主扫',
                'channelExtra' => '微信扫码支付，用户主扫，下单后返回支付链接，可生成支付二维码'
            ],
            self::YUNST2_WQF_ZZHK => [
                'name' => '微企付',
                'channelExtra' => [
                    'goodsinfo' => '商品信息，ARRAYObject数组',
                    'frontCallbackUrl' => '前端回跳地址信息，JSONObject字符串'
                ]
            ],
            self::H5_CASHIER => [
                'name' => 'H5收银台',
                'channelExtra' => '收银宝H5收银台，下单后返回支付链接'
            ],
            self::AUTO_BAR => [
                'name' => '付款码支付',
                'channelExtra' => [
                    'authcode' => '支付授权码',
                    'terminfo' => '终端信息，JSONObject字符串'
                ]
            ],
            self::B2B_ORDER_PAY => [
                'name' => 'B2B订单付款',
                'channelExtra' => [
                    'goodsinfo' => '商品信息，ARRAYObject数组',
                    'acctno' => '收款账号',
                    'acctname' => '收款户名',
                    'accttype' => '收款账户类型 1-对公',
                    'bankcode' => '收款银行代码'
                ]
            ],
            self::YW_PAY => [
                'name' => '云微支付',
                'channelExtra' => [
                    'apptype' => '交易发起场景 03-小程序, 04-公众号',
                    'appname' => '小程序ID或公众号的ID',
                    'truename' => '付款人姓名',
                    'idno' => '付款人证件号',
                    'extendparams' => '渠道拓展参数'
                ]
            ],
            self::JDBT_PAY => [
                'name' => '白条分期',
                'channelExtra' => [
                    'fqnum' => '分期期数'
                ]
            ]
        ];
    }

    /**
     * 获取支付方式名称
     * @param string $wayCode
     * @return string
     */
    public static function getName(string $wayCode): string
    {
        $descriptions = self::getPayWayDescriptions();
        return $descriptions[$wayCode]['name'] ?? '未知支付方式';
    }

    /**
     * 获取支付方式所需参数说明
     * @param string $wayCode
     * @return array|string
     */
    public static function getChannelExtra(string $wayCode): array|string
    {
        $descriptions = self::getPayWayDescriptions();
        return $descriptions[$wayCode]['channelExtra'] ?? [];
    }
} 