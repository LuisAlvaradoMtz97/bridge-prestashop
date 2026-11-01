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

namespace BridgePrestashop\Handler;

if (!defined('_PS_VERSION_')) {
    exit;
}

use BridgePrestashop\Config\ModuleConfig;
use BridgePrestashop\DTO\OrderPayload;
use BridgePrestashop\HttpClient\WebhookSender;
use BridgePrestashop\Logger\BridgeLogger;
use Order;
use Throwable;

class OrderEventHandler
{
    private WebhookSender $sender;

    public function __construct(?WebhookSender $sender = null)
    {
        $this->sender = $sender ?? new WebhookSender();
    }

    /**
     * Procesa la creación de un nuevo pedido (actionValidateOrder).
     */
    public function handleOrderCreated(array $params): void
    {
        if (!ModuleConfig::isEnabled() || !ModuleConfig::shouldNotifyOrders()) {
            return;
        }

        try {
            $order = $params['order'] ?? null;
            if (!$order instanceof Order || !(int) $order->id) {
                BridgeLogger::warning('Se recibió un evento de nuevo pedido pero sin instancia válida de Order.');

                return;
            }

            $payload = OrderPayload::fromOrder($order, 'order.created');
            $this->sender->send($payload, (int) $order->id);
        } catch (Throwable $e) {
            BridgeLogger::error('Excepción no controlada en handleOrderCreated: ' . $e->getMessage());
        }
    }

    /**
     * Procesa el cambio de estado de un pedido (actionOrderStatusPostUpdate).
     */
    public function handleOrderStatusUpdated(array $params): void
    {
        if (!ModuleConfig::isEnabled() || !ModuleConfig::shouldNotifyOrders() || !ModuleConfig::shouldNotifyStatusChange()) {
            return;
        }

        try {
            $idOrder = (int) ($params['id_order'] ?? 0);
            if (!$idOrder) {
                return;
            }

            $order = new Order($idOrder);
            if (!(int) $order->id) {
                return;
            }

            $payload = OrderPayload::fromOrder($order, 'order.status_updated');
            $this->sender->send($payload, (int) $order->id);
        } catch (Throwable $e) {
            BridgeLogger::error('Excepción no controlada en handleOrderStatusUpdated: ' . $e->getMessage());
        }
    }
}
