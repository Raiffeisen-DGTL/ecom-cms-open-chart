<?php

require_once(DIR_SYSTEM.'library/raiffeisen/ClientException.php');
require_once(DIR_SYSTEM.'library/raiffeisen/Client.php');

class ControllerExtensionPaymentRaiffeisen extends Controller
{
    private $pay_url = '/pay';

    private static function getStyle($str) {
        $re = '/{ ( (?: [^{}]* | (?R) )* ) }/x';
        preg_match_all($re, $str, $matches);

        if (isset($matches[0], $matches[0][0]) && !empty($matches)) {
            $str = rtrim($matches[0][0], '}');
            $str = ltrim($str, '{');

            preg_match_all($re, $str, $matchesTwo, PREG_SET_ORDER, 0);

            if (isset($matchesTwo, $matchesTwo[0], $matchesTwo[0][0]) && !empty($matchesTwo[0][0])) {
                return $matchesTwo[0][0];
            }
        }

        return false;
    }

    /**
     * Generate payment form
     * @return [type] [description]
     */
    public function index()
    {
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['button_back'] = $this->language->get('button_back');
        $data['action'] = $this->config->get('payment_raiffeisen_env_url').$this->pay_url;
        $data['raiffeisen_js'] = '';
        $data['isPopup'] = false;
        $data['formClass'] = 'checkout';
        $data['raiffeisen_css'] = false;

        $isPopup = $this->config->get('payment_raiffeisen_popup') ?? false;

        if ($isPopup) {
            $data['raiffeisen_css'] = true;
            $data['isPopup'] = $isPopup;
            $data['raiffeisen_js'] = 'https://pay.raif.ru/pay/sdk/v2/payment.min.js';
            $data['formClass'] = 'checkout popUpBank';

            if ($this->config->get('payment_raiffeisen_css')) {
                $data['js_popup_style'] = self::getStyle($this->config->get('payment_raiffeisen_css'));
            }
        }

        $this->load->language('extension/payment/raiffeisen');

        $this->load->model('extension/payment/raiffeisen');

        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $order_products = $this->cart->getProducts();

        // Продукты в заказе
        // Сумма для продуктов
        $product_amount = 0;

        // Product and deliveries items
        $items = [];

        // Payment method universal for products and deliveries
        $paymentMode = 'FULL_PREPAYMENT';
        $productUnit = $this->config->get('payment_raiffeisen_unit_products');
        $productObject = $this->config->get('payment_raiffeisen_object_products');
        $productVatMode = 'NONE';

        if ($order_products) {
            foreach ($order_products as $order_product) {
                $product_amount += $order_product['price'] * $order_product['quantity'];
                $items[] = [
                    'name' => $order_product['name'],
                    'price' => (float) number_format($order_product['price'],
                        2, '.', ''),
                    'quantity' => (int) number_format( $order_product['quantity'],
                        3, '.', ''),
                    'amount' => (float) number_format($order_product['price'] * $order_product['quantity'],
                        2, '.', ''),
                    'paymentObject' => $productObject,
                    'paymentMode' => $paymentMode,
                    'measurementUnit' => $productUnit,
                    'vatType' => $productVatMode,
                ];
            }
        }

        $deliveryUnit = $this->config->get('payment_raiffeisen_unit_delivery');
        $deliveryObject = $this->config->get('payment_raiffeisen_object_delivery');
        $deliveryVatMode = 'NONE';

        $items[] = [
            'name' => isset($order['shipping_method']) ? $order['shipping_method'] : 'Shipping',
            'price' => (float) (float) number_format($order['total'] - $product_amount,
                2, '.', ''),
            'quantity' => '1.000',
            'amount' => (float) number_format($order['total'] - $product_amount,
                2, '.', ''),
            'measurementUnit' => $deliveryUnit,
            'paymentObject' => $deliveryObject,
            'paymentMethod' => $paymentMode,
            'vatType' => $deliveryVatMode,
        ];

        $this->load->model('checkout/order');

        $real_plugin_url = str_replace('/admin', '', $this->url->link('', '', true));
        $orderId = $this->session->data['order_id'];

        $payment_notification_url = $real_plugin_url.'extension/payment/raiffeisen/callback&orderId='.$orderId;
        $payment_success_url = $real_plugin_url.'extension/payment/raiffeisen/success&orderId='.$orderId;
        $payment_fail_url = $real_plugin_url.'extension/payment/raiffeisen/fail&orderId='.$orderId;

        $args = array(
            'publicId' => $this->config->get('payment_raiffeisen_public_id'),
            'orderId' => $orderId,
            'amount' => number_format($order['total'], 2, '.', ''),
            'comment' => $this->config->get('payment_raiffeisen_order_comment'),
            'successUrl' => $payment_success_url,
            'failUrl' => $payment_fail_url,
            'successSbpUrl' => $payment_success_url,
            'paymentMethod' => $this->config->get('payment_raiffeisen_payment_method'),
            'locale' => 'ru',
            'paymentDetails' => $this->config->get('payment_raiffeisen_payment_details'),
            'extra' => json_encode([
                'apiClient' => 'Payform Software Opencart',
                'apiClientVersion' => '1.0.0'
            ])
        );

        if ($this->config->get('payment_raiffeisen_cash_receipt')) {
            $args['receipt'] = json_encode([
                    'customer' => [
                        'email' => $order['email'],
                    ],
                    'items' => $items]
            );
        }

        $data['args'] = $args;

        $paymentMethod = $this->config->get('payment_raiffeisen_frontend_name') ?? 'Райффайзенбанк';
        $this->model_extension_payment_raiffeisen->updateOrderPaymentName($orderId, $paymentMethod);

        $raiffeisen_order_id = $this->createOrder($args, $orderId);
        $this->createOrderItems($items, $orderId, $raiffeisen_order_id);

        $raiffeisenClient = new \RF\Api\Client($this->config->get('payment_raiffeisen_secret_key'),
            $this->config->get('payment_raiffeisen_public_id'),
            $this->config->get('payment_raiffeisen_env_url')
        );

        $raiffeisenClient->postCallbackUrl($payment_notification_url);

        $this->createLog(__METHOD__, $data);

        return $this->load->view('extension/payment/raiffeisen', $data);
    }


