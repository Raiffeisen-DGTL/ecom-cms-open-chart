<?php

/**
 * Платежная система Raiffeisen - онлайн касса и интеграция различных способов оплаты
 *
 * @cms    Opencart
 * @author  awa77@mail.ru (Alexey Agafonov)
 * @version    3.3.1
 * @license
 * @copyright  Copyright (c) 2018 Raiffeisen (https://www.raiffeisen.ru/)
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Тут объявляем константы
 */
define('RAIFFEISEN_TITLE', 'Оплата через метод Raiffeisen Bank');
define('RAIFFEISEN_TITLE_DESC', 'Raiffeisen платежный агрегатор');
define('RAIFFEISEN_DESC', 'Оплата через агрегатор платежей "Raiffeisen"');
define('TITLE_EDIT', 'Редактирование');
define('TEXT_PAYMENT', 'Оплата');
define('RAIFFEISEN_ERROR_PHP_VERSION', 'Версия PHP слишком низкая для работы с модулем Raiffeisen');

require_once(DIR_SYSTEM.'library/raiffeisen/ClientException.php');
require_once(DIR_SYSTEM.'library/raiffeisen/Client.php');

/**
 * Class ControllerExtensionPaymentRaiffeisen
 */
class ControllerExtensionPaymentRaiffeisen extends Controller
{

    private $error = array();

    private $currentLanguage = 'ru';

    /**
     * The production API host.
     *
     * @const string
     */
    const HOST_PROD = 'https://e-commerce.raiffeisen.ru';

    /**
     * The test API host.
     *
     * @const string
     */
    const HOST_TEST = 'https://test.ecom.raiffeisen.ru';

    /**
     * Функция конструктор
     * ControllerExtensionPaymentPaymaster constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->currentLanguage = $this->language->get('code');
    }

    /**
     * Установщик, тут не нужен, но делаем для порадка
     * @return [type] тут не нужен, но делаем для порадка
     */
    public function install()
    {
        $this->load->model('extension/payment/raiffeisen');

        $this->model_extension_payment_raiffeisen->install();
    }

    /**
     * Деинсталятор
     */
    public function uninstall()
    {
        $this->load->model('extension/payment/raiffeisen');

        $this->model_extension_payment_raiffeisen->uninstall();
    }

