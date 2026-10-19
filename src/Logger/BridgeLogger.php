<?php
declare(strict_types=1);

namespace BridgePrestashop\Logger;

if (!defined('_PS_VERSION_')) {
    exit;
}

use BridgePrestashop\Config\ModuleConfig;
use PrestaShopLogger;

class BridgeLogger
{
    public const SEVERITY_INFO = 1;
    public const SEVERITY_WARNING = 2;
    public const SEVERITY_ERROR = 3;
    public const SEVERITY_FATAL = 4;

    private const MODULE_PREFIX = '[BridgePrestashop] ';

    public static function log(string $message, int $severity = self::SEVERITY_INFO, ?int $orderId = null): void
    {
        if ($severity === self::SEVERITY_INFO && !ModuleConfig::shouldLogRequests()) {
            return;
        }

        if (class_exists(PrestaShopLogger::class)) {
            PrestaShopLogger::addLog(
                self::MODULE_PREFIX . $message,
                $severity,
                null,
                'Order',
                $orderId,
                true
            );
        }
    }

    public static function info(string $message, ?int $orderId = null): void
    {
        self::log($message, self::SEVERITY_INFO, $orderId);
    }

    public static function warning(string $message, ?int $orderId = null): void
    {
        self::log($message, self::SEVERITY_WARNING, $orderId);
    }

    public static function error(string $message, ?int $orderId = null): void
    {
        self::log($message, self::SEVERITY_ERROR, $orderId);
    }
}
