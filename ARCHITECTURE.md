# Arquitectura y Documentación Técnica de Bridge PrestaShop

Documento de referencia para desarrolladores con la descripción de la arquitectura, flujo de ejecución y el catálogo completo de funciones implementadas en el módulo `bridgeprestashop`.

---

## 🏗️ 1. Arquitectura General y Flujo de Datos

El módulo sigue una arquitectura por capas desacoplada que garantiza que el proceso de checkout del comprador nunca se interrumpa ni se vuelva lento si el servicio de terceros experimenta demoras o fallos.

```
 [ Comprador finaliza pedido ]
               │
               ▼
   Hook: actionValidateOrder
               │
               ▼
┌────────────────────────────────────────────────────────┐
│ BridgePrestashop::hookActionValidateOrder()            │
└──────────────────────┬─────────────────────────────────┘
                       │
                       ▼
┌────────────────────────────────────────────────────────┐
│ OrderEventHandler::handleOrderCreated()                │
│  - Valida si el módulo está activo                     │
│  - Try/Catch global para proteger el checkout          │
└──────────────────────┬─────────────────────────────────┘
                       │
                       ▼
┌────────────────────────────────────────────────────────┐
│ OrderPayload::fromOrder()                              │
│  - Construye el JSON estandarizado con DTO             │
│  - Genera UUIDv4 único de entrega                      │
└──────────────────────┬─────────────────────────────────┘
                       │
                       ▼
┌────────────────────────────────────────────────────────┐
│ WebhookSender::send()                                  │
│  - Genera cabeceras (X-Bridge-Delivery, Timestamp)     │
│  - Calcula firma digital HMAC-SHA256                   │
│  - Envía POST vía GuzzleHttp (o fallback a cURL)       │
│  - Controla Timeout (por defecto 5s)                   │
└──────────────────────┬─────────────────────────────────┘
                       │
                       ▼
┌────────────────────────────────────────────────────────┐
│ BridgeLogger::log()                                    │
│  - Registra código HTTP y tiempo de respuesta en       │
│    PrestaShopLogger (Parámetros Avanzados > Registros) │
└────────────────────────────────────────────────────────┘
```

---

## 📁 2. Estructura de Archivos

```text
bridgeprestashop/
├── bridgeprestashop.php          # Clase principal del módulo y registro de hooks
├── composer.json                 # Definición de dependencias y autoloading PSR-4
├── index.php                     # Archivo de protección de navegación directa
├── README.md                     # Guía de instalación, payloads y ejemplos receptores
├── ARCHITECTURE.md               # Esta documentación técnica
└── src/
    ├── Config/
    │   └── ModuleConfig.php      # Constantes y gestión de configuración persistente
    ├── DTO/
    │   ├── PayloadInterface.php  # Contrato base para payloads de webhook
    │   ├── OrderPayload.php      # DTO de pedidos (order.created, order.status_updated)
    │   └── CustomerPayload.php   # DTO de clientes (customer.created)
    ├── Handler/
    │   ├── OrderEventHandler.php # Procesador de hooks y eventos de pedido
    │   └── CustomerEventHandler.php # Procesador de registro de clientes
    ├── HttpClient/
    │   └── WebhookSender.php     # Cliente de transporte HTTP (Guzzle/cURL) y firmas HMAC
    └── Logger/
        └── BridgeLogger.php      # Canal de logging integrado en PrestaShop
```

---

## 🛠️ 3. Catálogo Detallado de Clases y Métodos

### 3.1. `BridgePrestashop` (`bridgeprestashop.php`)

Clase central que hereda de `Module` e interactúa con el ecosistema de PrestaShop.

| Método | Tipo | Descripción |
| :--- | :--- | :--- |
| `__construct()` | Público | Define el identificador técnico (`bridgeprestashop`), versión (`1.0.0`), pestaña de administración y compatibilidad mínima con PrestaShop 9.0.0+. |
| `install()` | Público | Registra los hooks requeridos (`actionValidateOrder`, `actionOrderStatusPostUpdate` y `actionCustomerAccountAdd`) e inicializa las opciones por defecto en la base de datos. |
| `uninstall()` | Público | Limpia las opciones del módulo en la tabla `ps_configuration` y completa la desinstalación. |
| `getContent()` | Público | Orquesta la pantalla del Backoffice: captura envíos de formulario, ejecuta pruebas manuales y renderiza la vista. |
| `hookActionValidateOrder(array $params)` | Público | Escucha el hook disparado tras crear y validar un pedido en la tienda y lo delega a `OrderEventHandler`. |
| `hookActionOrderStatusPostUpdate(array $params)` | Público | Escucha cambios en el estado de un pedido existente y lo delega al handler. |
| `hookActionCustomerAccountAdd(array $params)` | Público | Escucha la creación/registro de un nuevo cliente y lo delega a `CustomerEventHandler`. |
| `processConfiguration()` | Privado | Sanitiza y persiste los valores enviados en el formulario del Backoffice (URL, token, timeout, switches). |
| `processTestWebhook()` | Privado | Envía un payload de prueba al endpoint configurado y devuelve un banner de confirmación o error con el código HTTP y milisegundos transcurridos. |
| `renderConfigurationForm()` | Privado | Construye el formulario visual usando el `HelperForm` oficial de PrestaShop. |

