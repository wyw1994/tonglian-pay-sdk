<?php

namespace TonglianPay;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use TonglianPay\Exception\InvalidConfigException;
use TonglianPay\Exception\NotifyException;
use TonglianPay\Exception\PayException;
use TonglianPay\Exception\RefundException;
use TonglianPay\Utils\HttpClient;
use TonglianPay\Utils\OurpayKit;
use TonglianPay\Utils\RequestParams;
use TonglianPay\Utils\SignType;

class TonglianPay
{
    private HttpClient $client;
    private Logger $logger;
    private array $config;

    /**
     * @param array $config 配置参数
     * @throws InvalidConfigException
     */
    public function __construct(array $config)
    {
        $this->validateConfig($config);
        $this->config = $config;
        $this->initClient();
        $this->initLogger();
    }

    /**
     * 验证配置参数
     * @param array $config
     * @throws InvalidConfigException
     */
    private function validateConfig(array $config): void
    {
        $required = ['api_base_url', 'merchant_id', 'merchant_key', 'sign_type'];
        $missing = array_diff($required, array_keys($config));

        if (!empty($missing)) {
            throw new InvalidConfigException(
                sprintf('缺少必要的配置参数: %s', implode(', ', $missing)),
                1001,
                ['missing_params' => $missing]
            );
        }

        // 验证签名类型
        if (!in_array($config['sign_type'], [SignType::MD5, SignType::RSA, SignType::SM2])) {
            throw new InvalidConfigException(
                '不支持的签名类型：' . $config['sign_type'],
                1002,
                ['sign_type' => $config['sign_type']]
            );
        }

        // 如果是RSA或SM2签名，需要验证密钥
        if (in_array($config['sign_type'], [SignType::RSA, SignType::SM2])) {
            if (!isset($config['private_key']) || !isset($config['public_key'])) {
                throw new InvalidConfigException(
                    'RSA/SM2签名需要提供私钥和公钥',
                    1003,
                    ['sign_type' => $config['sign_type']]
                );
            }
        }
    }

    /**
     * 初始化HTTP客户端
     */
    private function initClient(): void
    {
        $this->client = new HttpClient([
            'timeout' => $this->config['timeout'] ?? 30,
            'verify_ssl' => $this->config['verify_ssl'] ?? true,
        ]);
    }

    /**
     * 初始化日志
     */
    private function initLogger(): void
    {
        $this->logger = new Logger('tonglianpay');
        $this->logger->pushHandler(new StreamHandler(
            $this->config['log_path'] ?? 'php://stdout',
            $this->config['log_level'] ?? Logger::INFO
        ));
    }

