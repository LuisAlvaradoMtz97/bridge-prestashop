<?php
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
        if (!ModuleConfig::isEnabled()) {
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
        if (!ModuleConfig::isEnabled() || !ModuleConfig::shouldNotifyStatusChange()) {
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