---

### 3.2. `BridgePrestashop\Config\ModuleConfig` (`src/Config/ModuleConfig.php`)

Maneja de forma tipada y centralizada las claves en la tabla `ps_configuration`.

| Método | Tipo | Descripción |
| :--- | :--- | :--- |
| `installDefaults()` | Estático | Guarda los valores iniciales predeterminados (timeout = 5s, SSL = true, token aleatorio generado). |
| `uninstall()` | Estático | Elimina todas las variables de configuración asociadas al módulo. |
| `isEnabled()` | Estático | Devuelve `true` si el switch del módulo está encendido. |
| `getWebhookUrl()` | Estático | Retorna la URL del webhook configurada. |
| `getSecretToken()` | Estático | Retorna la clave secreta compartida utilizada para la firma HMAC y el Bearer token. |
| `getTimeout()` | Estático | Retorna el tiempo máximo de espera en segundos para la conexión HTTP. |
| `isVerifySsl()` | Estático | Indica si se debe validar el certificado TLS/SSL del servidor de destino. |
| `shouldNotifyOrders()` | Estático | Indica si se deben notificar los pedidos/órdenes (`order.created`). Si está desactivado, los eventos de órdenes se descartan inmediatamente. |
| `shouldNotifyStatusChange()` | Estático | Indica si se deben notificar cambios de estado además de la creación. |
| `shouldNotifyCustomerRegistration()` | Estático | Indica si se deben notificar los registros de nuevos clientes (`customer.created`). |
| `shouldLogRequests()` | Estático | Indica si se deben almacenar registros de auditoría en PrestaShop. |
| `generateRandomToken(int $bytes = 32)` | Estático | Genera un token hexadecimal criptográficamente seguro usando `random_bytes()`. |

---

### 3.3. `BridgePrestashop\DTO\PayloadInterface` (`src/DTO/PayloadInterface.php`)

Contrato común para cualquier payload transmisible vía Webhook.

| Método | Tipo | Descripción |
| :--- | :--- | :--- |
| `toArray()` | Público | Retorna el payload como array asociativo. |
| `toJson()` | Público | Serializa el payload a formato JSON con opciones unescaped unicode y slashes. |
| `getEventName()` | Público | Retorna el nombre técnico del evento (`order.created`, `customer.created`, etc.). |
| `getEventId()` | Público | Retorna el UUIDv4 único del evento para trazabilidad e idempotencia. |

---

### 3.4. `BridgePrestashop\DTO\OrderPayload` (`src/DTO/OrderPayload.php`)

Encapsula y normaliza los datos del pedido en un formato JSON listo para consumo externo. Implementa `PayloadInterface`.

| Método | Tipo | Descripción |
| :--- | :--- | :--- |
| `__construct(array $data)` | Público | Inicializa el DTO con la estructura de datos procesada. |
| `fromOrder(Order $order, string $eventName)` | Estático | Extrae la información completa del pedido: cliente enriquecido con tipo y grupos, totales, desglose de impuestos, direcciones normalizadas, método de pago, transportista y detalle de productos. |
| `createTestPayload()` | Estático | Genera un payload representativo simulado para verificar la conexión desde el Backoffice. |
| `toArray()` | Público | Devuelve la representación en array asociativo de PHP. |
| `toJson()` | Público | Serializa los datos a JSON preservando acentos y caracteres especiales (`JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES`). |
| `getEventName()` | Público | Retorna el nombre del evento. |
| `getEventId()` | Público | Retorna el UUIDv4 del evento. |
| `formatAddress(Address $address, int $idLang)` | Privado | Extrae y estandariza los campos de una dirección (calle, ciudad, estado, país, teléfonos, etc.). |
| `generateUuidV4()` | Privado | Genera un identificador único global (UUID versión 4) para asegurar idempotencia en el sistema receptor. |

---

### 3.5. `BridgePrestashop\DTO\CustomerPayload` (`src/DTO/CustomerPayload.php`)

Encapsula y normaliza los datos de un cliente registrado. Implementa `PayloadInterface`.

