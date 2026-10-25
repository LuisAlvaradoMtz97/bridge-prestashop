<?php
declare(strict_types=1);

namespace BridgePrestashop\HttpClient;

if (!defined('_PS_VERSION_')) {
    exit;
}

use BridgePrestashop\Config\ModuleConfig;
use BridgePrestashop\DTO\OrderPayload;
use BridgePrestashop\DTO\PayloadInterface;
use BridgePrestashop\Logger\BridgeLogger;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Throwable;

class WebhookSender
{
    /**
     * Encola el envío del webhook para ejecutarse DESPUÉS de que PrestaShop
     * haya enviado la respuesta HTTP al cliente (via register_shutdown_function).
     *
     * De esta forma el checkout, cambio de estado y registro de cliente
     * NO se bloquean esperando la respuesta del servidor externo.
     * Los logs con el código HTTP real se registran igualmente en el shutdown.
     *
     * @return array{success: bool, http_code: int, response: string, error: ?string, duration_ms: float}
     */
    public function send(PayloadInterface $payload, ?int $entityId = null): array
    {
        $url = trim(ModuleConfig::getWebhookUrl());
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $err = 'URL de Webhook inválida o no configurada.';
            BridgeLogger::warning($err, $entityId);
            return [
                'success'     => false,
                'http_code'   => 0,
                'response'    => '',
                'error'       => $err,
                'duration_ms' => 0.0,
            ];
        }

        $jsonBody   = $payload->toJson();
        $payloadArr = $payload->toArray();
        $eventName  = $payload->getEventName();
        $eventId    = $payload->getEventId();
        $timestamp  = (string) ($payloadArr['timestamp'] ?? date('c'));
        $secret     = ModuleConfig::getSecretToken();
        $timeout    = ModuleConfig::getTimeout();
        $verifySsl  = ModuleConfig::isVerifySsl();

        $headers = [
            'Content-Type'       => 'application/json',
            'Accept'             => 'application/json',
            'User-Agent'         => 'BridgePrestashop-Webhook/1.0',
            'X-Bridge-Event'     => $eventName,
            'X-Bridge-Delivery'  => $eventId,
            'X-Bridge-Timestamp' => $timestamp,
        ];

        if (!empty($secret)) {
            $headers['Authorization']      = 'Bearer ' . $secret;
            $headers['X-Bridge-Signature'] = 'sha256=' . hash_hmac('sha256', $jsonBody, $secret);
        }

        // Capturamos $this en una variable para que el closure pueda usarlo.
        $sender = $this;

