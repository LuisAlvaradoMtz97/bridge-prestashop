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

