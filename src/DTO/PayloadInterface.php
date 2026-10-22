<?php
declare(strict_types=1);

namespace BridgePrestashop\DTO;

if (!defined('_PS_VERSION_')) {
    exit;
}

interface PayloadInterface
{
    /**
     * Retorna el payload en formato array asociativo.
     */
    public function toArray(): array;

    /**
     * Serializa el payload a formato JSON.
     */
    public function toJson(): string;

    /**
     * Retorna el nombre del evento (ej. 'order.created', 'customer.created').
     */
    public function getEventName(): string;

    /**
     * Retorna el identificador único del evento para idempotencia.
     */
    public function getEventId(): string;
}