    /**
     * Выводит меню с настройками
     * Для администрирования модуля
     */
    public function index()
    {
        $this->load->language('extension/payment/raiffeisen');
        $this->document->setTitle = $this->language->get('heading_title');
        $this->document->setTitle(RAIFFEISEN_TITLE);

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
            $this->model_setting_setting->editSetting('payment_raiffeisen', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension',
                'user_token='.$this->session->data['user_token'].'&type=payment', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        //a version php check
        if (PHP_VERSION_ID < 56000) {
            $data['errorPhpVersion'] = RAIFFEISEN_ERROR_PHP_VERSION;
        }

        //text for headings
        $data['headingTitle'] = RAIFFEISEN_TITLE;
        $data['heading_title'] = RAIFFEISEN_TITLE;
        $data['titleEdit'] = TITLE_EDIT;

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_all_zones'] = $this->language->get('text_all_zones');
        $data['text_card'] = $this->language->get('text_card');

        $real_plugin_url = str_replace('/admin', '', $this->url->link('', '', true));

        $data['payment_notification_url'] = $real_plugin_url.'extension/payment/raiffeisen/callback';
        $data['payment_success_url'] = $real_plugin_url.'extension/payment/raiffeisen/success';
        $data['payment_fail_url'] = $real_plugin_url.'extension/payment/raiffeisen/fail';

        // Какую используем среду - тестовую или рабочую (ставим везде ПРОД)
        $data['payment_raiffeisen_env_url'] = self::HOST_PROD;
        // Идентификатор магазина
        $data['payment_raiffeisen_public_id'] = $this->language->get('payment_raiffeisen_public_id');
        // Секретный ключ
        $data['payment_raiffeisen_secret_key'] = $this->language->get('payment_raiffeisen_secret_key');
        // Комментарий к заказу
        $data['payment_raiffeisen_order_comment'] = $this->language->get('payment_raiffeisen_order_comment');
        // Описание у клиента при оплате
        $data['payment_raiffeisen_frontend_name'] = $this->language->get('payment_raiffeisen_frontend_name') ?: 'Райффайзенбанк';
        // Метод оплаты Enum: "ONLY_SBP" "ONLY_ACQUIRING"
        // Метод оплаты. Если значение не передано, отображается общая форма
        $data['payment_raiffeisen_payment_method'] = $this->language->get('payment_raiffeisen_payment_method');
        //string <= 140 characters
        //Назначение платежа для платежей по СБП
        $data['payment_raiffeisen_payment_details'] = $this->language->get('payment_raiffeisen_payment_details');
        // Метод использования с чеком или без ФФД 1.05 и ФФД 1.2 (Фискализация)
        $data['payment_raiffeisen_cash_receipt'] = $this->language->get('payment_raiffeisen_cash_receipt');

        //Включен ли попап
        $data['payment_raiffeisen_popup'] = $this->language->get('payment_raiffeisen_popup');

        //CSS
        $data['payment_raiffeisen_css'] = $this->language->get('payment_raiffeisen_css');

        $data['payment_raiffeisen_unit_products'] = $this->language->get('payment_raiffeisen_unit_products');
        $data['payment_raiffeisen_unit_delivery'] = $this->language->get('payment_raiffeisen_unit_delivery');
        $data['payment_raiffeisen_object_products'] = $this->language->get('payment_raiffeisen_object_products');
        $data['payment_raiffeisen_object_delivery'] = $this->language->get('payment_raiffeisen_object_delivery');

        $data['payment_raiffeisen_order_success_status'] = $this->language->get('payment_raiffeisen_order_success_status');
        $data['payment_raiffeisen_order_fail_status'] = $this->language->get('payment_raiffeisen_order_fail_status');

        $data['payment_raiffeisen_status'] = $this->language->get('payment_raiffeisen_status');
        $data['payment_raiffeisen_sort_order'] = $this->language->get('payment_raiffeisen_sort_order');
        $data['payment_raiffeisen_tax'] = $this->language->get('payment_raiffeisen_tax');
        $data['payment_raiffeisen_log'] = $this->language->get('payment_raiffeisen_log');
        $data['payment_raiffeisen_class_tax'] = $this->language->get('payment_raiffeisen_class_tax');
        $data['payment_raiffeisen_text_tax'] = $this->language->get('payment_raiffeisen_text_tax');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['button_add'] = $this->language->get('button_add');

        $data['tab_general'] = $this->language->get('tab_general');

        !$data['payment_raiffeisen_public_id'] ? $data['error_payment_raiffeisen_public_id'] = true : $data['error_payment_raiffeisen_public_id'] = false;
        !$data['payment_raiffeisen_secret_key'] ? $data['error_payment_raiffeisen_secret_key'] = true : $data['error_payment_raiffeisen_secret_key'] = false;

        if (isset($this->error['payment_raiffeisen_public_id'])) {
            $data['payment_raiffeisen_public_id'] = $this->error['payment_raiffeisen_public_id'];
        } else {
            $data['error_payment_raiffeisen_public_id'] = '';
        }

        if (isset($this->error['payment_raiffeisen_secret_key'])) {
            $data['payment_raiffeisen_secret_key'] = $this->error['payment_raiffeisen_secret_key'];
        } else {
            $data['error_payment_raiffeisen_secret_key'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token='.$this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => TEXT_PAYMENT,
            'href' => $this->url->link('marketplace/extension',
                'user_token='.$this->session->data['user_token'].'&type=payment', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => RAIFFEISEN_TITLE,
            'href' => $this->url->link('extension/payment/raiffeisen', 'user_token='.$this->session->data['user_token'],
                true)
        );

        $data['action'] = $this->url->link('extension/payment/raiffeisen',
            'user_token='.$this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('extension/extension',
            'user_token='.$this->session->data['user_token'].'&type=payment', true);

        //$data['payment_raiffeisen_env_url'] = self::HOST_TEST;
        $data['payment_raiffeisen_env_url'] = self::HOST_PROD;

        isset($this->request->post['payment_raiffeisen_public_id']) ?
            $data['payment_raiffeisen_public_id'] = $this->request->post['payment_raiffeisen_public_id'] :
            $data['payment_raiffeisen_public_id'] = $this->config->get('payment_raiffeisen_public_id');

        isset($this->request->post['payment_raiffeisen_secret_key']) ?
            $data['payment_raiffeisen_secret_key'] = $this->request->post['payment_raiffeisen_secret_key'] :
            $data['payment_raiffeisen_secret_key'] = $this->config->get('payment_raiffeisen_secret_key');

        isset($this->request->post['payment_raiffeisen_order_comment']) ?
            $data['payment_raiffeisen_order_comment'] = $this->request->post['payment_raiffeisen_order_comment'] :
            $data['payment_raiffeisen_order_comment'] = $this->config->get('payment_raiffeisen_order_comment');

        isset($this->request->post['payment_raiffeisen_frontend_name']) ?
            $data['payment_raiffeisen_frontend_name'] = $this->request->post['payment_raiffeisen_frontend_name'] :
            $data['payment_raiffeisen_frontend_name'] = $this->config->get('payment_raiffeisen_frontend_name') ?: 'Райффайзенбанк';

        isset($this->request->post['payment_raiffeisen_payment_method']) ?
            $data['payment_raiffeisen_payment_method'] = $this->request->post['payment_raiffeisen_payment_method'] :
            $data['payment_raiffeisen_payment_method'] = $this->config->get('payment_raiffeisen_payment_method');

        isset($this->request->post['payment_raiffeisen_cash_receipt']) ?
            $data['payment_raiffeisen_cash_receipt'] = $this->request->post['payment_raiffeisen_cash_receipt'] :
            $data['payment_raiffeisen_cash_receipt'] = $this->config->get('payment_raiffeisen_cash_receipt');

        isset($this->request->post['payment_raiffeisen_popup']) ?
            $data['payment_raiffeisen_popup'] = $this->request->post['payment_raiffeisen_popup'] :
            $data['payment_raiffeisen_popup'] = $this->config->get('payment_raiffeisen_popup');

        isset($this->request->post['payment_raiffeisen_css']) ?
            $data['payment_raiffeisen_css'] = $this->request->post['payment_raiffeisen_css'] :
            $data['payment_raiffeisen_css'] = $this->config->get('payment_raiffeisen_css') ?: '';

        if (isset($this->request->post['payment_raiffeisen_payment_method'])) {
            $data['payment_raiffeisen_payment_method'] = $this->request->post['payment_raiffeisen_payment_method'];
        } else {
            $data['payment_raiffeisen_payment_method'] = $this->config->get('payment_raiffeisen_payment_method');
        }

        if (isset($this->request->post['payment_raiffeisen_payment_details'])) {
            $data['payment_raiffeisen_payment_details'] = $this->request->post['payment_raiffeisen_payment_details'];
        } else {
            $data['payment_raiffeisen_payment_details'] = $this->config->get('payment_raiffeisen_payment_details');
        }

        if (isset($this->request->post['payment_raiffeisen_unit_products'])) {
            $data['payment_raiffeisen_unit_products'] = $this->request->post['payment_raiffeisen_unit_products'];
        } else {
            $data['payment_raiffeisen_unit_products'] = $this->config->get('payment_raiffeisen_unit_products');
        }

        if (isset($this->request->post['payment_raiffeisen_unit_delivery'])) {
            $data['payment_raiffeisen_unit_delivery'] = $this->request->post['payment_raiffeisen_unit_delivery'];
        } else {
            $data['payment_raiffeisen_unit_delivery'] = $this->config->get('payment_raiffeisen_unit_delivery');
        }

        if (isset($this->request->post['payment_raiffeisen_object_products'])) {
            $data['payment_raiffeisen_object_products'] = $this->request->post['payment_raiffeisen_object_products'];
        } else {
            $data['payment_raiffeisen_object_products'] = $this->config->get('payment_raiffeisen_object_products');
        }

        if (isset($this->request->post['payment_raiffeisen_object_delivery'])) {
            $data['payment_raiffeisen_object_delivery'] = $this->request->post['payment_raiffeisen_object_delivery'];
        } else {
            $data['payment_raiffeisen_object_delivery'] = $this->config->get('payment_raiffeisen_object_delivery');
        }

        if (isset($this->request->post['payment_raiffeisen_object_delivery'])) {
            $data['payment_raiffeisen_object_delivery'] = $this->request->post['payment_raiffeisen_object_delivery'];
        } else {
            $data['payment_raiffeisen_object_delivery'] = $this->config->get('payment_raiffeisen_object_delivery');
        }

        if (isset($this->request->post['payment_raiffeisen_order_success_status'])) {
            $data['payment_raiffeisen_order_success_status'] = $this->request->post['payment_raiffeisen_order_success_status'];
        } else {
            $data['payment_raiffeisen_order_success_status'] = $this->config->get('payment_raiffeisen_order_success_status');
        }

        if (isset($this->request->post['payment_raiffeisen_order_fail_status'])) {
            $data['payment_raiffeisen_order_fail_status'] = $this->request->post['payment_raiffeisen_order_fail_status'];
        } else {
            $data['payment_raiffeisen_order_fail_status'] = $this->config->get('payment_raiffeisen_order_fail_status');
        }

        if (isset($this->request->post['payment_raiffeisen_status'])) {
            $data['payment_raiffeisen_status'] = $this->request->post['payment_raiffeisen_status'];
        } else {
            $data['payment_raiffeisen_status'] = $this->config->get('payment_raiffeisen_status');
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_raiffeisen_log'])) {
            $data['payment_raiffeisen_log'] = $this->request->post['payment_raiffeisen_log'];
        } else {
            $data['payment_raiffeisen_log'] = $this->config->get('payment_raiffeisen_log');
        }

        if (isset($this->request->post['payment_raiffeisen_classes'])) {
            $data['payment_raiffeisen_classes'] = $this->request->post['payment_raiffeisen_classes'];
        } elseif ($this->config->get('payment_raiffeisen_classes')) {
            $data['payment_raiffeisen_classes'] = $this->config->get('payment_raiffeisen_classes');
        } else {
            $data['payment_raiffeisen_classes'] = array(
                array(
                    'payment_raiffeisen_nalog' => 1,
                    'payment_raiffeisen_tax_rule' => 1
                )
            );
        }

        $data['cash_receipt_options'] = $this->get_cash_receipt_options();
        $data['tax_rules'] = $this->get_tax_rules();
        $data['method_options'] = $this->get_payment_method_options();
        $data['vat_options'] = $this->get_tax_rules();
        $data['unit_options'] = $this->get_unit_options();
        $data['object_options'] = $this->get_payment_object_options();
        $data['mode_options'] = $this->get_payment_mode_options();

        $this->load->model('localisation/tax_class');
        $data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();

        if (isset($this->request->post['payment_raiffeisen_status'])) {
            $data['payment_raiffeisen_status'] = $this->request->post['payment_raiffeisen_status'];
        } else {
            $data['payment_raiffeisen_status'] = $this->config->get('payment_raiffeisen_status');
        }

        if (isset($this->request->post['payment_raiffeisen_sort_order'])) {
            $data['payment_raiffeisen_sort_order'] = $this->request->post['payment_raiffeisen_sort_order'];
        } else {
            $data['payment_raiffeisen_sort_order'] = $this->config->get('payment_raiffeisen_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        // вывод вида
        $this->response->setOutput($this->load->view('extension/payment/raiffeisen', $data));
    }

    /**
     * Получение налоговых ставок
     * @return [type] [description]
     */
    private function get_tax_rules()
    {
        return [
            [
                'id' => 'NONE',
                'name' => 'без НДС',
            ],
            [
                'id' => 'VAT10',
                'name' => 'НДС по ставке 0%',
            ],
            [
                'id' => 'VAT10',
                'name' => 'НДС чека по ставке 10%',
            ],
            [
                'id' => 'VAT110',
                'name' => 'НДС чека по расчетной ставке 10/110',
            ],
            [
                'id' => 'VAT20',
                'name' => 'НДС чека по ставке 20%',
            ],
            [
                'id' => 'VAT120',
                'name' => 'НДС чека по расчетной ставке 20/120',
            ],
        ];
    }

    /**
     * Получение опций по использования онлайн касс (получение чека)
     */
    private function get_cash_receipt_options()
    {
        return [
            [
                'id' => '',
                'name' => 'Нет чека',
            ],
            [
                'id' => 'ffd_105',
                'name' => 'ФФД 1.02',
            ],
            [
                'id' => 'ffd_12',
                'name' => 'ФФД 1.2'
            ],
        ];
    }

    /**
     * Получение списка опций для единиц измерения
     * @return string[][]
     */
    private function get_unit_options()
    {
        return [
            [
                'id' => 'PIECE',
                'name' => 'штука/единица/дробный товар',
            ],
            [
                'id' => 'GRAM',
                'name' => 'грамм',
            ],
            [
                'id' => 'TON',
                'name' => 'тонна',
            ],
            [
                'id' => 'CENTIMETER',
                'name' => 'сантиметр',
            ],
            [
                'id' => 'DECIMETER',
                'name' => 'дециметр',
            ],
            [
                'id' => 'METER',
                'name' => 'метр',
            ],
            [
                'id' => 'SQUARE_CENTIMETER',
                'name' => 'кв. сантиметр',
            ],
            [
                'id' => 'SQUARE_DECIMETER',
                'name' => 'кв. дециметр',
            ],
            [
                'id' => 'SQUARE_METER',
                'name' => 'кв. метр',
            ],
            [
                'id' => 'MILLILITER',
                'name' => 'миллилитр',
            ],
            [
                'id' => 'LITER',
                'name' => 'литр',
            ],
            [
                'id' => 'CUBIC_METER',
                'name' => 'куб. метр',
            ],
            [
                'id' => 'KILOWATT_HOUR',
                'name' => 'киловатт-час',
            ],
            [
                'id' => 'GIGACALORIE',
                'name' => 'гигакалория',
            ],
            [
                'id' => 'DAY',
                'name' => 'сутки/день',
            ],
            [
                'id' => 'HOUR',
                'name' => 'час',
            ],
            [
                'id' => 'MINUTE',
                'name' => 'минута',
            ],
            [
                'id' => 'SECOND',
                'name' => 'SECOND',
            ],
            [
                'id' => 'KILOBYTE',
                'name' => 'килобайт',
            ],
            [
                'id' => 'MEGABYTE',
                'name' => 'мегабайт',
            ],
            [
                'id' => 'GIGABYTE',
                'name' => 'гигабайт',
            ],
            [
                'id' => 'TERABYTE',
                'name' => 'терабайт',
            ],
            [
                'id' => 'OTHER',
                'name' => 'иное',
            ],
        ];
    }

    /**
     * Get API version options
     *
     * @return array
     * @since
     */
    public static function get_payment_method_options()
    {
        return [
            [
                'id' => '',
                'name' => 'По умолчанию',
            ],
            [
                'id' => 'ONLY_SBP',
                'name' => 'ONLY_SBP',
            ],
            [
                'id' => 'ONLY_ACQUIRING',
                'name' => 'ONLY_ACQUIRING',
            ],
        ];
    }

    /**
     * Возвращает опции по выбору языка
     * @return string[][]
     */
    public static function get_locale_options()
    {
        return [
            [
                'id' => 'ru',
                'name' => 'ru',
            ],
            [
                'id' => 'en',
                'name' => 'en',
            ],
        ];
    }

    /**
     * Get API version options
     *
     * @return array
     * @since
     */
    public static function get_payment_object_options()
    {
        return [
            [
                'id' => 'COMMODITY',
                'name' => 'товар, который не является подакцизным и не подлежит маркировке',
            ],
            [
                'id' => 'COMMODITY_MARKING_NO_CODE',
                'name' => 'товар, который не является подакцизным, подлежит маркировке, но не имеет кода маркировки',
            ],
            [
                'id' => 'COMMODITY_MARKING_WITH_CODE',
                'name' => 'товар, который не является подакцизным, подлежит маркировке и имеет код маркировки',
            ],
            [
                'id' => 'EXCISE',
                'name' => 'подакцизный товар, который не подлежит маркировке',
            ],
            [
                'id' => 'EXCISE_MARKING_NO_CODE',
                'name' => 'подакцизный товар, который подлежит маркировке, но не имеет кода маркировки',
            ],
            [
                'id' => 'EXCISE_MARKING_WITH_CODE',
                'name' => 'подакцизный товар, который подлежит маркировке и имеет код маркировки',
            ],
            [
                'id' => 'JOB',
                'name' => 'работа',
            ],
            [
                'id' => 'SERVICE',
                'name' => 'услуга',
            ],
            [
                'id' => 'PAYMENT',
                'name' => 'платеж',
            ],
            [
                'id' => 'ANOTHER',
                'name' => 'иной предмет расчета',
            ],
        ];
    }

    /**
     * Опции для способа расчета.
     */
    public static function get_payment_mode_options() {
        return [
            [
                'id' => 'FULL_PREPAYMENT',
                'name' => '100% предоплата до момента передачи предмета расчета',
            ],
            [
                'id' => 'FULL_PAYMENT',
                'name' => 'полная оплата в момент передачи предмета расчета',
            ],
            [
                'id' => 'ADVANCE',
                'name' => 'аванс',
            ],
            [
                'id' => 'PREPAYMENT',
                'name' => 'частичная предоплата до момента передачи предмета расчета',
            ],
        ];
    }

    /**
     * Валидация формы
     * @return bool
     */
    private function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/raiffeisen')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_raiffeisen_public_id']) {
            $this->error['payment_raiffeisen_public_id'] = $this->language->get('error_payment_raiffeisen_public_id');
        }

        if (!$this->request->post['payment_raiffeisen_secret_key']) {
            $this->error['payment_raiffeisen_secret_key'] = $this->language->get('error_payment_raiffeisen_secret_key');
        }

        return !$this->error;
    }


    /**
     * Получение заказа внизу формы
     */
    public function order()
    {
        if ($this->config->get('payment_raiffeisen_status')) {
            $this->load->language('extension/payment/raiffeisen_order');

            if (isset($this->request->get['order_id'])) {
                $order_id = $this->request->get['order_id'];
            } else {
                $order_id = 0;
            }

            $this->load->model('extension/payment/raiffeisen');

            $raiffeisen_info = $this->model_extension_payment_raiffeisen->getRaiffeisenOrder($order_id);

            if ($raiffeisen_info) {
                $data['user_token'] = $this->session->data['user_token'];

                $data['order_id'] = $this->request->get['order_id'];

                $data['status'] = $raiffeisen_info['status'];

                $data['amount'] = $raiffeisen_info['amount'];

                $captured = number_format($this->model_extension_payment_raiffeisen->getCapturedTotal($raiffeisen_info['raiffeisen_order_id']),
                    2);

                $data['captured'] = $captured;

                $data['capture_remaining'] = number_format($raiffeisen_info['amount'] - $captured, 2);

                $refunded = number_format($this->model_extension_payment_raiffeisen->getRefundedTotal($raiffeisen_info['raiffeisen_order_id']),2);

                $data['refunded'] = $refunded;

                return $this->load->view('extension/payment/raiffeisen_order', $data);
            }
        }
    }

    /**
     * Вывод информации по транзакциям
     */
    public function transaction()
    {
        $this->load->language('extension/payment/raiffeisen');

        $data['transactions'] = array();

        if (isset($this->request->get['order_id'])) {
            $order_id = $this->request->get['order_id'];
        } else {
            $order_id = 0;
        }

        $this->load->model('extension/payment/raiffeisen');

        $raiffeisen_info = $this->model_extension_payment_raiffeisen->getOrder($order_id);

        if ($raiffeisen_info) {
            $results = $this->model_extension_payment_raiffeisen->getTransactions($order_id);

            foreach ($results as $result) {
                $data['transactions'][] = array(
                    'raiffeisen_transaction_item_id' => $result['raiffeisen_transaction_item_id'],
                    'transaction_id' => $result['transaction_id'],
                    'amount' => $result['amount'],
                    'price' => $result['price'],
                    'qty' => $result['qty'],
                    'name' => $result['name'],
                    'nomenclature_code' => $result['nomenclature_code'],
                    'code' => $result['code'],
                    'operation' => $result['operation'],
                    'created_at' => date($this->language->get('datetime_format'), strtotime($result['created_at'])),
                    'refund' => $this->url->link('extension/payment/raiffeisen/refund',
                        'user_token='.$this->session->data['user_token'].'&order_id='.$order_id.'&raiffeisen_transaction_item_id='.$result['raiffeisen_transaction_item_id'],
                        true),
                    'resend' => $this->url->link('extension/payment/raiffeisen/resend',
                        'user_token='.$this->session->data['user_token'].'&order_id='.$order_id.'&raiffeisen_transaction_item_id='.$result['raiffeisen_transaction_item_id'],
                        true)
                );
            }
        }

        $this->response->setOutput($this->load->view('extension/payment/raiffeisen_transaction', $data));
    }

    /**
     * Возврат по платежу
     */
    public function refund() {
        $this->load->language('extension/payment/raiffeisen');

        if (isset($this->request->get['order_id'])) {
            $order_id = $this->request->get['order_id'];
        } else {
            $order_id = 0;
        }

        if (isset($this->request->get['raiffeisen_transaction_item_id'])) {
            $raiffeisen_transaction_item_id = $this->request->get['raiffeisen_transaction_item_id'];
        } else {
            $raiffeisen_transaction_item_id = 0;
        }

        if (!$raiffeisen_transaction_item_id || !$order_id) {
            $this->response->redirect($this->url->link('sale/order',
                'user_token='.$this->session->data['user_token'], true));
        }

        $raiffeisenClient = new \RF\Api\Client($this->config->get('payment_raiffeisen_secret_key'),
            $this->config->get('payment_raiffeisen_public_id'),
            $this->config->get('payment_raiffeisen_env_url')
        );

        $this->load->model('sale/order');
        $order = $this->model_sale_order->getOrder($order_id);

        $this->load->model('extension/payment/raiffeisen');
        $RaiffeisenRefundItem = $this->model_extension_payment_raiffeisen->createRefund($raiffeisen_transaction_item_id);

        if ($this->config->get('payment_raiffeisen_cash_receipt')) {
            $receipt =
                [
                    'customer' => [
                        'email' => $order['email'],
                    ],
                    'items' =>
                        [
                            [
                                'name' => $RaiffeisenRefundItem['name'],
                                'price'  => $RaiffeisenRefundItem['price'],
                                'quantity'  => $RaiffeisenRefundItem['qty'],
                                'amount'  => $RaiffeisenRefundItem['amount'],
//                                'paymentObject'  => $RaiffeisenRefundItem['payment_object'],
//                                'paymentMode'  => $RaiffeisenRefundItem['payment_mode'],
//                                'measurementUnit'  => $RaiffeisenRefundItem['measurement_unit'],
                                'vatType'  => $RaiffeisenRefundItem['vat_type'],
                            ]
                        ]
                ];

            $this->logger($receipt, '$receipt');

            $responseData = $raiffeisenClient->postOrderRefund($order_id, $RaiffeisenRefundItem['raiffeisen_transaction_item_id'], $RaiffeisenRefundItem['amount'], $receipt);
        } else {
            $responseData = $raiffeisenClient->postOrderRefund($order_id, $RaiffeisenRefundItem['raiffeisen_transaction_item_id'], $RaiffeisenRefundItem['amount']);
        }
        $this->logger($responseData, '$responseData');
        if ($responseData['refundStatus'] === 'COMPLETED' && $responseData['amount'] == $RaiffeisenRefundItem['amount']) {
            $this->model_extension_payment_raiffeisen->updateRaiffeisenTransactionItemOperation($RaiffeisenRefundItem['raiffeisen_transaction_item_id'], 'refunded');
        }

        return $this->response->redirect($this->url->link('sale/order/info',
            'user_token='.$this->session->data['user_token'].'&order_id='.$order_id.'#tab-raiffeisen', true));

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

}

