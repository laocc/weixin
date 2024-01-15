<?php

namespace esp\weiXin\app;

use esp\http\HttpResult;
use Exception;

/**
 * 小程序发货管理
 *
 * 相关文档
 * https://developers.weixin.qq.com/miniprogram/dev/platform-capabilities/business-capabilities/order-shipping/order-shipping.html
 */
class Ship extends _Base
{
    /**
     * @param array $params
     * @return array|HttpResult|mixed|string|null
     * @throws Exception
     */
    public function send(array $params)
    {
        $value = [];
        $value['order_key'] = [
            'order_number_type' => 2,//订单单号类型,1=商户单号，2=微信支付单号
            'transaction_id' => $params['waybill'],
//            'mchid' => 0000,
//            'out_trade_no' => 0000,
        ];

        /**
         * 物流模式：
         * 1、快递公司物流配送
         * 2、同城配送
         * 3、虚拟商品，虚拟商品，例如话费充值，点卡等，无实体配送形式
         * 4、用户自提
         */
        $value['logistics_type'] = intval($params['type']);
        $value['delivery_mode'] = 1;//发货模式：1、UNIFIED_DELIVERY（统一发货）2、SPLIT_DELIVERY（分拆发货） 示例值: UNIFIED_DELIVERY
//        $value['is_all_delivered'] = true;//是否已全部发货完成,分拆发货模式时必填

        $value['upload_time'] = date(DATE_RFC3339);
        $item = ['item_desc' => $params['goods']];

        if ($value['logistics_type'] === 1) {//物流模式
            $expCarrier = [];
            $expCarrier['jitu'] = 'JTSD';
            $expCarrier['deppon'] = 'DBL';
            $expCarrier['yunda'] = 'YD';

            $item['tracking_no'] = $params['express']['waybill'];//快递单号
            $item['express_company'] = strtoupper($params['express']['company']);
            if (isset($expCarrier[$item['express_company']])) {
                $item['express_company'] = $expCarrier[$item['express_company']];
            }

            if (strtoupper($item['express_company']) === 'SF') {//顺丰必填
                $item['contact'] = [];
                if (isset($params['sender'])) $item['contact']['consignor_contact'] = $params['sender'];
                if (isset($params['receiver'])) $item['contact']['receiver_contact'] = $params['receiver'];
            }
        }
        $value['shipping_list'] = [$item];

        $value['payer'] = ['openid' => $params['openid']];

        $api = "/wxa/sec/order/upload_shipping_info?access_token={access_token}";
        return $this->Request($api, $value);
    }

    /**
     * 查询单个订单的发货状态
     *
     * @param array $params
     * @return array|HttpResult|mixed|string|null
     * @throws Exception
     */
    public function query(array $params)
    {
        $value = [];
        $value['transaction_id'] = $params['waybill'];

        $api = "/wxa/sec/order/get_order?access_token={access_token}";
        return $this->Request($api, $value);
    }

    /**
     * 查询订单列表
     *
     * @param array $params
     * @return array|string
     * @throws Exception
     */
    public function order(array $params)
    {
        $value = [];
        $value['pay_time_range'] = [//时间范围
            'begin_time' => $params['begin'] ?? 0,
            'end_time' => $params['end'] ?? time(),
        ];
        $value['page_size'] = 50;
        if (isset($params['state'])) $value['order_state'] = $params['state'];
        if (isset($params['openid'])) $value['openid'] = $params['openid'];
        if (isset($params['index'])) $value['last_index'] = $params['index'];
        if (isset($params['size'])) $value['page_size'] = $params['size'];

        $api = "/wxa/sec/order/get_order_list?access_token={access_token}";
        $orders = $this->Request($api, $value);
        if (is_string($orders)) return $orders;
        $result = [];
        foreach ($orders['order_list'] as $ord) {
            $result[] = [
                'waybill' => $ord['transaction_id'],
                'order' => $ord['merchant_trade_no'],
                'openid' => $ord['openid'],
                'state' => $ord['order_state'],//状态订单：(1) 待发货；(2) 已发货；(3) 确认收货；(4) 交易完成；(5) 已退款。
                'merchant' => $ord['merchant_id'],//商户
            ];
        }
        return $result;
    }

    /**
     * 五、确认收货提醒接口
     * 如你已经从你的快递物流服务方获知到用户已经签收相关商品，可以通过该接口提醒用户及时确认收货，以提高资金结算效率，每个订单仅可调用一次。
     * @param array $params
     * @return array|HttpResult|mixed|string|null
     * @throws Exception
     */
    public function confirm(array $params)
    {
        $value = [];
        $value['transaction_id'] = $params['waybill'];//支付单号
        $value['received_time'] = $params['sign_time'];//快递签收时间，时间戳形式。

        $api = "/wxa/sec/order/notify_confirm_receive?access_token={access_token}";
        return $this->Request($api, $value);
    }

    /**
     * 六、消息跳转路径设置接口
     * @param array $params
     * @return array|HttpResult|mixed|string|null
     * @throws Exception
     */
    public function set_jump_path(array $params)
    {
        $value = [];
        $value['path'] = $params['path'];

        $api = "/wxa/sec/order/set_msg_jump_path?access_token={access_token}";
        return $this->Request($api, $value);
    }

    /**
     * 七、查询小程序是否已开通发货信息管理服务
     * 八、查询小程序是否已完成交易结算管理确认
     *
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function state(array $params)
    {
        $value = [];
        $value['appid'] = $this->AppID;
        if (isset($params['appid'])) $value['appid'] = $params['appid'];

        $result = [];
        $result['trade'] = $this->Request('/wxa/sec/order/is_trade_managed?access_token={access_token}', $value);
        $result['confirmation'] = $this->Request('/wxa/sec/order/is_trade_management_confirmation_completed?access_token={access_token}', $value);

        return $result;
    }


    /**
     * 九、相关消息推送
     * 当产生交易或订单结算时，微信服务器会向开发者服务器、第三方平台方的消息与事件接收 URL 以 POST 的方式推送相关事件。注意，需要先接入 微信小程序消息推送服务 才能接收事件。
     * @return void
     */
    public function notify()
    {

    }


}