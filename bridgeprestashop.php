<?php
/**
 * 2026 Bridge PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 *
 * @author    Luis Eduardo Alvarado Martínez <luis.alvaradomtz97@gmail.com>
 * @copyright 2026 Bridge PrestaShop
 * @license   https://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

// Autoloader fallback para el namespace BridgePrestashop\
spl_autoload_register(static function (string $class): void {
    $prefix = 'BridgePrestashop\\';
    if (str_starts_with($class, $prefix)) {
        $relativeClass = substr($class, strlen($prefix));
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

use BridgePrestashop\Config\ModuleConfig;
use BridgePrestashop\Handler\CustomerEventHandler;
use BridgePrestashop\Handler\OrderEventHandler;
use BridgePrestashop\HttpClient\WebhookSender;

class BridgePrestashop extends Module
{
    public function __construct()
    {
        $this->name = 'bridgeprestashop';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Luis Eduardo Alvarado Martínez';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->emailing = 'luis.alvaradomtz97@gmail.com';

        // Compatible con PrestaShop 9.0 en adelante
        $this->ps_versions_compliancy = [
            'min' => '9.0.0',
            'max' => '9.9.9',
        ];

        parent::__construct();

        $this->displayName = $this->l('Bridge PrestaShop');
        $this->description = $this->l('Envía notificaciones vía Webhook a plataformas de terceros cuando se crea o actualiza un pedido.');
        $this->confirmUninstall = $this->l('¿Estás seguro de que deseas desinstalar Bridge PrestaShop? Se perderá la configuración de los webhooks.');
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('actionValidateOrder')
            // && $this->registerHook('actionOrderStatusUpdate')
            // && $this->registerHook('actionObjectAddAfter')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('actionCartSave')
            && $this->registerHook('actionCustomerAccountAdd')
            && ModuleConfig::installDefaults();
    }

    public function uninstall(): bool
    {
        return ModuleConfig::uninstall() && parent::uninstall();
    }

    /**
     * Interfaz de configuración del módulo en el Backoffice.
     */
    public function getContent(): string
    {
        $output = '';

        // Procesar guardado de configuración
        if (Tools::isSubmit('submitBridgePrestashopConfig')) {
            $output .= $this->processConfiguration();
        }

        // Procesar prueba de webhook
        if (Tools::isSubmit('submitBridgePrestashopTest')) {
            $output .= $this->processTestWebhook();
        }

        return $output . $this->renderConfigurationForm();
    }

    /**
     * Hook disparado inmediatamente tras crearse y validarse un pedido.
     */
    public function hookActionValidateOrder(array $params): void
    {
        $handler = new OrderEventHandler();
        $handler->handleOrderCreated($params);
    }

    // public function hookActionOrderStatusUpdate(array $params): void 
    // {
    //     $handler = new OrderEventHandler();
    //     $handler->handleOrderStatusUpdated($params)
    // }

    // public function hookActionObjectAddAfter(array $params): void 
    // {
    //     $handler = new OrderEventHandler();
    //     $handler->handleOrderCreated($params);
    // }

    /**
     * Hook disparado cuando cambia el estado de un pedido.
     */
    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $handler = new OrderEventHandler();
        $handler->handleOrderStatusUpdated($params);
    }

    /**
     * Hook disparado inmediatamente tras registrarse un cliente.
     */
    public function hookActionCustomerAccountAdd(array $params): void
    {
        $handler = new CustomerEventHandler();
        $handler->handleCustomerCreated($params);
    }

    public function hookActionCartSave(array $params): void
    {
    $handler = new CustomerEventHandler();
        $handler->handleCustomerCreated($params);
    
    }

    private function processConfiguration(): string
    {
        $url = trim((string) Tools::getValue(ModuleConfig::KEY_WEBHOOK_URL));
        $enabled = (bool) Tools::getValue(ModuleConfig::KEY_ENABLED);
        $secret = trim((string) Tools::getValue(ModuleConfig::KEY_SECRET_TOKEN));
        $timeout = (int) Tools::getValue(ModuleConfig::KEY_TIMEOUT);
        $verifySsl = (bool) Tools::getValue(ModuleConfig::KEY_VERIFY_SSL);
        $notifyOrders = (bool) Tools::getValue(ModuleConfig::KEY_NOTIFY_ORDERS);
        $notifyStatus = (bool) Tools::getValue(ModuleConfig::KEY_NOTIFY_STATUS_CHANGE);
        $notifyCustomer = (bool) Tools::getValue(ModuleConfig::KEY_NOTIFY_CUSTOMER_REGISTRATION);
        $logRequests = (bool) Tools::getValue(ModuleConfig::KEY_LOG_REQUESTS);

        if ($enabled && (empty($url) || !filter_var($url, FILTER_VALIDATE_URL))) {
            return $this->displayError($this->l('Debes ingresar una URL válida para el Webhook si el módulo está habilitado.'));
        }

        if ($timeout < 1 || $timeout > 30) {
            return $this->displayError($this->l('El tiempo de espera (timeout) debe estar entre 1 y 30 segundos.'));
        }

        Configuration::updateValue(ModuleConfig::KEY_ENABLED, $enabled ? '1' : '0');
        Configuration::updateValue(ModuleConfig::KEY_WEBHOOK_URL, $url);
        Configuration::updateValue(ModuleConfig::KEY_SECRET_TOKEN, $secret);
        Configuration::updateValue(ModuleConfig::KEY_TIMEOUT, (string) $timeout);
        Configuration::updateValue(ModuleConfig::KEY_VERIFY_SSL, $verifySsl ? '1' : '0');
        Configuration::updateValue(ModuleConfig::KEY_NOTIFY_ORDERS, $notifyOrders ? '1' : '0');
        Configuration::updateValue(ModuleConfig::KEY_NOTIFY_STATUS_CHANGE, $notifyStatus ? '1' : '0');
        Configuration::updateValue(ModuleConfig::KEY_NOTIFY_CUSTOMER_REGISTRATION, $notifyCustomer ? '1' : '0');
        Configuration::updateValue(ModuleConfig::KEY_LOG_REQUESTS, $logRequests ? '1' : '0');

        return $this->displayConfirmation($this->l('Configuración guardada correctamente.'));
    }

    private function processTestWebhook(): string
    {
        $sender = new WebhookSender();
        $result = $sender->sendTest();

        if ($result['success']) {
            return $this->displayConfirmation(
                sprintf(
                    $this->l('¡Prueba exitosa! El webhook respondió HTTP %d en %s ms.'),
                    $result['http_code'],
                    $result['duration_ms']
                )
            );
        }

        return $this->displayError(
            sprintf(
                $this->l('Error en la prueba del webhook. Código HTTP: %d | Error: %s | Tiempo: %s ms'),
                $result['http_code'],
                $result['error'] ?? 'Sin respuesta',
                $result['duration_ms']
            )
        );
    }

    private function renderConfigurationForm(): string
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitBridgePrestashopConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value = [
            ModuleConfig::KEY_ENABLED => Configuration::get(ModuleConfig::KEY_ENABLED),
            ModuleConfig::KEY_WEBHOOK_URL => Configuration::get(ModuleConfig::KEY_WEBHOOK_URL),
            ModuleConfig::KEY_SECRET_TOKEN => Configuration::get(ModuleConfig::KEY_SECRET_TOKEN),
            ModuleConfig::KEY_TIMEOUT => Configuration::get(ModuleConfig::KEY_TIMEOUT) ?: ModuleConfig::DEFAULT_TIMEOUT,
            ModuleConfig::KEY_VERIFY_SSL => Configuration::get(ModuleConfig::KEY_VERIFY_SSL),
            ModuleConfig::KEY_NOTIFY_ORDERS => Configuration::get(ModuleConfig::KEY_NOTIFY_ORDERS) !== false ? Configuration::get(ModuleConfig::KEY_NOTIFY_ORDERS) : (ModuleConfig::DEFAULT_NOTIFY_ORDERS ? '1' : '0'),
            ModuleConfig::KEY_NOTIFY_STATUS_CHANGE => Configuration::get(ModuleConfig::KEY_NOTIFY_STATUS_CHANGE),
            ModuleConfig::KEY_NOTIFY_CUSTOMER_REGISTRATION => Configuration::get(ModuleConfig::KEY_NOTIFY_CUSTOMER_REGISTRATION),
            ModuleConfig::KEY_LOG_REQUESTS => Configuration::get(ModuleConfig::KEY_LOG_REQUESTS),
        ];

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Configuración del Bridge de Notificaciones Webhook'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Habilitar Bridge'),
                        'name' => ModuleConfig::KEY_ENABLED,
                        'is_bool' => true,
                        'desc' => $this->l('Activa o desactiva el envío de eventos de pedidos.'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Sí'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('URL del Webhook de destino'),
                        'name' => ModuleConfig::KEY_WEBHOOK_URL,
                        'size' => 60,
                        'required' => true,
                        'desc' => $this->l('URL completa (ej. https://api.tercero.com/webhooks/orders) que recibirá las peticiones POST en JSON.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Token / Clave Secreta'),
                        'name' => ModuleConfig::KEY_SECRET_TOKEN,
                        'size' => 60,
                        'desc' => $this->l('Se enviará en la cabecera Authorization (Bearer <token>) y en la firma HMAC-SHA256 (X-Bridge-Signature) para que el receptor verifique la autenticidad.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Tiempo de espera (Timeout en segundos)'),
                        'name' => ModuleConfig::KEY_TIMEOUT,
                        'size' => 10,
                        'desc' => $this->l('Tiempo máximo que PrestaShop esperará respuesta del servidor externo. Recomendado: 3 a 5 segundos para no ralentizar el checkout.'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Verificar certificado SSL'),
                        'name' => ModuleConfig::KEY_VERIFY_SSL,
                        'is_bool' => true,
                        'desc' => $this->l('Mantener activado en entornos de producción por seguridad.'),
                        'values' => [
                            ['id' => 'ssl_on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'ssl_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Notificar pedidos / órdenes'),
                        'name' => ModuleConfig::KEY_NOTIFY_ORDERS,
                        'is_bool' => true,
                        'desc' => $this->l('Si se activa, enviará una notificación (order.created) cuando un cliente confirme un pedido.'),
                        'values' => [
                            ['id' => 'orders_on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'orders_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Notificar también cambios de estado'),
                        'name' => ModuleConfig::KEY_NOTIFY_STATUS_CHANGE,
                        'is_bool' => true,
                        'desc' => $this->l('Si se activa y las notificaciones de pedidos están habilitadas, también enviará una notificación (order.status_updated) cuando cambie el estado del pedido.'),
                        'values' => [
                            ['id' => 'status_on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'status_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Notificar registro de nuevos clientes'),
                        'name' => ModuleConfig::KEY_NOTIFY_CUSTOMER_REGISTRATION,
                        'is_bool' => true,
                        'desc' => $this->l('Si se activa, enviará una notificación (customer.created) cada vez que un cliente se registre en la tienda.'),
                        'values' => [
                            ['id' => 'cust_on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'cust_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Registrar logs en PrestaShop'),
                        'name' => ModuleConfig::KEY_LOG_REQUESTS,
                        'is_bool' => true,
                        'desc' => $this->l('Guarda registros de los envíos en Parámetros Avanzados > Registros / Logs.'),
                        'values' => [
                            ['id' => 'log_on', 'value' => 1, 'label' => $this->l('Sí')],
                            ['id' => 'log_off', 'value' => 0, 'label' => $this->l('No')],
                        ],
                    ],
                ],
                'buttons' => [
                    [
                        'type' => 'submit',
                        'title' => $this->l('Probar Webhook ahora'),
                        'name' => 'submitBridgePrestashopTest',
                        'icon' => 'process-icon-refresh',
                        'class' => 'btn btn-info pull-left',
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Guardar configuración'),
                    'class' => 'btn btn-primary pull-right',
                ],
            ],
        ];

        return $helper->generateForm([$fieldsForm]);
    }
}
