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
     * Envía un PayloadInterface (OrderPayload, CustomerPayload, etc.) al webhook configurado.
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
                'success' => false,
                'http_code' => 0,
                'response' => '',
                'error' => $err,
                'duration_ms' => 0.0,
            ];
        }

        $jsonBody = $payload->toJson();
        $payloadArray = $payload->toArray();
        $eventName = $payload->getEventName();
        $eventId = $payload->getEventId();
        $timestamp = (string) ($payloadArray['timestamp'] ?? date('c'));
        $secret = ModuleConfig::getSecretToken();
        $timeout = ModuleConfig::getTimeout();
        $verifySsl = ModuleConfig::isVerifySsl();

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'BridgePrestashop-Webhook/1.0',
            'X-Bridge-Event' => $eventName,
            'X-Bridge-Delivery' => $eventId,
            'X-Bridge-Timestamp' => $timestamp,
        ];

        if (!empty($secret)) {
            $headers['Authorization'] = 'Bearer ' . $secret;
            $signature = hash_hmac('sha256', $jsonBody, $secret);
            $headers['X-Bridge-Signature'] = 'sha256=' . $signature;
        }

        $startTime = microtime(true);

        // Si Guzzle está disponible (estándar en PrestaShop 9 / Symfony)
        if (class_exists(GuzzleClient::class)) {
            $result = $this->sendWithGuzzle($url, $jsonBody, $headers, $timeout, $verifySsl);
        } else {
            $result = $this->sendWithCurl($url, $jsonBody, $headers, $timeout, $verifySsl);
        }

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $result['duration_ms'] = $durationMs;

        if ($result['success']) {
            BridgeLogger::info(
                sprintf('Webhook [%s] enviado con éxito a %s (HTTP %d en %sms)', $eventName, $url, $result['http_code'], $durationMs),
                $entityId
            );
        } else {
            BridgeLogger::error(
                sprintf('Fallo al enviar Webhook [%s] a %s (HTTP %d, error: %s)', $eventName, $url, $result['http_code'], $result['error'] ?? 'desconocido'),
                $entityId
            );
        }

        return $result;
    }

    /**
     * Envía un payload de prueba al webhook configurado.
     */
    public function sendTest(): array
    {
        $payload = OrderPayload::createTestPayload();
        return $this->send($payload, null);
    }

    private function sendWithGuzzle(
        string $url,
        string $body,
        array $headers,
        int $timeout,
        bool $verifySsl
    ): array {
        try {
            $client = new GuzzleClient([
                RequestOptions::TIMEOUT => $timeout,
                RequestOptions::CONNECT_TIMEOUT => min(3, $timeout),
                RequestOptions::VERIFY => $verifySsl,
                RequestOptions::HTTP_ERRORS => false,
            ]);

            $response = $client->post($url, [
                RequestOptions::HEADERS => $headers,
                RequestOptions::BODY => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = (string) $response->getBody();
            $isSuccess = ($statusCode >= 200 && $statusCode < 300);

            return [
                'success' => $isSuccess,
                'http_code' => $statusCode,
                'response' => $responseBody,
                'error' => $isSuccess ? null : sprintf('El servidor respondió con código HTTP %d', $statusCode),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'http_code' => 0,
                'response' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function sendWithCurl(
        string $url,
        string $body,
        array $headers,
        int $timeout,
        bool $verifySsl
    ): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return [
                'success' => false,
                'http_code' => 0,
                'response' => '',
                'error' => 'No se pudo inicializar cURL',
            ];
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'success' => false,
                'http_code' => $httpCode,
                'response' => '',
                'error' => $curlError ?: 'Error desconocido en cURL',
            ];
        }

        $isSuccess = ($httpCode >= 200 && $httpCode < 300);
        return [
            'success' => $isSuccess,
            'http_code' => $httpCode,
            'response' => (string) $response,
            'error' => $isSuccess ? null : sprintf('El servidor respondió con código HTTP %d', $httpCode),
        ];
    }
}