    /**
     * 发送请求
     * @param string $url 请求地址
     * @param array $params 请求参数
     * @return array
     * @throws PayException
     */
    private function request(string $url, array $params): array
    {
        try {
            // 添加公共参数
            $params['mchNo'] = $this->config['merchant_id'];
            $params['orgId'] = $this->config['orgId'] ?? '';
            $params['appId'] = $this->config['app_id'] ?? '';
            $params['reqTime'] = (string)(time() * 1000);
            $params['version'] = '1.0';
            $params['signType'] = $this->config['sign_type'];
            //channelExtra，如果params中存在channelExtra，则将$this->config['channelExtra']合并到params中的channelExtra中，以params为准，优先以params为准
            //如果params中不存在channelExtra，则将$this->config['channelExtra']合并到params的channelExtra中
            if (isset($params['channelExtra']) && is_array($params['channelExtra'])) {
                if ($params['channelExtra']) {
                    $params['channelExtra'] = array_merge($this->config['channelExtra'] ?? [], $params['channelExtra']);
                } else {
                    $params['channelExtra'] = [];
                }
            } else {
                $params['channelExtra'] = $this->config['channelExtra'] ?? [];
            }
            if (isset($params['channelExtra'])) {
                $params['channelExtra'] = $params['channelExtra'] ? json_encode($params['channelExtra']) : '';
            }
            // 生成签名
            $params['sign'] = OurpayKit::getSign(
                $params,
                $this->config['sign_type'],
                $this->getSignKey()
            );

            // 构建完整URL
            $fullUrl = rtrim($this->config['api_base_url'], '/') . '/' . ltrim($url, '/');
            // 发送请求
            //exit(json_encode($params, JSON_UNESCAPED_UNICODE));
            $response = $this->client->postJson($fullUrl, $params);

            if (!$response->isSuccess()) {
                throw new PayException(
                    '请求失败：' . implode(', ', $response->getErrors()),
                    2001,
                    ['response' => $response->getBody()]
                );
            }

            $data = $response->getBody();

            // 验证响应签名
            if (isset($data['data']) && isset($data['sign'])) {
                $responseData = $data['data'];
                $responseData['sign'] = $data['sign'];

                if (!OurpayKit::checkSign(
                    $responseData,
                    $this->config['sign_type'],
                    $this->getVerifyKey(),
                    $this->config['cert_id'] ?? null
                )) {
                    throw new PayException('响应签名验证失败');
                }
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('请求失败', [
                'url' => $url,
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new PayException('请求失败：' . $e->getMessage());
        }
    }

    /**
     * 获取签名密钥
     * @return string
     */
    private function getSignKey(): string
    {
        return match ($this->config['sign_type']) {
            SignType::MD5 => $this->config['merchant_key'],
            SignType::RSA, SignType::SM2 => $this->config['private_key'],
            default => throw new PayException('不支持的签名类型：' . $this->config['sign_type'])
        };
    }

    /**
     * 获取验证密钥
     * @return string
     */
    private function getVerifyKey(): string
    {
        return match ($this->config['sign_type']) {
            SignType::MD5 => $this->config['merchant_key'],
            SignType::RSA, SignType::SM2 => $this->config['public_key'],
            default => throw new PayException('不支持的签名类型：' . $this->config['sign_type'])
        };
    }

    /**
     * 统一下单
     * @param array $params 下单参数
     * @return array
     * @throws PayException
     *
     * 参数说明：
     * - mchOrderNo: string, 必填, 商户订单号，商户系统唯一标识
     * - wayCode: string, 必填, 支付方式，参考 PayWayCode 类
     * - amount: string, 必填, 支付金额，单位分
     * - currency: string, 选填, 货币代码，默认CNY，支持：CNY-人民币，USD-美元
     * - clientIp: string, 选填, 客户端IP，最大长度32个字符
     * - notifyUrl: string, 选填, 支付结果通知地址，最大长度256个字符
     * - body: string, 选填, 商品描述，最大长度128个字符
     * - channelExtra: array, 选填, 渠道参数，特定渠道发起的额外参数
     *   - 微信支付：需要传入 openid, subAppid 等
     *   - 支付宝：需要传入 buyerId 等
     *   - 银联：需要传入 cardNo 等
     */
    public function unifiedOrder(array $params): array
    {
        $requestParams = [
            'mchOrderNo' => $params['mchOrderNo'],
            'wayCode' => $params['wayCode'] ?? '',
            'amount' => (string)$params['amount'],
            'currency' => $params['currency'] ?? 'CNY',
            'clientIp' => $params['clientIp'] ?? '',
            'notifyUrl' => $params['notifyUrl'] ?? '',
            'body' => $params['body'] ?? '',
            'channelExtra' => $params['channelExtra'] ?? []
        ];

        $response = $this->request('/pay/unifiedOrder', $requestParams);

        if ($response['code'] != 0) {
            throw new PayException(
                $response['msg'] ?? '统一下单失败',
                $response['code'] ?? 'UNKNOWN_ERROR',
                $response
            );
        }

        return $response['data'] ?? [];
    }

    /**
     * 获取小程序链接
     * @param array $params 下单参数
     * @return array
     * @throws PayException
     *
     * 参数说明：
     * - mchOrderNo: string, 必填, 商户订单号，商户系统唯一标识
     * - amount: string, 必填, 支付金额，单位分
     * - notifyUrl: string, 选填, 支付结果通知地址，最大长度256个字符
     * - body: string, 选填, 商品描述，最大长度128个字符
     * - urlType: string, 选填, 链接类型，默认URL_Common
     */
    public function appletUrl(array $params): array
    {
        $requestParams = [
            'mchOrderNo' => $params['mchOrderNo'],
            'amount' => (string)$params['amount'],
            'notifyUrl' => $params['notifyUrl'] ?? '',
            'body' => $params['body'] ?? '',
            'urlType' => 'URL_Common',
            'channelExtra' => $params['channelExtra'] ?? []
        ];

        $response = $this->request('/applet/getAppletUrl', $requestParams);
        if ($response['code'] != 0) {
            throw new PayException(
                $response['msg'] ?? '获取小程序链接失败',
                $response['code'] ?? 'UNKNOWN_ERROR',
                $response
            );
        }

        return $response['data'] ?? [];
    }

    /**
     * 退款接口
     * @param array $params 退款参数
     * @return array
     * @throws RefundException
     *
     * 参数说明：
     * - mchRefundNo: string, 必填, 商户退款单号，商户平台唯一
     * - mchOrderNo: string, 与payOrderId二选一, 商户订单号，针对采用统一下单接口且支付方式为微信预消费的订单，建议使用商户订单号发起退款
     * - payOrderId: string, 与mchOrderNo二选一, 支付订单号
     * - refundAmount: int, 必填, 渠道退款金额，单位分
     * - currency: string, 必填, 货币代码，人民币:cny
     * - refundReason: string, 必填, 退款原因，最大长度64个字符
     * - clientIp: string, 选填, 客户端IP，最大长度32个字符
     * - notifyUrl: string, 选填, 异步通知地址，最大长度128个字符
     * - channelExtra: string, 选填, 渠道参数，特定渠道发起的额外参数，JSON格式，最大长度256个字符
     *   - 分账退款示例：{"divisionRefundInfo":{"refundAmount":0,"divisionBizMemNo":"11","refundCouponAmount":0,"refundInfoList":[{"refundAmount":0,"divisionBizMemNo":"12"}]}}
     * - extParam: string, 选填, 商户扩展参数，回调时会原样返回，最大长度512个字符
     *
     * 返回数据说明：
     * - refundOrderId: string, 退款订单号
     * - mchRefundNo: string, 商户退款单号
     * - payAmount: int, 支付金额，单位分
     * - refundAmount: int, 退款金额，单位分
     * - state: int, 订单状态：0-订单生成 1-退款中 2-退款成功 3-退款失败 4-退款关闭 6-预消费退款
     * - channelOrderNo: string, 渠道退款单号
     * - errCode: string, 渠道错误码
     * - errMsg: string, 渠道错误描述
     */
    public function refund(array $params): array
    {
        // 验证必填参数
        if (empty($params['mchRefundNo'])) {
            throw new RefundException('商户退款单号不能为空');
        }
        if (empty($params['mchOrderNo']) && empty($params['payOrderId'])) {
            throw new RefundException('商户订单号和支付订单号不能同时为空');
        }
        if (!isset($params['refundAmount']) || $params['refundAmount'] <= 0) {
            throw new RefundException('退款金额必须大于0');
        }
        if (empty($params['refundReason'])) {
            throw new RefundException('退款原因不能为空');
        }

        $requestParams = [
            'mchRefundNo' => $params['mchRefundNo'],
            'mchOrderNo' => $params['mchOrderNo'] ?? '',
            'payOrderId' => $params['payOrderId'] ?? '',
            'refundAmount' => (int)$params['refundAmount'],
            'currency' => $params['currency'] ?? 'cny',
            'refundReason' => $params['refundReason'],
            'clientIp' => $params['clientIp'] ?? '',
            'notifyUrl' => $params['notifyUrl'] ?? '',
            'channelExtra' => $params['channelExtra'] ?? [],
            'extParam' => $params['extParam'] ?? ''
        ];

        // 如果传入了服务商号，则添加到请求参数中
        if (!empty($this->config['org_id'])) {
            $requestParams['orgId'] = $this->config['org_id'];
        }

        try {
            $response = $this->request('/refund/refundOrder', $requestParams);

            if ($response['code'] != 0) {
                throw new RefundException(
                    $response['msg'] ?? '退款失败',
                    $response['code'] ?? 'UNKNOWN_ERROR',
                    $response
                );
            }

            $data = $response['data'] ?? [];
            // 检查退款状态
            if (isset($data['state'])) {
                switch ($data['state']) {
                    case 2: // 退款成功
                        $this->logger->info('退款成功', [
                            'refund_order_id' => $data['refundOrderId'],
                            'mch_refund_no' => $data['mchRefundNo'],
                            'amount' => $data['refundAmount']
                        ]);
                        break;
                    case 3: // 退款失败
                        $errorMsg = sprintf(
                            '退款失败：%s（错误码：%s）',
                            $data['errMsg'] ?? '未知错误',
                            $data['errCode'] ?? 'UNKNOWN'
                        );
                        $this->logger->error($errorMsg, [
                            'refund_order_id' => $data['refundOrderId'],
                            'mch_refund_no' => $data['mchRefundNo'],
                            'amount' => $data['refundAmount'],
                            'err_code' => $data['errCode'] ?? '',
                            'err_msg' => $data['errMsg'] ?? ''
                        ]);
                        throw new RefundException($errorMsg, 3003, $data);
                    case 4: // 退款关闭
                        $this->logger->warning('退款已关闭', [
                            'refund_order_id' => $data['refundOrderId'],
                            'mch_refund_no' => $data['mchRefundNo']
                        ]);
                        throw new RefundException('退款已关闭', 3004, $data);
                    case 6: // 预消费退款
                        $this->logger->info('预消费退款', [
                            'refund_order_id' => $data['refundOrderId'],
                            'mch_refund_no' => $data['mchRefundNo']
                        ]);
                        break;
                    default: // 其他状态（0-订单生成，1-退款中）
                        $this->logger->info('退款处理中', [
                            'refund_order_id' => $data['refundOrderId'],
                            'mch_refund_no' => $data['mchRefundNo'],
                            'state' => $data['state']
                        ]);
                }
            }

            return $data;
        } catch (\Exception $e) {
            $this->logger->error('退款失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new RefundException($e->getMessage(), 3001, $params);
        }
    }

    /**
     * 订单查询接口
     * @param array $params 查询参数
     * @return array
     * @throws PayException
     *
     * 参数说明：
     * - mchOrderNo: string, 与payOrderId二选一, 商户订单号
     * - payOrderId: string, 与mchOrderNo二选一, 支付订单号
     * - busiType: string, 选填, 业务类型，不传默认为1：
     *   1-支付订单查询
     *   4-提现订单查询
     *   5-转账订单查询
     *
     * 返回数据说明：
     * - mchNo: string, 商户号
     * - appId: string, 应用ID
     * - payOrderId: string, 支付订单号
     * - mchOrderNo: string, 商户订单号
     * - ifCode: string, 支付接口
     * - wayCode: string, 支付方式
     * - amount: int, 支付金额，单位分
     * - currency: string, 货币代码，人民币:cny
     * - state: int, 订单状态：0-订单生成 1-支付中 2-支付成功 3-支付失败 4-已撤销 5-已退款 6-订单关闭
     * - clientIp: string, 客户端IP
     * - body: string, 商品描述
     * - channelOrderNo: string, 渠道订单号
     * - errCode: string, 渠道错误码
     * - errMsg: string, 渠道错误描述
     * - extParam: string, 扩展参数
     * - createdAt: long, 订单创建时间，13位时间戳
     * - successTime: long, 订单支付成功时间，13位时间戳
     * - rSuccessTime: long, 订单退款成功时间，13位时间戳
     * - channelFeeAmount: long, 渠道返回的手续费，单位：分
     * - channelUser: string, 付款人账号
     * - channelUserName: string, 付款人户名
     * - channelAcctType: string, 账户类型：00-借记卡 02-信用卡 99-其他（花呗/余额等）
     */
    public function queryOrder(array $params): array
    {
        // 验证必填参数
        if (empty($params['mchOrderNo']) && empty($params['payOrderId'])) {
            throw new PayException('商户订单号和支付订单号不能同时为空');
        }

        $requestParams = [
            'mchOrderNo' => $params['mchOrderNo'] ?? '',
            'payOrderId' => $params['payOrderId'] ?? '',
            'busiType' => $params['busiType'] ?? '1',
            'channelExtra' => []
        ];

        try {
            $response = $this->request('/pay/query', $requestParams);

            if ($response['code'] != 0) {
                throw new PayException(
                    $response['msg'] ?? '订单查询失败',
                    $response['code'] ?? 'UNKNOWN_ERROR',
                    $response
                );
            }

            return $response['data'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('订单查询失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new PayException($e->getMessage(), 2002, $params);
        }
    }

    /**
     * 对账单下载接口
     * @param array $params 下载参数
     * @return array
     * @throws PayException
     *
     * 参数说明：
     * - day: string, 必填, 交易日期，格式：yyyy-MM-dd，D+1日11点之后可下载D日的交易对账文件
     *
     * 返回数据说明：
     * - fileUrl: string, 对账文件链接，下载文件格式为csv
     */
    public function downloadBill(array $params): array
    {
        // 验证必填参数
        if (empty($params['day'])) {
            throw new PayException('交易日期不能为空');
        }

        // 验证日期格式
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['day'])) {
            throw new PayException('交易日期格式不正确，应为：yyyy-MM-dd');
        }

        // 验证是否可以下载（D+1日11点之后可下载D日的交易对账文件）
        $billDate = strtotime($params['day']);
        $now = time();
        $nextDay11am = strtotime(date('Y-m-d', $billDate + 86400) . ' 11:00:00');

        if ($now < $nextDay11am) {
            throw new PayException('对账单暂未生成，请于D+1日11点后下载');
        }

        $requestParams = [
            'day' => $params['day'],
        ];

        try {
            $response = $this->request('/accountstatement/getOrderFile', $requestParams);

            if ($response['code'] != 0) {
                throw new PayException(
                    $response['msg'] ?? '对账单下载失败',
                    $response['code'] ?? 'UNKNOWN_ERROR',
                    $response
                );
            }

            // 记录下载日志
            $this->logger->info('对账单下载成功', [
                'day' => $params['day'],
                'file_url' => $response['data']['fileUrl'] ?? '',
                'org_id' => $params['orgId'] ?? ''
            ]);

            return $response['data'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('对账单下载失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new PayException($e->getMessage(), 2003, $params);
        }
    }

    /**
     * 查询退款订单
     * @param array $params 查询参数
     * @return array
     * @throws RefundException
     *
     * 参数说明：
     * - refundOrderId: string, 与mchRefundNo二选一, 退款订单号
     * - mchRefundNo: string, 与refundOrderId二选一, 商户退款单号
     *
     * 返回数据说明：
     * - mchNo: string, 商户号
     * - appId: string, 应用ID
     * - refundOrderId: string, 退款订单号
     * - payOrderId: string, 支付订单号
     * - mchRefundNo: string, 商户退款单号
     * - payAmount: int, 支付金额，单位分
     * - refundAmount: int, 退款金额，单位分
     * - currency: string, 货币代码，人民币:cny
     * - state: int, 退款状态：0-订单生成 1-退款中 2-退款成功 3-退款失败 4-退款关闭 6-预消费退款
     * - channelOrderNo: string, 渠道订单号
     * - errCode: string, 渠道错误码
     * - errMsg: string, 渠道错误描述
     * - extParam: string, 扩展参数
     * - createdAt: long, 订单创建时间，13位时间戳
     * - successTime: long, 订单支付成功时间，13位时间戳
     * - channelFeeAmount: long, 渠道返回的手续费，单位：分
     */
    public function queryRefund(array $params): array
    {
        // 验证必填参数
        if (empty($params['refundOrderId']) && empty($params['mchRefundNo'])) {
            throw new RefundException('退款订单号和商户退款单号不能同时为空');
        }

        $requestParams = [
            'refundOrderId' => $params['refundOrderId'] ?? '',
            'mchRefundNo' => $params['mchRefundNo'] ?? ''
        ];

        try {
            $response = $this->request('/refund/query', $requestParams);

            if ($response['code'] != 0) {
                throw new RefundException(
                    $response['msg'] ?? '退款订单查询失败',
                    $response['code'] ?? 'UNKNOWN_ERROR',
                    $response
                );
            }

            return $response['data'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('退款订单查询失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new RefundException($e->getMessage(), 3002, $params);
        }
    }

    /**
     * 处理支付回调
     * @param callable|null $callback 业务处理回调函数，接收订单数据作为参数
     * @return string 返回success表示处理成功
     * @throws NotifyException 支付回调处理异常
     * @throws \Throwable 业务处理异常
     */
    public function handlePayNotify(?callable $callback = null): string
    {
        // 获取所有数据数据
        $requestParams = new RequestParams();
        $data = $requestParams->params();

        try {
            // 验证签名
            if (!OurpayKit::checkSign(
                $data,
                $this->config['sign_type'],
                $this->getVerifyKey(),
                $this->config['cert_id'] ?? null
            )) {
                throw new NotifyException('支付回调签名验证失败');
            }

            // 验证商户号
            if (!isset($data['mchNo']) || $data['mchNo'] != $this->config['merchant_id']) {
                throw new NotifyException('商户号不匹配');
            }

            // 验证应用ID
            if (!isset($data['appId']) || $data['appId'] != $this->config['app_id']) {
                throw new NotifyException('应用ID不匹配');
            }

            // 查询订单状态
            $orderInfo = $this->queryOrder([
                'payOrderId' => $data['payOrderId']
            ]);

            // 验证订单状态
            if (!isset($orderInfo['state']) || $orderInfo['state'] != 2) {
                throw new NotifyException('订单未支付成功');
            }

            // 验证订单金额
            if (!isset($orderInfo['amount']) || $orderInfo['amount'] != $data['amount']) {
                throw new NotifyException('订单金额不匹配');
            }

            // 记录通知日志
            $this->logger->info('收到支付回调通知', [
                'data' => $data,
                'order_info' => $orderInfo,
                'merchant_id' => $this->config['merchant_id'],
                'app_id' => $this->config['app_id']
            ]);

            // 合并订单查询结果和回调数据
            $result = array_merge($data, [
                'order_info' => $orderInfo
            ]);

            // 执行业务回调
            if ($callback !== null) {
                $callback($result);
            }

            return 'success';
        } catch (NotifyException $e) {
            // 支付回调处理异常
            $this->logger->error('支付回调处理失败', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Throwable $e) {
            // 其他异常（包括业务回调异常）
            $this->logger->error('业务处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 处理退款回调
     * @param callable|null $callback 业务处理回调函数，接收退款数据作为参数
     * @return string 返回success表示处理成功
     * @throws NotifyException 退款回调处理异常
     * @throws \Throwable 业务处理异常
     */
    public function handleRefundNotify(?callable $callback = null): string
    {
        // 获取所有数据数据
        $requestParams = new RequestParams();
        $data = $requestParams->params();

        try {
            // 验证签名
            if (!OurpayKit::checkSign(
                $data,
                $this->config['sign_type'],
                $this->getVerifyKey(),
                $this->config['cert_id'] ?? null
            )) {
                throw new NotifyException('退款回调签名验证失败');
            }

            // 验证商户号
            if (!isset($data['mchNo']) || $data['mchNo'] != $this->config['merchant_id']) {
                throw new NotifyException('商户号不匹配');
            }

            // 验证应用ID
            if (!isset($data['appId']) || $data['appId'] != $this->config['app_id']) {
                throw new NotifyException('应用ID不匹配');
            }

            // 查询退款订单状态
            $refundInfo = $this->queryRefund([
                'refundOrderId' => $data['refundOrderId']
            ]);

            // 验证退款状态
            if (!isset($refundInfo['state']) || !in_array($refundInfo['state'], [2, 3, 6])) {
                //退款失败的状态不做处理，直接返回正常
                return 'success';
                //throw new NotifyException('退款状态不正确');
            }

            // 验证退款金额
            if (!isset($refundInfo['refundAmount']) || $refundInfo['refundAmount'] != $data['refundAmount']) {
                throw new NotifyException('退款金额不匹配');
            }

            // 记录通知日志
            $this->logger->info('收到退款回调通知', [
                'data' => $data,
                'refund_info' => $refundInfo,
                'merchant_id' => $this->config['merchant_id'],
                'app_id' => $this->config['app_id']
            ]);

            // 合并退款查询结果和回调数据
            $result = array_merge($data, [
                'refund_info' => $refundInfo
            ]);

            // 执行业务回调
            if ($callback !== null) {
                $callback($result);
            }

            return 'success';
        } catch (NotifyException $e) {
            // 退款回调处理异常
            $this->logger->error('退款回调处理失败', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Throwable $e) {
            // 其他异常（包括业务回调异常）
            $this->logger->error('业务处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 统一余额查询
     * @param array $params 查询参数
     * @return array
     * @throws PayException
     *
     * 参数说明：
     * - chanNo: string, 必填, 渠道:
     *   - allinpay：收银宝渠道
     *   - yunst2isv：特色结算渠道
     * - channelExtra: string, 必填, 渠道参数，JSON字符串
     *   - allinpay渠道需要指定提现商户号，如：{"orgid":"66045xxxxxxx","cusid":"66046xxxxxxxxx"}
     *   - yunst2isv渠道需要指定会员及账户类型，{"bizUserId":"BS1738808XXX","acctType":"08"}
     *   - acctType取值：08-待结算户
     *
     * 返回数据说明：
     * - amount: string, 总余额，单位元
     * - cusid: string, 子商户号（allinpay渠道必返）
     * - availableAmt: string, 可用余额（yunst2isv渠道必返）
     * - transitAmt: string, 在途余额（yunst2isv渠道必返）
     */
    public function queryBalance(array $params): array
    {
        // 验证必填参数
        if (empty($params['chanNo'])) {
            throw new PayException('渠道不能为空');
        }

        $requestParams = [
            'chanNo' => $params['chanNo'] ?? 'allinpay',
            'channelExtra' => $params['channelExtra'] ?? [],
        ];

        try {
            $response = $this->request('/balance/query', $requestParams);

            if ($response['code'] != 0) {
                throw new PayException(
                    $response['msg'] ?? '余额查询失败',
                    $response['code'] ?? 'UNKNOWN_ERROR',
                    $response
                );
            }

            return $response['data'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error('余额查询失败', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw new PayException($e->getMessage(), 2004, $params);
        }
    }
} 