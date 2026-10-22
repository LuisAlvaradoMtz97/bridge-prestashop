<?php
declare(strict_types=1);

namespace BridgePrestashop\Handler;

if (!defined('_PS_VERSION_')) {
    exit;
}

use BridgePrestashop\Config\ModuleConfig;
use BridgePrestashop\DTO\CustomerPayload;
use BridgePrestashop\HttpClient\WebhookSender;
use BridgePrestashop\Logger\BridgeLogger;
use Customer;
use Throwable;

class CustomerEventHandler
{
    private WebhookSender $sender;

    public function __construct(?WebhookSender $sender = null)
    {
        $this->sender = $sender ?? new WebhookSender();
    }

    /**
     * Procesa el registro o creación de una nueva cuenta de cliente (actionCustomerAccountAdd).
     */
    public function handleCustomerCreated(array $params): void
    {
        if (!ModuleConfig::isEnabled() || !ModuleConfig::shouldNotifyCustomerRegistration()) {
            return;
        }

        try {
            /** @var Customer|null $customer */
            $customer = $params['newCustomer'] ?? $params['customer'] ?? null;
            if (!$customer instanceof Customer || !(int) $customer->id) {
                BridgeLogger::warning('Se recibió un evento de nuevo cliente pero sin instancia válida de Customer.');
                return;
            }

            $payload = CustomerPayload::fromCustomer($customer, 'customer.created');
            $this->sender->send($payload, (int) $customer->id);
        } catch (Throwable $e) {
            BridgeLogger::error('Excepción no controlada en handleCustomerCreated: ' . $e->getMessage());
        }
    }
}
