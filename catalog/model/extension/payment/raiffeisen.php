<?php


class ModelExtensionPaymentRaiffeisen extends Model
{
    public function getMethod($address, $total)
    {
        $status = null;

        $this->load->language('extension/payment/raiffeisen');

        if ($this->config->get('payment_raiffeisen_status')) {
            if (!$this->config->get('payment_raiffeisen_status') ||
                !$this->config->get('payment_raiffeisen_public_id') ||
                !$this->config->get('payment_raiffeisen_secret_key')) {
                $status = false;
            } else {
                $status = true;
            }
        }

        $method_data = [];

        if ($status) {
            $method_data = [
                'code' => 'raiffeisen',
                'title' => $this->config->get('payment_raiffeisen_frontend_name') ?? 'Райффайзенбанк',
                'terms' => $this->config->get('entry_payment_raiffeisen_order_comment'),
                'sort_order' => $this->config->get('payment_raiffeisen_sort_order')
            ];

            if (isset($_REQUEST['route']) && $_REQUEST['route'] == 'checkout/payment_method' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                $method_data['title'] = $this->displayLogos($method_data['title']);
            }
        }

        return $method_data;
    }

    public function displayLogos($title)
    {
        $customizedTitle = $title;

        $customizedTitle .= '<br/><img style="max-height: 20px;" src="/catalog/view/theme/default/image/payment/raiffeisen/mirLogo.svg"><img style="max-height: 20px;" src="/catalog/view/theme/default/image/payment/raiffeisen/mastercardLogo.svg"> <img style="max-height: 20px;" src="/catalog/view/theme/default/image/payment/raiffeisen/sbpLogo.svg"> <img style="max-height: 20px;" src="/catalog/view/theme/default/image/payment/raiffeisen/visaLogo.svg">';

        return $customizedTitle;
    }

    public function updateOrderPaymentName($order_id, $paymentName)
    {
        $paymentName = $this->db->escape($paymentName);
        $paymentName = strip_tags($paymentName);

        return $this->db->query("UPDATE ".DB_PREFIX."order SET payment_method='".$paymentName."' WHERE `order_id` = '".$order_id."'");
    }

