<?php
declare(strict_types=1);

namespace BridgePrestashop\DTO;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Context;
use Customer;
use Group;
use Throwable;

class CustomerPayload implements PayloadInterface
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function fromCustomer(Customer $customer, string $eventName = 'customer.created'): self
    {
        $context = Context::getContext();
        $idLang = (int) ($customer->id_lang ?: ($context->language ? $context->language->id : 1));

        $defaultGroup = new Group((int) $customer->id_default_group, $idLang);
        $defaultGroupName = is_array($defaultGroup->name)
            ? ($defaultGroup->name[$idLang] ?? reset($defaultGroup->name))
            : (string) $defaultGroup->name;

        // Tienda
        $shop = $context->shop;
        $shopUrl = $shop ? (string) $shop->getBaseURL(true) : '';
        $shopName = $shop ? (string) $shop->name : '';

        // Grupos a los que pertenece
        $rawGroups = $customer->getGroups();
        if (empty($rawGroups)) {
            $rawGroups = [(int) $customer->id_default_group];
        }
        $groups = array_values(array_map('intval', $rawGroups));

        $data = [
            'event' => $eventName,
            'event_id' => self::generateUuidV4(),
            'timestamp' => date('c'),
            'shop' => [
                'id' => (int) ($customer->id_shop ?: ($shop ? $shop->id : 1)),
                'name' => $shopName,
                'url' => $shopUrl,
            ],
            'customer' => [
                'id' => (int) $customer->id,
                'id_gender' => (int) $customer->id_gender,
                'firstname' => (string) $customer->firstname,
                'lastname' => (string) $customer->lastname,
                'email' => (string) $customer->email,
                'company' => (string) $customer->company,
                'siret' => (string) $customer->siret,
                'ape' => (string) $customer->ape,
                'website' => (string) $customer->website,
                'birthday' => (string) $customer->birthday,
                'newsletter' => (bool) $customer->newsletter,
                'optin' => (bool) $customer->optin,
                'active' => (bool) $customer->active,
                'is_guest' => (bool) $customer->is_guest,
                'default_group' => [
                    'id' => (int) $customer->id_default_group,
                    'name' => (string) $defaultGroupName,
                ],
                'groups' => $groups,
                'date_add' => (string) $customer->date_add,
                'date_upd' => (string) $customer->date_upd,
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
        return (string) ($this->data['event'] ?? 'customer.created');
    }

    public function getEventId(): string
    {
        return (string) ($this->data['event_id'] ?? '');
    }

    private static function generateUuidV4(): string
    {
        try {
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
            $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
        } catch (Throwable) {
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