    /**
     * Formatting logging
     * @param  [type] $method метод (страница)
     * @param  array  $data  данные (для дампа)
     * @param  string  $text  текст (для описания)
     * @return [type]         [description]
     */
    public function createLog($method, $data = array(), $text = '')
    {
        if ($this->config->get('payment_raiffeisen_log')) {
            $this->log->write('---------RAIFFEISEN START LOG---------');
            $this->log->write('---Вызываемый метод: '.$method.'---');
            $this->log->write('---Описание: '.$text.'---');
            $this->log->write($data);
            $this->log->write('---------RAIFFEISEN END LOG---------');
        }
        return true;
    }

    /**
     * Fail order payment
     * @return [type] [description]
     */
    public function fail()
    {
        $request = $this->request->post;
        if (empty($request)) {
            $request = $this->request->get;
        }

        $order_id = isset($request["orderId"]) ? $request["orderId"] : null;
        if (empty($order_id)) {
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        try {
            $this->createLog(__METHOD__, '', 'Платеж не выполнен');
            $this->load->model('checkout/order');
            $this->model_checkout_order->addOrderHistory($order_id,
                $this->config->get('payment_raiffeisen_order_fail_status'), 'Raiffeisen', true);
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return true;
        } catch (Exception $e) {
            $this->response->redirect($this->url->link('checkout/success', '', true));
            return true;
        }
    }

    /**
     * Successful order payment
     * @return [type] [description]
     */
    public function success()
    {
        $request = $this->request->post;

        if (empty($request)) {
            $request = $this->request->get;
        }

        $order_id = isset($request["orderId"]) ? $request["orderId"] : null;
        if (empty($order_id)) {
            $this->response->redirect($this->url->link('checkout/success', '', true));
            return;
        }

        try {
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($order_id);

            if ((int) $order_info["order_status_id"] == (int) $this->config->get('payment_raiffeisen_order_success_status')) {
                $this->model_checkout_order->addOrderHistory($order_id,
                    $this->config->get('payment_raiffeisen_order_success_status'), 'Raiffeisen', true);
                $this->createLog(__METHOD__, $request, 'Платеж успешно завершен');
                // Clear all cookies and sessions
                unset($this->session->data['shipping_method']);
                unset($this->session->data['shipping_methods']);
                unset($this->session->data['payment_method']);
                unset($this->session->data['payment_methods']);
                unset($this->session->data['guest']);
                unset($this->session->data['comment']);
                unset($this->session->data['order_id']);
                unset($this->session->data['coupon']);
                unset($this->session->data['reward']);
                unset($this->session->data['voucher']);
                unset($this->session->data['vouchers']);
                unset($this->session->data['totals']);
                // Clear cart
                $this->cart->clear();
                $this->response->redirect($this->url->link('checkout/success', '', true));
                return true;
            }
        } catch (Exception $e) {
            $this->response->redirect($this->url->link('checkout/success', '', true));
            return false;
        }
        return false;

    }

    /**
     * Callback # 1 for check sign
     * @return function [description]
     */
    public function callback()
    {
        $request = $this->request->post;
        if (empty($request)) {
            $request = $this->request->get;
        }

        if (isset($this->request->post)) {
            $this->createLog(__METHOD__, $this->request->post, 'Data from Raiffeisen');
        }

        $order_id = isset($request["orderId"]) ? $request["orderId"] : null;
        if (empty($order_id)) {
            $this->response->redirect($this->url->link('checkout/success', '', true));
            return;
        }

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);

        $raiffeisenClient = new \RF\Api\Client($this->config->get('payment_raiffeisen_secret_key'),
            $this->config->get('payment_raiffeisen_public_id'),
            $this->config->get('payment_raiffeisen_env_url')
        );

        $transaction = $raiffeisenClient->getOrderTransaction($order_id);

        $this->logger($transaction, 'transaction');

        if ($transaction['code'] === 'SUCCESS') {

            $this->changeRaiffeisenOrderStatus($order_id, 'Completed');
            $this->createOrderSaleTransaction($transaction);

            if ($order_info['order_status_id'] == 0) {
                $this->model_checkout_order->addOrderHistory($order_id,
                    $this->config->get('payment_raiffeisen_order_success_status'), 'Оплачено через Raiffeisen');
                exit;
            }
            if ($order_info['order_status_id'] != $this->config->get('payment_raiffeisen_order_status_id')) {
                $this->model_checkout_order->addOrderHistory($order_id,
                    $this->config->get('payment_raiffeisen_order_success_status'), 'Raiffeisen', true);
            }
        } else {
            $this->log->write("Raiffeisen check is not correct!");
        }
    }

