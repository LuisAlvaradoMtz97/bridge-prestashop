<?php
declare(strict_types=1);

namespace BridgePrestashop\Handler;

if (!defined('_PS_VERSION_')) {
    exit;
}

use BridgePrestashop\Config\ModuleConfig;
use BridgePrestashop\DTO\CustomerMessagePayload;
use BridgePrestashop\HttpClient\WebhookSender;
use BridgePrestashop\Logger\BridgeLogger;
use CustomerMessage;
use CustomerThread;
use Throwable;
use Validate;

class CustomerMessageEventHandler 
{
    private WebhookSender $sender;

    public function __construct(?WebhookSender $sender = null)
    {
        $this->sender = $sender ?? new WebhookSender();
    }

public function handleCustomerMessageCreated(array $params) : void
    {
        if(!ModuleConfig::isEnabled() || !ModuleConfig::shouldNotifyCustomerMessage()){
            return;
        }

        try
        {
            $customerMessage = $params['object'] ?? null;
            if(!$customerMessage instanceof CustomerMessage || !(int) $customerMessage->id){
                BridgeLogger::warning('Se recibio un evento de mensaje de cliente pero sin instalcia valida de CustomerMessage.');
                return;
            }

            // VALIDACIÓN CLAVE: Si tiene id_employee > 0, lo escribió un empleado en el Back Office. Lo ignoramos.
            if ((int) $customerMessage->id_employee > 0) {
                return; 
            }

            // Validación adicional: Asegurar que el hilo del cliente exista y tenga un cliente asociado
            $customerThread = new CustomerThread((int) $customerMessage->id_customer_thread);
            if (!Validate::isLoadedObject($customerThread) || !(int) $customerThread->id_customer) {
                BridgeLogger::warning('El mensaje de cliente no está asociado a un CustomerThread válido.');
                return;
            }
            

            $payload = CustomerMessagePayload::fromCustomerMessagePayload($customerMessage, 'customerMessage.created');
            $this->sender->send($payload, (int) $customerMessage->id);
        }
        catch(Throwable $e){
            BridgeLogger::error('Excepcion no controlada en handleCustomerMessageCreated: ' . $e->getMessage());
        }
    }
}
    