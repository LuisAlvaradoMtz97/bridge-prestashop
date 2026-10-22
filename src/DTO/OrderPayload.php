<?php
declare(strict_types=1);

namespace BridgePrestashop\DTO;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Address;
use Carrier;
use Context;
use Country;
use Currency;
use Customer;
use Group;
use Order;
use OrderState;
use State;

class OrderPayload implements PayloadInterface
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromOrder(Order $order, string $eventName = 'order.created'): self
    {
        $context = Context::getContext();
        $idLang = $order->id_lang ?: ($context->language ? $context->language->id : 1);

        // Estado del pedido
        $orderState = new OrderState((int) $order->current_state, (int) $idLang);
        $statusName = is_array($orderState->name)
            ? ($orderState->name[$idLang] ?? reset($orderState->name))
            : $orderState->name;

        // Moneda
        $currency = new Currency((int) $order->id_currency);

        // Cliente
        $customer = new Customer((int) $order->id_customer);
        $defaultGroup = new Group((int) $customer->id_default_group, (int) $idLang);
        $defaultGroupName = is_array($defaultGroup->name)
            ? ($defaultGroup->name[$idLang] ?? reset($defaultGroup->name))
            : (string) $defaultGroup->name;

        $rawGroups = $customer->getGroups();
        if (empty($rawGroups)) {
            $rawGroups = [(int) $customer->id_default_group];
        }
        $customerGroups = array_values(array_map('intval', $rawGroups));

        // Direcciones
        $shippingAddress = new Address((int) $order->id_address_delivery);
        $invoiceAddress = new Address((int) $order->id_address_invoice);

        // Transportista
        $carrier = new Carrier((int) $order->id_carrier, (int) $idLang);

        // Productos
        $products = [];
        $orderProducts = $order->getProducts();
        if (is_array($orderProducts)) {
            foreach ($orderProducts as $p) {
                $products[] = [
                    'product_id' => (int) ($p['product_id'] ?? 0),
                    'product_attribute_id' => (int) ($p['product_attribute_id'] ?? 0),
                    'reference' => (string) ($p['product_reference'] ?? ''),
                    'name' => (string) ($p['product_name'] ?? ''),
                    'quantity' => (int) ($p['product_quantity'] ?? 0),
                    'unit_price_tax_incl' => (float) ($p['unit_price_tax_incl'] ?? 0.0),
                    'unit_price_tax_excl' => (float) ($p['unit_price_tax_excl'] ?? 0.0),
                    'total_price_tax_incl' => (float) ($p['total_price_tax_incl'] ?? 0.0),
                    'total_price_tax_excl' => (float) ($p['total_price_tax_excl'] ?? 0.0),
                ];
            }
        }

        // Tienda
        $shop = $context->shop;
        $shopUrl = $shop ? (string) $shop->getBaseURL(true) : '';
        $shopName = $shop ? (string) $shop->name : '';

        $data = [
            'event' => $eventName,
            'event_id' => self::generateUuidV4(),
            'timestamp' => date('c'),
            'shop' => [
                'id' => (int) $order->id_shop,
                'name' => $shopName,
                'url' => $shopUrl,
            ],
            'order' => [
                'id' => (int) $order->id,
                'reference' => (string) $order->reference,
                'status' => [
                    'id' => (int) $order->current_state,
                    'name' => (string) $statusName,
                ],
                'currency' => [
                    'iso_code' => (string) $currency->iso_code,
                    'sign' => (string) $currency->sign,
                ],
                'totals' => [
                    'total_paid_tax_incl' => (float) $order->total_paid_tax_incl,
                    'total_paid_tax_excl' => (float) $order->total_paid_tax_excl,
                    'total_products_tax_incl' => (float) $order->total_products_wt,
                    'total_products_tax_excl' => (float) $order->total_products,
                    'total_shipping_tax_incl' => (float) $order->total_shipping_tax_incl,
                    'total_shipping_tax_excl' => (float) $order->total_shipping_tax_excl,
                    'total_discounts_tax_incl' => (float) $order->total_discounts_tax_incl,
                    'total_discounts_tax_excl' => (float) $order->total_discounts_tax_excl,
                    'total_wrapping_tax_incl' => (float) $order->total_wrapping_tax_incl,
                    'total_wrapping_tax_excl' => (float) $order->total_wrapping_tax_excl,
                ],
                'payment_method' => (string) $order->payment,
                'module' => (string) $order->module,
                'date_add' => (string) $order->date_add,
                'date_upd' => (string) $order->date_upd,
            ],
            'customer' => [
                'id' => (int) $customer->id,
                'firstname' => (string) $customer->firstname,
                'lastname' => (string) $customer->lastname,
                'email' => (string) $customer->email,
                'company' => (string) $customer->company,
                'siret' => (string) $customer->siret,
                'ape' => (string) $customer->ape,
                'website' => (string) $customer->website,
                'is_guest' => (bool) $customer->is_guest,
                'default_group' => [
                    'id' => (int) $customer->id_default_group,
                    'name' => (string) $defaultGroupName,
                ],
                'groups' => $customerGroups,
            ],
            'shipping_address' => self::formatAddress($shippingAddress, $idLang),
            'invoice_address' => self::formatAddress($invoiceAddress, $idLang),
            'carrier' => [
                'id' => (int) $carrier->id,
                'name' => (string) $carrier->name,
            ],
            'items' => $products,
        ];

        return new self($data);
    }

    public static function createTestPayload(): self
    {
        $context = Context::getContext();
        $shop = $context->shop;

        $data = [
            'event' => 'webhook.test',
            'event_id' => self::generateUuidV4(),
            'timestamp' => date('c'),
            'shop' => [
                'id' => $shop ? (int) $shop->id : 1,
                'name' => $shop ? (string) $shop->name : 'PrestaShop 9',
                'url' => $shop ? (string) $shop->getBaseURL(true) : 'https://example.com/',
            ],
            'message' => 'Este es un mensaje de prueba emitido por el módulo Bridge PrestaShop.',
            'version' => '1.0.0',
            'test_order' => [
                'id' => 99999,
                'reference' => 'TEST-ORDER-001',
                'status' => [
                    'id' => 2,
                    'name' => 'Pago aceptado (Test)',
                ],
                'currency' => [
                    'iso_code' => 'USD',
                    'sign' => '$',
                ],
                'totals' => [
                    'total_paid_tax_incl' => 99.99,
                    'total_paid_tax_excl' => 86.20,
                ],
                'payment_method' => 'Test Payment Gateway',
            ],
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
        return (string) ($this->data['event'] ?? 'order.created');
    }

    public function getEventId(): string
    {
        return (string) ($this->data['event_id'] ?? '');
    }

    private static function formatAddress(Address $address, int $idLang): array
    {
        $country = new Country((int) $address->id_country, $idLang);
        $state = new State((int) $address->id_state);

        $countryName = is_array($country->name)
            ? ($country->name[$idLang] ?? reset($country->name))
            : $country->name;

        return [
            'alias' => (string) $address->alias,
            'firstname' => (string) $address->firstname,
            'lastname' => (string) $address->lastname,
            'company' => (string) $address->company,
            'address1' => (string) $address->address1,
            'address2' => (string) $address->address2,
            'postcode' => (string) $address->postcode,
            'city' => (string) $address->city,
            'state' => (string) $state->name,
            'country' => (string) $countryName,
            'country_iso' => (string) $country->iso_code,
            'phone' => (string) $address->phone,
            'phone_mobile' => (string) $address->phone_mobile,
            'vat_number' => (string) $address->vat_number,
            'dni' => (string) $address->dni,
        ];
    }

    private static function generateUuidV4(): string
    {
        try {
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
            $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        } catch (\Throwable) {
            return sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
            );
        }
    }
}
