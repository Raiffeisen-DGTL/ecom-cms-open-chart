<?php

class ModelExtensionPaymentRaiffeisen extends Model
{
    /**
     * Инсталяция
     */
    public function install()
    {
        $this->db->query("
			CREATE TABLE IF NOT EXISTS `".DB_PREFIX."raiffeisen_order` (
			  `raiffeisen_order_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `order_id` int(11) NOT NULL,
			  `public_id` VARCHAR(100) NOT NULL,
			  `amount` DECIMAL( 10, 2 ) NOT NULL,
			  `comment` VARCHAR(141) DEFAULT NULL,
			  `success_url` VARCHAR(255) DEFAULT NULL,
			  `fail_url` VARCHAR(255) DEFAULT NULL,
			  `extra` TEXT DEFAULT NULL,
			  `payment_method` VARCHAR(30) DEFAULT '', 
			  `locale` ENUM('ru', 'en') DEFAULT 'ru',
			  `expiration_date` DATETIME DEFAULT NULL,
			  `success_sbp_url` VARCHAR(255) DEFAULT NULL,
			  `status` VARCHAR(40) DEFAULT NULL,
		      `payment_details` VARCHAR(141) DEFAULT NULL,
			  `receipt` TEXT DEFAULT NULL,
			  `created_at` DATETIME DEFAULT NOW(),
			  `updated_at` DATETIME DEFAULT NOW(), 
			  PRIMARY KEY (`raiffeisen_order_id`),
			  INDEX (public_id, order_id, payment_method)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci");

        $this->db->query("
			CREATE TABLE IF NOT EXISTS `".DB_PREFIX."raiffeisen_order_item` (
			  `raiffeisen_order_item_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `raiffeisen_order_id`      INT(11) NOT NULL,
			  `order_id` int(11) NOT NULL,
              `name` VARCHAR(255) DEFAULT '',
              `price` DECIMAL( 10, 2 ) DEFAULT NULL,
              `qty` INT(11) DEFAULT 1, 
              `payment_object` VARCHAR(40) DEFAULT 'COMMODITY',      
			  `amount` DECIMAL( 10, 2 ) DEFAULT 0.00,
			  `payment_mode` VARCHAR(40) DEFAULT 'FULL_PREPAYMENT',
			  `measurement_unit` VARCHAR(40) DEFAULT 'PIECE',
			  `nomenclature_code` VARCHAR(100) DEFAULT '',
			  `vat_type` VARCHAR(40) DEFAULT 'VAT20',
			  `marking` TEXT, 
			  `status` VARCHAR(40) DEFAULT NULL,
			  `created_at` DATETIME DEFAULT NOW(),
			  `updated_at` DATETIME DEFAULT NOW(), 
			  PRIMARY KEY (`raiffeisen_order_item_id`),
			  INDEX (raiffeisen_order_id, name, order_id)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci");

        $this->db->query("
			CREATE TABLE IF NOT EXISTS `".DB_PREFIX."raiffeisen_transaction` (
			  `raiffeisen_transaction_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `raiffeisen_order_id` INT(11) NOT NULL,
			  `order_id` int(11) NOT NULL,
			  `code` VARCHAR(40) DEFAULT NULL,
			  `operation` VARCHAR(40) DEFAULT 'payment',
			  `transaction_id` VARCHAR(40) NOT NULL,
			  `status` VARCHAR(40) DEFAULT NULL,
			  `status_date` DATETIME DEFAULT NULL,
			  `refund_id` VARCHAR(40) DEFAULT NULL,
			  `refund_status` VARCHAR(40) DEFAULT NULL,
			  `amount` DECIMAL( 10, 2 ) NOT NULL,
			  `transaction_data` TEXT DEFAULT NULL,
			  `receipt` TEXT DEFAULT NULL,
			  `payment_details` VARCHAR(141) DEFAULT NULL,
			  `created_at` DATETIME DEFAULT NOW(),
			  `updated_at` DATETIME DEFAULT NOW(), 
			  PRIMARY KEY (`raiffeisen_transaction_id`),
			   INDEX (code, operation, transaction_id, refund_id, order_id)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci");

        $this->db->query("
			CREATE TABLE IF NOT EXISTS `".DB_PREFIX."raiffeisen_transaction_item` (
			  `raiffeisen_transaction_item_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `raiffeisen_order_item_id` INT(11) NOT NULL,
			  `order_id` int(11) NOT NULL,
			  `parent_id` int(11) DEFAULT NULL,
			  `code` VARCHAR(40),
			  `operation` VARCHAR(40) DEFAULT 'payment',
			  `transaction_id` VARCHAR(40) NOT NULL,
			  `refund_id` VARCHAR(40),
			  `refund_status` VARCHAR(40),
			  `transaction_data` TEXT DEFAULT NULL,
			  `receipt` TEXT DEFAULT NULL,
			  `payment_details` VARCHAR(141),
              `name` VARCHAR(255) DEFAULT '',
              `price` DECIMAL( 10, 2 ) DEFAULT NULL,
              `qty` INT(11) DEFAULT 1, 
              `payment_object` VARCHAR(40) DEFAULT 'COMMODITY',      
			  `amount` DECIMAL( 10, 2 ) DEFAULT 0.00,
			  `payment_mode` VARCHAR(40) DEFAULT 'FULL_PREPAYMENT',
			  `measurement_unit` VARCHAR(40) DEFAULT 'PIECE',
			  `nomenclature_code` VARCHAR(100) DEFAULT '',
			  `vat_type` VARCHAR(40) DEFAULT 'VAT20',
			  `created_at` DATETIME DEFAULT NOW(),
			  `updated_at` DATETIME DEFAULT NOW(), 
			  PRIMARY KEY (`raiffeisen_transaction_item_id`),
			  INDEX (code, operation, transaction_id, refund_id, order_id)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci");
    }

    /**
     * Деинсталяция
     */
    public function uninstall()
    {
        $this->db->query("SET FOREIGN_KEY_CHECKS=0;");
        $this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."raiffeisen_transaction_item`");
        $this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."raiffeisen_transaction`");
        $this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."raiffeisen_order_item`");
        $this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."raiffeisen_order`");
        $this->db->query("SET FOREIGN_KEY_CHECKS=1;");
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

    /**
     * @param $order_id
     * @return mixed
     */
    public function getRaiffeisenOrderTransactions($order_id)
    {
        $query = $this->db->query("SELECT * FROM `".DB_PREFIX."raiffeisen_order_transaction` WHERE `order_id` = '".(int) $order_id."'");
        return $query->rows;
    }


    /**
     * Изменение статуса ззаказа
     * @param $order_id
     * @param $status
     */
    public function editRaiffeisenOrderStatus($order_id, $status)
    {
        $this->db->query("UPDATE `".DB_PREFIX."raiffeisen_order` SET `status` = '".$this->db->escape($status)."', `updated_at` = NOW() WHERE `order_id` = '".(int) $order_id."'");
    }

    /**
     * @param $transaction_data
     * @param  array  $request_data
     * @return mixed
     */
    public function addTransaction($transaction_data, $request_data = array())
    {
        if ($request_data) {
            $serialized_data = json_encode($request_data);

            $this->db->query("UPDATE ".DB_PREFIX."raiffeisen_order_transaction SET call_data = '".$this->db->escape($serialized_data)."' WHERE raiffeisen_order_transaction_id = ".(int) $raiffeisen_order_transaction_id." LIMIT 1");
        }


        $this->db->query("INSERT INTO `".DB_PREFIX."raiffeisen_order_transaction` SET `raiffeisen_order_id` = '".(int) $transaction_data['raiffeisen_order_id']."', `transaction_id` = '".$this->db->escape($transaction_data['transaction_id'])."', `parent_id` = '".$this->db->escape($transaction_data['parent_id'])."', `date_added` = NOW(), `note` = '".$this->db->escape($transaction_data['note'])."', `msgsubid` = '".$this->db->escape($transaction_data['msgsubid'])."', `receipt_id` = '".$this->db->escape($transaction_data['receipt_id'])."', `payment_type` = '".$this->db->escape($transaction_data['payment_type'])."', `payment_status` = '".$this->db->escape($transaction_data['payment_status'])."', `pending_reason` = '".$this->db->escape($transaction_data['pending_reason'])."', `transaction_entity` = '".$this->db->escape($transaction_data['transaction_entity'])."', `amount` = '".(float) $transaction_data['amount']."', `debug_data` = '".$this->db->escape($transaction_data['debug_data'])."'");

        return $this->db->getLastId();
    }

    public function updateTransaction($transaction)
    {
        $this->db->query("UPDATE ".DB_PREFIX."raiffeisen_order_transaction SET raiffeisen_order_id = ".(int) $transaction['raiffeisen_order_id'].", transaction_id = '".$this->db->escape($transaction['transaction_id'])."', parent_id = '".$this->db->escape($transaction['parent_id'])."', date_added = '".$this->db->escape($transaction['date_added'])."', note = '".$this->db->escape($transaction['note'])."', msgsubid = '".$this->db->escape($transaction['msgsubid'])."', receipt_id = '".$this->db->escape($transaction['receipt_id'])."', payment_type = '".$this->db->escape($transaction['payment_type'])."', payment_status = '".$this->db->escape($transaction['payment_status'])."', pending_reason = '".$this->db->escape($transaction['pending_reason'])."', transaction_entity = '".$this->db->escape($transaction['transaction_entity'])."', amount = '".$this->db->escape($transaction['amount'])."', debug_data = '".$this->db->escape($transaction['debug_data'])."', call_data = '".$this->db->escape($transaction['call_data'])."' WHERE raiffeisen_order_transaction_id = '".(int) $transaction['raiffeisen_order_transaction_id']."'");
    }

    public function getraiffeisenOrderByTransactionId($transaction_id)
    {
        $query = $this->db->query("SELECT * FROM ".DB_PREFIX."raiffeisen_order_transaction WHERE transaction_id = '".$this->db->escape($transaction_id)."'");

        return $query->rows;
    }

    public function getFailedTransaction($raiffeisen_order_transaction_id)
    {
        $query = $this->db->query("SELECT * FROM ".DB_PREFIX."raiffeisen_order_transaction WHERE raiffeisen_order_transaction_id = '".(int) $raiffeisen_order_transaction_id."'");

        return $query->row;
    }

    public function getLocalTransaction($transaction_id)
    {
        $result = $this->db->query("SELECT * FROM ".DB_PREFIX."raiffeisen_order_transaction WHERE transaction_id = '".$this->db->escape($transaction_id)."'")->row;

        if ($result) {
            return $result;
        } else {
            return false;
        }
    }

    public function getOrderId($transaction_id)
    {
        $query = $this->db->query("SELECT `o`.`order_id` FROM `".DB_PREFIX."raiffeisen_order_transaction` `ot` LEFT JOIN `".DB_PREFIX."raiffeisen_order` `o`  ON `o`.`raiffeisen_order_id` = `ot`.`raiffeisen_order_id`  WHERE `ot`.`transaction_id` = '".$this->db->escape($transaction_id)."' LIMIT 1");

        return $query->row['order_id'];
    }

    /**
     * Получение неоплаченной части заказа
     * @param $raiffeisen_order_id
     * @return mixed
     */
    public function getCapturedTotal($raiffeisen_order_id)
    {
        $query = $this->db->query("SELECT SUM(`amount`) AS `amount` FROM `".DB_PREFIX."raiffeisen_order_item` WHERE `raiffeisen_order_id` = '".(int) $raiffeisen_order_id."' AND `status` != 'NotCompleted'");
        return $query->row['amount'];
    }

    public function getRefundedTotal($raiffeisen_order_id)
    {
        return 0;
        //$query = $this->db->query("SELECT SUM(`amount`) AS `amount` FROM `".DB_PREFIX."raiffeisen_order_transaction` WHERE `raiffeisen_order_id` = '".(int) $raiffeisen_order_id."' AND `payment_status` = 'Refunded' AND `parent_id` != ''");
        //return isset($query->row['amount']) ? $query->row['amount'] : 0;
    }

    public function getRefundedTotalByParentId($transaction_id)
    {
        $query = $this->db->query("SELECT SUM(`amount`) AS `amount` FROM `".DB_PREFIX."raiffeisen_order_transaction` WHERE `parent_id` = '".$this->db->escape($transaction_id)."' AND `payment_type` = 'refund'");
        return $query->row['amount'];
    }

    public function getOrder($order_id)
    {
        $qry = $this->db->query("SELECT * FROM `".DB_PREFIX."raiffeisen_order` WHERE `order_id` = '".(int) $order_id."' LIMIT 1");

        if ($qry->num_rows) {
            $order = $qry->row;
            $order['transactions'] = $this->getTransactions($order_id);
            $order['captured'] = $this->totalCaptured($order['raiffeisen_order_id']);
            return $order;
        } else {
            return false;
        }
    }

    public function totalCaptured($order_id)
    {
        $qry = $this->db->query("SELECT SUM(`amount`) AS `amount` FROM `".DB_PREFIX."raiffeisen_transaction_item` WHERE `order_id` = '".(int) $order_id."'");
        return $qry->row['amount'];
    }

    public function getTransactions($order_id)
    {
        $query = $this->db->query("SELECT * FROM `".DB_PREFIX."raiffeisen_transaction_item` WHERE `order_id` = '".(int) $order_id."' ORDER BY `created_at` ASC");
        return $query->rows;
    }

    /**
     * Создание возврата
     * @param $raiffeisen_transaction_item_id
     */
    public function createRefund($raiffeisen_transaction_item_id) {
        $this->updateRaiffeisenTransactionItemOperation($raiffeisen_transaction_item_id, 'sale_and_refund');
        return $this->copyRaiffeisenTransactionItem($raiffeisen_transaction_item_id, 'start_refund');
    }

    /**
     * Получение записи по транзакции
     * @param $raiffeisen_transaction_item_id
     */
    public function getRaiffeisenTransactionItem($raiffeisen_transaction_item_id) {
        $query = $this->db->query("SELECT * FROM `".DB_PREFIX."raiffeisen_transaction_item` WHERE `raiffeisen_transaction_item_id` = ".(int)$raiffeisen_transaction_item_id);
        return $query->row;
    }

    /**
     * Добавление позиции транзакции по заказу Raiffeisen
     * @param $item_data
     * @param $raiffeisen_order_id
     */
    public function copyRaiffeisenTransactionItem($raiffeisen_transaction_item_id, $operation)
    {
        $item = $this->getRaiffeisenTransactionItem($raiffeisen_transaction_item_id);
        $item['parent'] = $item['raiffeisen_transaction_item_id'];
        unset($item['raiffeisen_transaction_item_id']);
        $item['operation'] = $operation;
        unset($item['created_at']);
        unset($item['updated_at']);
        $order_id = $item['order_id'];
        $raiffeisen_order_item_id = $item['raiffeisen_order_item_id'];
        $lastId = $this->createRaiffeisenTransactionItem($item, $order_id, $raiffeisen_order_item_id);
        return $this->getRaiffeisenTransactionItem($lastId);
    }

    /**
     * @param $raiffeisen_transaction_item_id
     * @param $operation
     */
    public function updateRaiffeisenTransactionItemOperation($raiffeisen_transaction_item_id, $operation) {
        $this->db->query("UPDATE ".DB_PREFIX."raiffeisen_transaction_item SET `operation`='".$operation."' WHERE `raiffeisen_transaction_item_id` = ".$raiffeisen_transaction_item_id);
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
        (isset($transaction_data['parent_id'])) ? $transaction_data['parent_id'] = (int)$transaction_data['parent_id'] : $transaction_data['parent_id'] = null;
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
             parent_id = '".$transaction_data['parent_id']."', 
             code = '".$transaction_data['code']."', 
             operation = '". $transaction_data['operation']."', 
             transaction_id = '".$transaction_data['transaction_id']."', 
             refund_id = '". $transaction_data['refund_id']."', 
             refund_status = '". $transaction_data['refund_status']."', 
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

}
