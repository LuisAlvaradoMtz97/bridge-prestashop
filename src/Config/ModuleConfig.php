<?php
declare(strict_types=1);

namespace BridgePrestashop\Config;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Configuration;

class ModuleConfig
{
    public const KEY_ENABLED = 'BRIDGEPRESTASHOP_ENABLED';
    public const KEY_WEBHOOK_URL = 'BRIDGEPRESTASHOP_WEBHOOK_URL';
    public const KEY_SECRET_TOKEN = 'BRIDGEPRESTASHOP_SECRET_TOKEN';
    public const KEY_TIMEOUT = 'BRIDGEPRESTASHOP_TIMEOUT';
    public const KEY_VERIFY_SSL = 'BRIDGEPRESTASHOP_VERIFY_SSL';
    public const KEY_NOTIFY_ORDERS = 'BRIDGEPRESTASHOP_NOTIFY_ORDERS';
    public const KEY_NOTIFY_STATUS_CHANGE = 'BRIDGEPRESTASHOP_NOTIFY_STATUS';
    public const KEY_NOTIFY_CUSTOMER_REGISTRATION = 'BRIDGEPRESTASHOP_NOTIFY_CUSTOMER';
    public const KEY_LOG_REQUESTS = 'BRIDGEPRESTASHOP_LOG_REQUESTS';

    public const DEFAULT_TIMEOUT = 5;
    public const DEFAULT_VERIFY_SSL = true;
    public const DEFAULT_ENABLED = true;
    public const DEFAULT_NOTIFY_ORDERS = true;
    public const DEFAULT_NOTIFY_STATUS = true;
    public const DEFAULT_NOTIFY_CUSTOMER_REGISTRATION = true;
    public const DEFAULT_LOG_REQUESTS = true;

    /**
     * Instala los valores por defecto en la configuración de PrestaShop.
     */
    public static function installDefaults(): bool
    {
        $res = true;
        $res = $res && Configuration::updateValue(self::KEY_ENABLED, self::DEFAULT_ENABLED ? '1' : '0');
        $res = $res && Configuration::updateValue(self::KEY_WEBHOOK_URL, '');
        $res = $res && Configuration::updateValue(self::KEY_SECRET_TOKEN, self::generateRandomToken());
        $res = $res && Configuration::updateValue(self::KEY_TIMEOUT, (string) self::DEFAULT_TIMEOUT);
        $res = $res && Configuration::updateValue(self::KEY_VERIFY_SSL, self::DEFAULT_VERIFY_SSL ? '1' : '0');
        $res = $res && Configuration::updateValue(self::KEY_NOTIFY_ORDERS, self::DEFAULT_NOTIFY_ORDERS ? '1' : '0');
        $res = $res && Configuration::updateValue(self::KEY_NOTIFY_STATUS_CHANGE, self::DEFAULT_NOTIFY_STATUS ? '1' : '0');
        $res = $res && Configuration::updateValue(self::KEY_NOTIFY_CUSTOMER_REGISTRATION, self::DEFAULT_NOTIFY_CUSTOMER_REGISTRATION ? '1' : '0');
        $res = $res && Configuration::updateValue(self::KEY_LOG_REQUESTS, self::DEFAULT_LOG_REQUESTS ? '1' : '0');

        return $res;
    }

    /**
     * Limpia la configuración al desinstalar.
     */
    public static function uninstall(): bool
    {
        $keys = [
            self::KEY_ENABLED,
            self::KEY_WEBHOOK_URL,
            self::KEY_SECRET_TOKEN,
            self::KEY_TIMEOUT,
            self::KEY_VERIFY_SSL,
            self::KEY_NOTIFY_ORDERS,
            self::KEY_NOTIFY_STATUS_CHANGE,
            self::KEY_NOTIFY_CUSTOMER_REGISTRATION,
            self::KEY_LOG_REQUESTS,
        ];

        $res = true;
        foreach ($keys as $key) {
            $res = $res && Configuration::deleteByName($key);
        }

        return $res;
    }

    public static function isEnabled(): bool
    {
        return (bool) Configuration::get(self::KEY_ENABLED);
    }

    public static function getWebhookUrl(): string
    {
        return (string) Configuration::get(self::KEY_WEBHOOK_URL);
    }

    public static function getSecretToken(): string
    {
        return (string) Configuration::get(self::KEY_SECRET_TOKEN);
    }

    public static function getTimeout(): int
    {
        $timeout = (int) Configuration::get(self::KEY_TIMEOUT);
        return $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
    }

    public static function isVerifySsl(): bool
    {
        $val = Configuration::get(self::KEY_VERIFY_SSL);
        return $val === false ? self::DEFAULT_VERIFY_SSL : (bool) $val;
    }

    public static function shouldNotifyOrders(): bool
    {
        $val = Configuration::get(self::KEY_NOTIFY_ORDERS);
        return $val === false ? self::DEFAULT_NOTIFY_ORDERS : (bool) $val;
    }

    public static function shouldNotifyStatusChange(): bool
    {
        return (bool) Configuration::get(self::KEY_NOTIFY_STATUS_CHANGE);
    }

    public static function shouldNotifyCustomerRegistration(): bool
    {
        $val = Configuration::get(self::KEY_NOTIFY_CUSTOMER_REGISTRATION);
        return $val === false ? self::DEFAULT_NOTIFY_CUSTOMER_REGISTRATION : (bool) $val;
    }

    public static function shouldLogRequests(): bool
    {
        $val = Configuration::get(self::KEY_LOG_REQUESTS);
        return $val === false ? self::DEFAULT_LOG_REQUESTS : (bool) $val;
    }

    /**
     * Genera un token secreto criptográficamente seguro por defecto.
     */
    public static function generateRandomToken(int $bytes = 32): string
    {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (\Throwable) {
            return md5(uniqid((string) mt_rand(), true));
        }
    }
}