    /**
     * Get tax info for product
     * @param  [type] $product_id  id продукта
     * @return [type]             [description]
     */
    protected function getTax($product_id)
    {
        $this->load->model('catalog/product');
        $product_info = $this->model_catalog_product->getProduct($product_id);
        $tax_rule_id = 3;

        foreach ($this->config->get('payment_raiffeisen_classes') as $i => $tax_rule) {
            if ($tax_rule['raiffeisen_nalog'] == $product_info['tax_class_id']) {
                $tax_rule_id = $tax_rule['raiffeisen_tax_rule'];
            }
        }

        $tax_rules = array(
            array(
                'id' => 0,
                'name' => 'vat18',
            ),
            array(
                'id' => 1,
                'name' => 'vat10',
            ),
            array(
                'id' => 2,
                'name' => 'vat0',
            ),
            array(
                'id' => 3,
                'name' => 'no_vat',
            ),
            array(
                'id' => 4,
                'name' => 'vat118',
            ),
            array(
                'id' => 5,
                'name' => 'vat110',
            ),
        );
        return $tax_rules[$tax_rule_id]['name'];
    }

    /**
     * Logger function
     * @param  [type] $var  [description]
     * @param  string  $text  [description]
     * @return [type]       [description]
     */
    public function logger($var, $text = '')
    {
        $loggerFile = __DIR__.'/logger.log';
        if (is_object($var) || is_array($var)) {
            $var = (string) print_r($var, true);
        } else {
            $var = (string) $var;
        }
        $string = date("Y-m-d H:i:s")." - ".$text.' - '.$var."\n";
        file_put_contents($loggerFile, $string, FILE_APPEND);
    }

