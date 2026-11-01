<?php
declare(strict_types=1);

namespace BridgePrestashop\DTO;

if (!defined('_PS_VERSION_')) {
    exit;
}

use CustomerMessage;
use CustomerThread;
use Customer;
use Order;
use Validate;
use Context;

class CustomerMessagePayload implements PayloadInterface{
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromCustomerMessagePayload(CustomerMessage $message, string $eventName ='customerMessage.created'): self
    {
   // 1. Obtener el hilo de cliente para relacionar datos
        $customerThread = new CustomerThread((int) $message->id_customer_thread);
        
        $customer = null;
        $order = null;
        
        if (Validate::isLoadedObject($customerThread)) {
            // Si el hilo tiene un cliente asociado
            if (!empty($customerThread->id_customer)) {
                $customer = new Customer((int) $customerThread->id_customer);
            }
            // Si el hilo está vinculado a una orden específica
            if (!empty($customerThread->id_order)) {
                $order = new Order((int) $customerThread->id_order);
            }
        }

        // 2. Extraer datos básicos y de valor
        $data = [
            'event' => $eventName,
            'event_id' => 'msg_' . $message->id . '_' . time(),
            'timestamp' => date('c'),
            'message' => [
                'id' => (int) $message->id,
                'content' => $message->message,
                'date_add' => $message->date_add,
                'ip_address' => $message->ip_address ?? null,
            ],
            'customer' => [
                'id' => $customer && Validate::isLoadedObject($customer) ? (int) $customer->id : null,
                'firstname' => $customer && Validate::isLoadedObject($customer) ? $customer->firstname : null,
                'lastname' => $customer && Validate::isLoadedObject($customer) ? $customer->lastname : null,
                'email' => $customer && Validate::isLoadedObject($customer) ? $customer->email : null,
            ],
            'order' => $order && Validate::isLoadedObject($order) ? [
                'id' => (int) $order->id,
                'reference' => $order->reference,
                'total_paid' => (float) $order->total_paid,
                'date_add' => $order->date_add,
                'current_state' => (int) $order->getCurrentState(),
            ] : null,
            'metadata' => [
                'thread_id' => (int) $message->id_customer_thread,
                'shop_id'   => (int) Context::getContext()->shop->id,
            ]
        ];

        return new self($data);
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function toJson(): string
    {
        $json = json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false ? $json : '{}';
    }

       public function getEventName(): string
    {
        return (string) ($this->data['event'] ?? 'customer.created');
    }

    public function getEventId(): string
    {
        return (string) ($this->data['event_id'] ?? '');
    }
}