    /**
     * Создание заказа для метода оплаты Raiffeisen
     * @param $order_data
     * @param $order_id
     * @return void
     */
    public function createRaiffeisenOrder($order_data, $order_id)
    {
        (isset($order_data['publicId'])) ? $order_data['publicId'] = $this->db->escape($order_data['publicId']) : $order_data['publicId'] = '';
        (isset($order_data['amount'])) ? $order_data['amount'] = (int) $order_data['amount'] : $order_data['amount'] = 0.00;
        (isset($order_data['comment'])) ? $order_data['comment'] = $this->db->escape($order_data['comment']) : $order_data['comment'] = '';
        (isset($order_data['successUrl'])) ? $order_data['successUrl'] = $this->db->escape($order_data['successUrl']) : $order_data['successUrl'] = '';
        (isset($order_data['failUrl'])) ? $order_data['failUrl'] = $this->db->escape($order_data['failUrl']) : $order_data['failUrl'] = '';
        (isset($order_data['extra'])) ? $order_data['extra'] = $this->db->escape($order_data['extra']) : $order_data['extra'] = '{}';
        (isset($order_data['paymentMethod'])) ? $order_data['paymentMethod'] = $this->db->escape($order_data['paymentMethod']) : $order_data['paymentMethod'] = '{}';
        (isset($order_data['locale'])) ? $order_data['locale'] = $this->db->escape($order_data['locale']) : $order_data['locale'] = 'ru';
        (isset($order_data['expirationDate'])) ? $order_data['expirationDate'] = $this->db->escape($order_data['expirationDate']) : $order_data['expirationDate'] = null;
        (isset($order_data['successSbpUrl'])) ? $order_data['successSbpUrl'] = $this->db->escape($order_data['successSbpUrl']) : $order_data['successSbpUrl'] = '';
        (isset($order_data['paymentDetails'])) ? $order_data['paymentDetails'] = $this->db->escape($order_data['paymentDetails']) : $order_data['paymentDetails'] = '';
        (isset($order_data['receipt'])) ? $order_data['receipt'] = $this->db->escape($order_data['receipt']) : $order_data['receipt'] = '';
        $this->db->query("INSERT INTO ".DB_PREFIX."raiffeisen_order
            SET public_id = '".$order_data['publicId']."',
             order_id = '".(int) $order_id."',
             amount = '".(int) $order_data['amount']."', 
             comment = '".$order_data['comment']."', 
             success_url = '".$order_data['successUrl']."', 
             fail_url = '".$order_data['failUrl']."', 
             extra = '".$order_data['extra']."', 
             payment_method = '".$order_data['paymentMethod']."', 
             locale = '".$order_data['locale']."',
             expiration_date =  '".$order_data['expirationDate']."',
             success_sbp_url = '".$order_data['successSbpUrl']."', 
             status = 'NotCompleted', 
             payment_details = '".$order_data['paymentDetails']."',
             receipt = '".$order_data['receipt']."'
            ");
        return $this->db->getLastId();
    }

    /**
     * Удаление всех заказов c указанным order_id
     * @param $order_id
     * @return mixed
     */
    public function deleteRaiffeisenOrder($order_id) {
        return $this->db->query("DELETE FROM ".DB_PREFIX."raiffeisen_order WHERE `order_id` = '".$order_id."'");
    }

    /**
     * Добавление позиции по заказу Raiffeisen
     * @param $item_data
     * @param $raiffeisen_order_id
     */
    public function createRaiffeisenOrderItem($item_data, $order_id, $raiffeisen_order_id)
    {
        (isset($item_data['name'])) ? $item_data['name'] = $this->db->escape($item_data['name']) : $item_data['name'] = '';
        (isset($item_data['price'])) ? $item_data['price'] = (float) $item_data['price'] : $item_data['price'] = 0.00;
        (isset($item_data['qty'])) ? $item_data['qty'] = (int) $item_data['qty'] : $item_data['qty'] = 1;
        (isset($item_data['amount'])) ?: $item_data['amount'] = $item_data['qty'] * $item_data['price'];
        (isset($item_data['payment_object'])) ? $item_data['payment_object'] = $this->db->escape($item_data['payment_object']) : $item_data['payment_object'] = 'COMMODITY';
        (isset($item_data['measurement_unit'])) ? $item_data['payment_object'] = $this->db->escape($item_data['measurement_unit']) : $item_data['measurement_unit'] = 'PIECE';
        (isset($item_data['nomenclature_code'])) ? $item_data['nomenclature_code'] = $this->db->escape($item_data['nomenclature_code']) : $item_data['nomenclature_code'] = '';
        (isset($item_data['vat_type'])) ? $item_data['vat_type'] = $this->db->escape($item_data['vat_type']) : $item_data['vat_type'] = 'VAT20';
        (isset($item_data['marking'])) ? $item_data['marking'] = $this->db->escape($item_data['marking']) : $item_data['marking'] = '{}';
        (isset($item_data['status'])) ? $item_data['status'] = $this->db->escape($item_data['status']) : $item_data['status'] = 'paid';
        $this->db->query("INSERT INTO ".DB_PREFIX."raiffeisen_order_item
            SET raiffeisen_order_id = '".(int) $raiffeisen_order_id."',
             order_id = '".(int) $order_id."',
             name = '".$item_data['name']."', 
             price = '".(float) $item_data['price']."', 
             qty = '".(int) $item_data['qty']."', 
             amount = '".(float) $item_data['amount']."', 
             payment_object = '".$item_data['payment_object']."', 
             measurement_unit = '".$item_data['measurement_unit']."', 
             nomenclature_code = '".$item_data['nomenclature_code']."', 
             vat_type = '".$item_data['vat_type']."', 
             marking = '".$item_data['marking']."', 
            status = 'NotCompleted'
            ");
        return $this->db->getLastId();
    }

    /**
     * Удаление всех позиций заказов c указанным order_id
     * @param $order_id
     * @return mixed
     */
    public function deleteRaiffeisenOrderItem($order_id) {
        return $this->db->query("DELETE FROM ".DB_PREFIX."raiffeisen_order_item WHERE `order_id` = '".$order_id."'");
    }

    /**
     * Добавление транзакции по заказу Raiffeisen
     * @param $item_data
     * @param $raiffeisen_order_id
     */
    public function createRaiffeisenTransaction($transaction_data, $order_id, $raiffeisen_order_id)
    {
        (isset($transaction_data['code'])) ? $transaction_data['code'] = $this->db->escape($transaction_data['code']) : $transaction_data['code'] = '';
        (isset($transaction_data['operation']) ) ? $transaction_data['operation'] = $this->db->escape($transaction_data['operation']) : $transaction_data['operation'] = '';
        (isset($transaction_data['transaction_id'])) ? $transaction_data['transaction_id'] = $this->db->escape($transaction_data['transaction_id']) : $transaction_data['transaction_id'] = '';
        (isset($transaction_data['refund_id'])) ? $transaction_data['refund_id'] = $this->db->escape($transaction_data['refund_id']) : $transaction_data['refund_id'] = '';
        (isset($transaction_data['refund_status'])) ? $transaction_data['refund_status'] = $this->db->escape($transaction_data['refund_status']) : $transaction_data['refund_status'] = '';
        (isset($transaction_data['amount'])) ? $transaction_data['amount'] = (float) $transaction_data['amount'] : $transaction_data['amount'] = 0.00;
        (isset($transaction_data['status']))? $transaction_data['status'] = $this->db->escape($transaction_data['status']) : $transaction_data['status'] = '';
        (isset($transaction_data['status_date']))? $transaction_data['status_date'] = $this->db->escape($transaction_data['status_date']) : $transaction_data['status_date'] = '';
        if (isset($transaction_data['transaction_data']) && (is_array($transaction_data['transaction_data']) || is_object($transaction_data['transaction_data']))) {
            $transaction_data['transaction_data'] = json_encode($transaction_data['transaction_data']);
        }
        (isset($transaction_data['transaction_data'])) ? $transaction_data['transaction_data'] = $this->db->escape($transaction_data['transaction_data']) : $transaction_data['transaction_data'] = '{}';
        if (isset($transaction_data['receipt']) && (is_array($transaction_data['receipt']) || is_object($transaction_data['receipt']))) {
            $transaction_data['receipt'] = json_encode($transaction_data['receipt']);
        }
        (isset($transaction_data['receipt'])) ? $transaction_data['receipt'] = $this->db->escape($transaction_data['receipt']) : $transaction_data['receipt'] = '{}';
        (isset($transaction_data['payment_details'])) ? $transaction_data['payment_details'] = $this->db->escape($transaction_data['payment_details']) : $transaction_data['payment_details'] = '';
        $this->db->query("INSERT INTO ".DB_PREFIX."raiffeisen_transaction
            SET raiffeisen_order_id = '".(int) $raiffeisen_order_id."',
             order_id = '".(int) $order_id."',
             code = '".$transaction_data['code']."', 
             operation = '". $transaction_data['operation']."', 
             transaction_id = '".$transaction_data['transaction_id']."', 
             status = '".$transaction_data['status']."', 
             status_date = '". $transaction_data['status_date']."', 
             refund_id = '".$transaction_data['refund_id']."', 
             refund_status = '".$transaction_data['refund_status']."', 
             amount = '".(float) $transaction_data['amount']."', 
             transaction_data = '".$transaction_data['transaction_data']."', 
             receipt = '". $transaction_data['receipt']."', 
             payment_details = '". $transaction_data['payment_details']."' 
            ");
        return $this->db->getLastId();
    }

    /**
     * Удаление всех транзакций c указанным order_id
     * @param $order_id
     * @return mixed
     */
    public function deleteRaiffeisenTransaction($order_id) {
        return $this->db->query("DELETE FROM ".DB_PREFIX."raiffeisen_transaction WHERE `order_id` = '".$order_id."'");
    }

    /**
     * Добавление позиции транзакции по заказу Raiffeisen
     * @param $item_data
     * @param $raiffeisen_order_id
     */
    public function createRaiffeisenTransactionItem($transaction_data, $order_id, $raiffeisen_order_item_id)
    {
        (isset($transaction_data['code'])) ? $transaction_data['code'] = $this->db->escape($transaction_data['code']) : $transaction_data['code'] = '';
        (isset($transaction_data['operation'])) ? $transaction_data['operation'] = $this->db->escape($transaction_data['operation']) : $transaction_data['operation'] = '';
        (isset($transaction_data['transaction_id'])) ? $transaction_data['transaction_id'] = $this->db->escape($transaction_data['transaction_id']) : $transaction_data['transaction_id'] = '';
        (isset($transaction_data['refund_id'])) ? $transaction_data['refund_id'] = $this->db->escape($transaction_data['refund_id']) : $transaction_data['refund_id'] = '';
        (isset($transaction_data['refund_status'])) ? $transaction_data['refund_status'] = $this->db->escape($transaction_data['refund_status']) : $transaction_data['refund_status'] = '';
        if (isset($transaction_data['transaction_data']) && (is_array($transaction_data['transaction_data']) || is_object($transaction_data['transaction_data']))) {
            $transaction_data['transaction_data'] = json_encode($transaction_data['transaction_data']);
        }
        (isset($transaction_data['transaction_data'])) ? $transaction_data['transaction_data'] = $this->db->escape($transaction_data['transaction_data']) : $transaction_data['transaction_data'] = '{}';
        if (isset($transaction_data['receipt']) && (is_array($transaction_data['receipt']) || is_object($transaction_data['receipt']))) {
            $transaction_data['receipt'] = json_encode($transaction_data['receipt']);
        }
        (isset($transaction_data['receipt'])) ? $transaction_data['receipt'] = $this->db->escape($transaction_data['receipt']) : $transaction_data['receipt'] = '{}';
        (isset($transaction_data['payment_details']) || !empty($transaction_data['payment_details'])) ? $transaction_data['payment_details'] = $this->db->escape($transaction_data['payment_details']) : $transaction_data['payment_details'] = '';
        (isset($transaction_data['name'])) ? $transaction_data['name'] = $this->db->escape($transaction_data['name']) : $transaction_data['name'] = '';
        (isset($transaction_data['price'])) ? $transaction_data['price'] = (float) $transaction_data['price'] : $transaction_data['price'] = 0.00;
        (isset($transaction_data['qty'])) ? $transaction_data['qty'] = (int) $transaction_data['qty'] : $transaction_data['qty'] = 1;
        (isset($transaction_data['amount'])) ?: $transaction_data['amount'] = $transaction_data['qty'] * $transaction_data['price'];
        (isset($transaction_data['payment_object'])) ? $transaction_data['payment_object'] = $this->db->escape($transaction_data['payment_object']) : $transaction_data['payment_object'] = 'COMMODITY';
        (isset($transaction_data['measurement_unit'])) ? $transaction_data['payment_object'] = $this->db->escape($transaction_data['measurement_unit']) : $transaction_data['measurement_unit'] = 'PIECE';
        (isset($transaction_data['nomenclature_code'])) ? $transaction_data['nomenclature_code'] = $this->db->escape($transaction_data['nomenclature_code']) : $transaction_data['nomenclature_code'] = '';
        (isset($transaction_data['vat_type'])) ? $transaction_data['vat_type'] = $this->db->escape($transaction_data['vat_type']) : $transaction_data['vat_type'] = 'VAT20';
        $this->db->query("INSERT INTO ".DB_PREFIX."raiffeisen_transaction_item
            SET raiffeisen_order_item_id = '".(int) $raiffeisen_order_item_id."',
             order_id = '".(int) $order_id."',
             code = '".$transaction_data['code']."', 
             operation = '". $transaction_data['operation']."', 
             transaction_id = '".$transaction_data['transaction_id']."', 
             refund_id = '". $transaction_data['refund_id']."', 
             refund_status = '". $transaction_data['refund_status']."', 
             transaction_data = '". $transaction_data['transaction_data']."', 
             receipt = '". $transaction_data['receipt']."', 
             payment_details = '". $transaction_data['payment_details']."',
             name = '".$transaction_data['name']."', 
             price = '".(float) $transaction_data['price']."', 
             qty = '".(int) $transaction_data['qty']."', 
             amount = '".(float) $transaction_data['amount']."', 
             payment_object = '".$transaction_data['payment_object']."', 
             measurement_unit = '".$transaction_data['measurement_unit']."', 
             nomenclature_code = '".$transaction_data['nomenclature_code']."', 
             vat_type = '".$transaction_data['vat_type']."'
            ");
        return $this->db->getLastId();
    }

    /**
     * Удаление всех позиций транзакций c указанным order_id
     * @param $order_id
     * @return mixed
     */
    public function deleteRaiffeisenTransactionItem($order_id) {
        return $this->db->query("DELETE FROM ".DB_PREFIX."raiffeisen_transaction_item WHERE `order_id` = '".$order_id."'");
    }

    /**
     * Изменение статуса заказа
     * @param $order_id
     * @param $status
     */
    public function changeRaiffeisenOrderStatus($order_id, $status) {
        $this->db->query("UPDATE ".DB_PREFIX."raiffeisen_order SET `status` = '".$status."' WHERE `order_id`=".$order_id);
        $this->db->query("UPDATE ".DB_PREFIX."raiffeisen_order_item SET `status` = '".$status."' WHERE `order_id`=".$order_id);
    }


    /**
     * Получение объекта заказа
     * @param $order_id
     * @return mixed
     */
    public function getRaiffeisenOrder($order_id)
    {
        $query = $this->db->query("SELECT * FROM `".DB_PREFIX."raiffeisen_order` WHERE `order_id` = '".(int) $order_id."'");
        return $query->row;
    }

    /**
     * @param $order_id
     * @return mixed
     */
    public function getRaiffeisenOrderItems($order_id)
    {
        $query = $this->db->query("SELECT * FROM `".DB_PREFIX."raiffeisen_order_item` WHERE `order_id` = '".(int) $order_id."'");
        return $query->rows;
    }

}

?>