    /**
     * Создание заказа Raiffeisen
     */
    private function createOrder($data, $order_id) {
        $this->load->model('extension/payment/raiffeisen');
        $this->model_extension_payment_raiffeisen->deleteRaiffeisenOrder($order_id);
        return $this->model_extension_payment_raiffeisen->createRaiffeisenOrder($data, $order_id);
    }

    /**
     * Создание линий заказа в Raiffeisen
     */
    private function createOrderItems($data, $order_id, $raiffeisen_order_id) {
        $this->load->model('extension/payment/raiffeisen');
        $this->model_extension_payment_raiffeisen->deleteRaiffeisenOrderItem($order_id);
        $items = [];
        foreach ($data as $item) {
            $items[] = $this->model_extension_payment_raiffeisen->createRaiffeisenOrderItem($item, $order_id, $raiffeisen_order_id);
        }
        return $items;
    }

    /**
     * @param $order_id
     * @param $status
     */
    private function changeRaiffeisenOrderStatus($order_id, $status) {
        $this->load->model('extension/payment/raiffeisen');
        $this->model_extension_payment_raiffeisen->changeRaiffeisenOrderStatus($order_id, $status);
    }

    /**
     * Делаем
     * @param $data
     */
    private function createOrderSaleTransaction($data) {
        $code = $data['code'];
        $transaction_id = $data['transaction']['id'];
        $order_id = $data['transaction']['orderId'];
        $status = $data['transaction']['status']['value'] === 'SUCCESS' ? 'Completed' : 'Not Completed';
        $status_date = $data['transaction']['status']['date'];
        $amount = $data['transaction']['amount'];
        $comment = $data['transaction']['comment'] ?? '';

        $this->load->model('extension/payment/raiffeisen');
        $RaiffeisenOrder = $this->model_extension_payment_raiffeisen->getRaiffeisenOrder($order_id);

        $this->logger($RaiffeisenOrder, '$RaiffeisenOrder');

        if (!$RaiffeisenOrder) {
            return;
        }
        $transactionData = [
            'code' => $code,
            'operation' => 'sale',
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'status' => $status,
            'status_date' => $status_date,
            'transaction_data' => $data,
            'payment_details' => $comment,
        ];

        $RaiffeisenTransactionId = $this->model_extension_payment_raiffeisen->createRaiffeisenTransaction(
            $transactionData, $order_id, $RaiffeisenOrder['raiffeisen_order_id']);

        $RaiffeisenOrderItems = $this->model_extension_payment_raiffeisen->getRaiffeisenOrderItems($order_id);

        if (!$RaiffeisenOrderItems) {
            return;
        }

        foreach ($RaiffeisenOrderItems as $item) {
            $transactionDataItem = array_merge($transactionData, $item);
            $transactionDataItem['raiffeisen_transaction_id'] = $RaiffeisenTransactionId;
            unset($transactionDataItem['created_at']);
            unset($transactionDataItem['updated_at']);
            $this->model_extension_payment_raiffeisen->createRaiffeisenTransactionItem(
                $transactionDataItem, $order_id, $item['raiffeisen_order_item_id']
            );
            unset($transactionDataItem);
        }
    }
}