| Método | Tipo | Descripción |
| :--- | :--- | :--- |
| `__construct(array $data)` | Público | Inicializa el DTO con la estructura de datos procesada. |
| `fromCustomer(Customer $customer, string $eventName)` | Estático | Extrae los datos del cliente: datos personales, de contacto, de empresa/B2B (siret, ape, website), estado de cuenta, grupo predeterminado y grupos asociados. |
| `toArray()` | Público | Devuelve la representación en array asociativo de PHP. |
| `toJson()` | Público | Serializa los datos a JSON. |
| `getEventName()` | Público | Retorna el nombre del evento (`customer.created`). |
| `getEventId()` | Público | Retorna el UUIDv4 del evento. |

---

### 3.6. `BridgePrestashop\HttpClient\WebhookSender` (`src/HttpClient/WebhookSender.php`)

Encargado del transporte HTTP, cálculo de seguridad criptográfica y tolerancia a fallos.

| Método | Tipo | Descripción |
| :--- | :--- | :--- |
| `send(PayloadInterface $payload, ?int $entityId)` | Público | Prepara cabeceras HTTP (`X-Bridge-Delivery`, `X-Bridge-Signature`, etc.), envía la petición POST, calcula la duración en ms y registra el log. |
| `sendTest()` | Público | Atajo para emitir el payload de prueba y validar conectividad. |
| `sendWithGuzzle(...)` | Privado | Utiliza el cliente estándar `GuzzleHttp\Client` integrado en Symfony / PrestaShop 9 con gestión de timeouts y excepciones. |
| `sendWithCurl(...)` | Privado | Fallback nativo utilizando la extensión `cURL` de PHP en caso de entornos donde Guzzle no esté presente. |

---

### 3.7. `BridgePrestashop\Handler\OrderEventHandler` (`src/Handler/OrderEventHandler.php`)

Capa intermedia de control para procesar los eventos de negocio de pedidos.

| Método | Tipo | Descripción |
| :--- | :--- | :--- |
| `__construct(?WebhookSender $sender = null)` | Público | Permite inyección de dependencias para el cliente HTTP. |
| `handleOrderCreated(array $params)` | Público | Valida la existencia del objeto `Order`, construye el payload y solicita el envío dentro de un bloque protector contra excepciones. |
| `handleOrderStatusUpdated(array $params)` | Público | Valida si está activa la notificación de cambio de estado y dispara el evento `order.status_updated`. |

---

### 3.8. `BridgePrestashop\Handler\CustomerEventHandler` (`src/Handler/CustomerEventHandler.php`)

Capa intermedia de control para procesar los eventos de registro de usuarios.

| Método | Tipo | Descripción |
| :--- | :--- | :--- |
| `__construct(?WebhookSender $sender = null)` | Público | Permite inyección de dependencias para el cliente HTTP. |
| `handleCustomerCreated(array $params)` | Público | Valida la existencia del objeto `Customer`, construye el `CustomerPayload` y solicita el envío dentro de un bloque protector contra excepciones. |

---

### 3.9. `BridgePrestashop\Logger\BridgeLogger` (`src/Logger/BridgeLogger.php`)

Auditoría y trazabilidad en el Backoffice.

| Método | Tipo | Descripción |
| :--- | :--- | :--- |
| `log(string $message, int $severity, ?int $orderId)` | Estático | Guarda un registro en la tabla `ps_log` con prefijo `[BridgePrestashop]`. |
| `info(string $message, ?int $orderId)` | Estático | Registra envíos exitosos con tiempo de respuesta y código HTTP 2xx. |
| `warning(string $message, ?int $orderId)` | Estático | Registra alertas menores o configuración incompleta. |
| `error(string $message, ?int $orderId)` | Estático | Registra fallos de conexión, respuestas HTTP 4xx/5xx o timeouts. |

---

## 🛡️ 4. Principios y Buenas Prácticas Aplicadas

1. **No-Bloqueo de Checkout:** Ningún fallo externo (sitio caído, error 500, timeout) interrumpe la confirmación de compra del cliente en la tienda gracias a bloques `try/catch` globales y timeouts estrictos.
2. **Idempotencia:** Cada entrega lleva un `event_id` único en cabecera y en el cuerpo para que el receptor pueda descartar duplicados ante reintentos.
3. **Autenticación Doble:** Soporte simultáneo para `Authorization: Bearer <token>` y firma de integridad `X-Bridge-Signature: sha256=<hash>`.
4. **Compatibilidad Nativa PS9:** Compatible con PHP 8.1+, PHP 8.2 y PHP 8.3 con tipado estricto (`declare(strict_types=1);`) y namespaces PSR-4.