        register_shutdown_function(
            static function () use ($sender, $url, $jsonBody, $headers, $timeout, $verifySsl, $eventName, $entityId): void {
                // En PHP-FPM: libera al cliente ANTES de ejecutar el HTTP request.
                // El usuario ya ve su confirmación de pedido mientras nosotros enviamos el webhook.
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }

                $sender->executeRequest($url, $jsonBody, $headers, $timeout, $verifySsl, $eventName, $entityId);
            }
        );

        // Retornamos de forma inmediata — el envío real ocurrirá en el shutdown del proceso.
        BridgeLogger::info(
            sprintf('Webhook [%s] encolado para envío post-respuesta a %s', $eventName, $url),
            $entityId
        );

        return [
            'success'     => true,
            'http_code'   => 0,
            'response'    => '',
            'error'       => null,
            'duration_ms' => 0.0,
        ];
    }

    /**
     * Envía un payload de prueba de forma SÍNCRONA.
     * Usado exclusivamente desde el backoffice para mostrar el resultado al administrador.
     * No usa register_shutdown_function — necesitamos la respuesta de forma inmediata.
     *
     * @return array{success: bool, http_code: int, response: string, error: ?string, duration_ms: float}
     */
    public function sendTest(): array
    {
        $payload = OrderPayload::createTestPayload();
        return $this->sendSync($payload, null);
    }

    /**
     * Envío síncrono completo: construye los headers y ejecuta el HTTP request
     * de forma bloqueante, devolviendo el resultado completo con código HTTP.
     *
     * Usado por sendTest(). Los hooks de PrestaShop deben usar send() en su lugar.
     *
     * @return array{success: bool, http_code: int, response: string, error: ?string, duration_ms: float}
     */
    public function sendSync(PayloadInterface $payload, ?int $entityId): array
    {
        $url = trim(ModuleConfig::getWebhookUrl());
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $err = 'URL de Webhook inválida o no configurada.';
            BridgeLogger::warning($err, $entityId);
            return [
                'success'     => false,
                'http_code'   => 0,
                'response'    => '',
                'error'       => $err,
                'duration_ms' => 0.0,
            ];
        }

        $jsonBody   = $payload->toJson();
        $payloadArr = $payload->toArray();
        $eventName  = $payload->getEventName();
        $eventId    = $payload->getEventId();
        $timestamp  = (string) ($payloadArr['timestamp'] ?? date('c'));
        $secret     = ModuleConfig::getSecretToken();
        $timeout    = ModuleConfig::getTimeout();
        $verifySsl  = ModuleConfig::isVerifySsl();

        $headers = [
            'Content-Type'       => 'application/json',
            'Accept'             => 'application/json',
            'User-Agent'         => 'BridgePrestashop-Webhook/1.0',
            'X-Bridge-Event'     => $eventName,
            'X-Bridge-Delivery'  => $eventId,
            'X-Bridge-Timestamp' => $timestamp,
        ];

        if (!empty($secret)) {
            $headers['Authorization']      = 'Bearer ' . $secret;
            $headers['X-Bridge-Signature'] = 'sha256=' . hash_hmac('sha256', $jsonBody, $secret);
        }

        $startTime = microtime(true);
        $result    = $this->executeRequest($url, $jsonBody, $headers, $timeout, $verifySsl, $eventName, $entityId);
        $result['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $result;
    }

    /**
     * Ejecuta el HTTP request real y registra el resultado en los logs de PrestaShop.
     *
     * Es llamado tanto desde el shutdown (async via send()) como directamente
     * desde sendSync(). Se declara public para que el closure del shutdown
     * pueda invocarlo correctamente, pero debe tratarse como @internal.
     *
     * @internal No invocar directamente desde fuera de WebhookSender.
     *
     * @return array{success: bool, http_code: int, response: string, error: ?string, duration_ms: float}
     */
    public function executeRequest(
        string $url,
        string $body,
        array  $headers,
        int    $timeout,
        bool   $verifySsl,
        string $eventName,
        ?int   $entityId
    ): array {
        $startTime = microtime(true);

        if (class_exists(GuzzleClient::class)) {
            $result = $this->sendWithGuzzle($url, $body, $headers, $timeout, $verifySsl);
        } else {
            $result = $this->sendWithCurl($url, $body, $headers, $timeout, $verifySsl);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $result['duration_ms'] = $durationMs;

        if ($result['success']) {
            BridgeLogger::info(
                sprintf(
                    'Webhook [%s] enviado con éxito a %s (HTTP %d en %sms)',
                    $eventName,
                    $url,
                    $result['http_code'],
                    $durationMs
                ),
                $entityId
            );
        } else {
            BridgeLogger::error(
                sprintf(
                    'Fallo al enviar Webhook [%s] a %s (HTTP %d, error: %s)',
                    $eventName,
                    $url,
                    $result['http_code'],
                    $result['error'] ?? 'desconocido'
                ),
                $entityId
            );
        }

        return $result;
    }

    /**
     * Envía el payload usando Guzzle (disponible en PrestaShop 9 / Symfony).
     */
    private function sendWithGuzzle(
        string $url,
        string $body,
        array  $headers,
        int    $timeout,
        bool   $verifySsl
    ): array {
        try {
            $client = new GuzzleClient([
                RequestOptions::TIMEOUT         => $timeout,
                RequestOptions::CONNECT_TIMEOUT => min(3, $timeout),
                RequestOptions::VERIFY          => $verifySsl,
                RequestOptions::HTTP_ERRORS     => false,
            ]);

            $response   = $client->post($url, [
                RequestOptions::HEADERS => $headers,
                RequestOptions::BODY    => $body,
            ]);

            $statusCode   = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $isSuccess    = ($statusCode >= 200 && $statusCode < 300);

            return [
                'success'  => $isSuccess,
                'http_code' => $statusCode,
                'response' => $responseBody,
                'error'    => $isSuccess ? null : sprintf('El servidor respondió con código HTTP %d', $statusCode),
            ];
        } catch (Throwable $e) {
            return [
                'success'   => false,
                'http_code' => 0,
                'response'  => '',
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Envía el payload usando cURL nativo (fallback cuando Guzzle no está disponible).
     */
    private function sendWithCurl(
        string $url,
        string $body,
        array  $headers,
        int    $timeout,
        bool   $verifySsl
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'success'   => false,
                'http_code' => 0,
                'response'  => '',
                'error'     => 'No se pudo inicializar cURL',
            ];
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success'   => false,
                'http_code' => $httpCode,
                'response'  => '',
                'error'     => $curlError ?: 'Error desconocido en cURL',
            ];
        }

        $isSuccess = ($httpCode >= 200 && $httpCode < 300);
        return [
            'success'   => $isSuccess,
            'http_code' => $httpCode,
            'response'  => (string) $response,
            'error'     => $isSuccess ? null : sprintf('El servidor respondió con código HTTP %d', $httpCode),
        ];
    }
}
