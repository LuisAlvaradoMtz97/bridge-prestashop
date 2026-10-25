# Bridge PrestaShop (`bridgeprestashop`)

Módulo para **PrestaShop 9+** (compatible con PHP 8.1, 8.2 y 8.3) diseñado para funcionar como un **Bridge / Webhook** que notifica a plataformas y sitios web de terceros en tiempo real cada vez que se genera o actualiza un pedido en la tienda.

---

## Características

* **Control granular de notificaciones:**
  * Switch independiente para habilitar o ignorar notificaciones de pedidos (`order.created`).
  * Switch independiente para habilitar o ignorar notificaciones de cambios de estado (`order.status_updated`).
  * Switch independiente para habilitar o ignorar notificaciones de nuevos clientes (`customer.created`).
* **Disparo automático en tiempo real:**
  * `order.created`: Se ejecuta inmediatamente cuando el cliente confirma el pedido (`actionValidateOrder`), enriquecido con la clasificación completa del cliente (grupo por defecto, grupos asociados, tipo de cuenta particular o empresa B2B).
  * `order.status_updated`: Opcional, se ejecuta cuando el estado del pedido cambia en el Backoffice o por pasarelas de pago (`actionOrderStatusPostUpdate`).
  * `customer.created`: Opcional, se ejecuta inmediatamente cuando un nuevo cliente se registra en la tienda (`actionCustomerAccountAdd`).
* **Seguridad de nivel empresarial:**
  * Autenticación mediante `Authorization: Bearer <token>`.
  * Firma digital criptográfica `X-Bridge-Signature: sha256=<hash_hmac>` para verificar la integridad del payload en el receptor.
* **Resiliencia y no bloqueo:**
  * Timeout configurable (por defecto 5 segundos) para no degradar la velocidad del checkout ni afectar la experiencia del comprador.
  * Soporta **GuzzleHttp** (nativo de PrestaShop 9) con fallback transparente a **cURL**.
* **Panel de Configuración:**
  * Activar / Desactivar el bridge.
  * URL del Webhook.
  * Clave Secreta / Token (con generador criptográfico aleatorio inicial).
  * Tiempo de espera (Timeout).
  * Verificación de certificados SSL.
  * Botón **"Probar Webhook ahora"** para verificar conectividad con un clic desde el Backoffice sin tener que simular una compra real.
* **Logs integrados:**
  * Registro de envíos, códigos de estado HTTP y tiempos de respuesta visible en *Parámetros Avanzados > Registros / Logs*.

---

## Requisitos

* **PrestaShop:** `9.0.0` o superior.
* **PHP:** `>= 8.1` (totalmente compatible con PHP 8.2 y 8.3).
* Extensiones PHP: `curl`, `json`, `openssl`.

---

## Instalación

### Opción 1: Archivo ZIP (Recomendado para Backoffice)
1. Comprime la carpeta `bridgeprestashop/` en un archivo `bridgeprestashop.zip`.
2. En el Backoffice de PrestaShop, ve a **Módulos > Module Manager**.
3. Haz clic en **Subir un módulo** y selecciona el archivo `.zip`.
4. Haz clic en **Configurar**.

### Opción 2: Clonado directo en el servidor
1. Copia o clona esta carpeta dentro del directorio de módulos de PrestaShop:
   ```bash
   cd /ruta/a/prestashop/modules/
   git clone <repo-url> bridgeprestashop
   ```
2. Ve al Backoffice de PrestaShop > **Módulos > Module Manager** y busca **Bridge PrestaShop**.
3. Haz clic en **Instalar** y luego en **Configurar**.

---

## Configuración

Una vez instalado, ingresa a la configuración del módulo:

1. **Habilitar Bridge:** Activa o desactiva de forma global el envío de webhooks.
2. **URL del Webhook de destino:** Endpoint donde tu sistema externo escucha las peticiones (ej. `https://api.tu-sitio.com/webhooks/prestashop`).
3. **Token / Clave Secreta:** Clave compartida para verificar autenticidad (Bearer y firma HMAC-SHA256).
4. **Tiempo de espera (Timeout):** Tiempo máximo en segundos (recomendado: 3 a 5).
5. **Verificar certificado SSL:** Verificación TLS/SSL (mantener encendido en producción).
6. **Notificar pedidos / órdenes:** Si está encendido, se envían eventos de pedidos (`order.created`). Si está apagado, se ignoran por completo.
7. **Notificar también cambios de estado:** Si está encendido (y pedidos activo), se envían actualizaciones de estado (`order.status_updated`).
8. **Notificar registro de nuevos clientes:** Si está encendido, se envían notificaciones de creación de cuentas (`customer.created`). Si está apagado, se ignoran por completo.
9. **Registrar logs en PrestaShop:** Auditoría de respuestas y errores en *Parámetros Avanzados > Registros*.
10. Haz clic en **"Probar Webhook ahora"** para verificar conectividad con un clic.

---

## Estructura del Webhook (HTTP POST)

### Cabeceras HTTP enviadas

| Cabecera | Descripción | Ejemplo |
| :--- | :--- | :--- |
| `Content-Type` | Formato de la petición | `application/json` |
| `User-Agent` | Identificador del cliente | `BridgePrestashop-Webhook/1.0` |
| `X-Bridge-Event` | Tipo de evento | `order.created`, `order.status_updated` o `customer.created` |
| `X-Bridge-Delivery` | UUID único de entrega (idempotencia) | `c56a4180-65aa-42ec-a945-5fd21dec0538` |
| `X-Bridge-Timestamp` | Fecha y hora en ISO 8601 | `2026-10-18T23:30:00-06:00` |
| `Authorization` | Token Bearer | `Bearer <tu_token_secreto>` |
| `X-Bridge-Signature` | Firma HMAC SHA-256 del cuerpo JSON | `sha256=d3a8...` |

---

### Ejemplo de Payload JSON (`order.created`)

```json
{
  "event": "order.created",
  "event_id": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
  "timestamp": "2026-10-18T23:30:00-06:00",
  "shop": {
    "id": 1,
    "name": "Mi Tienda",
    "url": "https://mitienda.com/"
  },
  "order": {
    "id": 105,
    "reference": "XJHDFPKO",
    "status": {
      "id": 2,
      "name": "Pago aceptado"
    },
    "currency": {
      "iso_code": "USD",
      "sign": "$"
    },
    "totals": {
      "total_paid_tax_incl": 125.50,
      "total_paid_tax_excl": 108.19,
      "total_products_tax_incl": 115.50,
      "total_products_tax_excl": 99.57,
      "total_shipping_tax_incl": 10.00,
      "total_shipping_tax_excl": 8.62,
      "total_discounts_tax_incl": 0.0,
      "total_discounts_tax_excl": 0.0,
      "total_wrapping_tax_incl": 0.0,
      "total_wrapping_tax_excl": 0.0
    },
    "payment_method": "Tarjeta de crédito (Stripe)",
    "module": "stripe_official",
    "date_add": "2026-10-18 23:29:55",
    "date_upd": "2026-10-18 23:30:00"
  },
  "customer": {
    "id": 42,
    "firstname": "Carlos",
    "lastname": "Gómez",
    "email": "carlos.gomez@example.com",
    "company": "Soluciones Digitales",
    "siret": "12345678900012",
    "ape": "6201Z",
    "website": "https://solucionesdigitales.com",
    "is_guest": false,
    "default_group": {
      "id": 3,
      "name": "Cliente"
    },
    "groups": [3]
  },
  "shipping_address": {
    "alias": "Mi Casa",
    "firstname": "Carlos",
    "lastname": "Gómez",
    "company": "Soluciones Digitales",
    "address1": "Av. Insurgentes Sur 1234",
    "address2": "Piso 5, Depto 502",
    "postcode": "03100",
    "city": "Ciudad de México",
    "state": "CDMX",
    "country": "México",
    "country_iso": "MX",
    "phone": "5512345678",
    "phone_mobile": "5598765432",
    "vat_number": "",
    "dni": ""
  },
  "invoice_address": {
    "alias": "Facturación",
    "firstname": "Carlos",
    "lastname": "Gómez",
    "company": "Soluciones Digitales",
    "address1": "Av. Insurgentes Sur 1234",
    "address2": "Piso 5, Depto 502",
    "postcode": "03100",
    "city": "Ciudad de México",
    "state": "CDMX",
    "country": "México",
    "country_iso": "MX",
    "phone": "5512345678",
    "phone_mobile": "",
    "vat_number": "SDI123456ABC",
    "dni": ""
  },
  "carrier": {
    "id": 3,
    "name": "DHL Express"
  },
  "items": [
    {
      "product_id": 12,
      "product_attribute_id": 45,
      "reference": "PROD-12-RED",
      "name": "Camiseta de Algodón - Talla M, Color Rojo",
      "quantity": 2,
      "unit_price_tax_incl": 25.00,
      "unit_price_tax_excl": 21.55,
      "total_price_tax_incl": 50.00,
      "total_price_tax_excl": 43.10
    }
  ]
}
```

---

### Ejemplo de Payload JSON (`order.status_updated`)

```json
{
  "event": "order.status_updated",
  "event_id": "1c3d9b2a-88f0-4c71-a320-b9e012de4c1f",
  "timestamp": "2026-10-18T23:45:00-06:00",
  "shop": {
    "id": 1,
    "name": "Mi Tienda",
    "url": "https://mitienda.com/"
  },
  "order": {
    "id": 105,
    "reference": "XJHDFPKO",
    "status": {
      "id": 5,
      "name": "Entregado"
    },
    "currency": {
      "iso_code": "USD",
      "sign": "$"
    },
    "totals": {
      "total_paid_tax_incl": 125.50,
      "total_paid_tax_excl": 108.19,
      "total_products_tax_incl": 115.50,
      "total_products_tax_excl": 99.57,
      "total_shipping_tax_incl": 10.00,
      "total_shipping_tax_excl": 8.62,
      "total_discounts_tax_incl": 0.0,
      "total_discounts_tax_excl": 0.0,
      "total_wrapping_tax_incl": 0.0,
      "total_wrapping_tax_excl": 0.0
    },
    "payment_method": "Tarjeta de crédito (Stripe)",
    "module": "stripe_official",
    "date_add": "2026-10-18 23:29:55",
    "date_upd": "2026-10-18 23:45:00"
  },
  "customer": {
    "id": 42,
    "firstname": "Carlos",
    "lastname": "Gómez",
    "email": "carlos.gomez@example.com",
    "company": "Soluciones Digitales",
    "siret": "12345678900012",
    "ape": "6201Z",
    "website": "https://solucionesdigitales.com",
    "is_guest": false,
    "default_group": {
      "id": 3,
      "name": "Cliente"
    },
    "groups": [3]
  },
  "shipping_address": {
    "alias": "Mi Casa",
    "firstname": "Carlos",
    "lastname": "Gómez",
    "company": "Soluciones Digitales",
    "address1": "Av. Insurgentes Sur 1234",
    "address2": "Piso 5, Depto 502",
    "postcode": "03100",
    "city": "Ciudad de México",
    "state": "CDMX",
    "country": "México",
    "country_iso": "MX",
    "phone": "5512345678",
    "phone_mobile": "5598765432",
    "vat_number": "",
    "dni": ""
  },
  "invoice_address": {
    "alias": "Facturación",
    "firstname": "Carlos",
    "lastname": "Gómez",
    "company": "Soluciones Digitales",
    "address1": "Av. Insurgentes Sur 1234",
    "address2": "Piso 5, Depto 502",
    "postcode": "03100",
    "city": "Ciudad de México",
    "state": "CDMX",
    "country": "México",
    "country_iso": "MX",
    "phone": "5512345678",
    "phone_mobile": "",
    "vat_number": "SDI123456ABC",
    "dni": ""
  },
  "carrier": {
    "id": 3,
    "name": "DHL Express"
  },
  "items": [
    {
      "product_id": 12,
      "product_attribute_id": 45,
      "reference": "PROD-12-RED",
      "name": "Camiseta de Algodón - Talla M, Color Rojo",
      "quantity": 2,
      "unit_price_tax_incl": 25.00,
      "unit_price_tax_excl": 21.55,
      "total_price_tax_incl": 50.00,
      "total_price_tax_excl": 43.10
    }
  ]
}
```

---

### Ejemplo de Payload JSON (`customer.created`)

```json
{
  "event": "customer.created",
  "event_id": "8f3b2049-74d1-4be6-a19e-e8b9487c69a2",
  "timestamp": "2026-10-18T23:35:00-06:00",
  "shop": {
    "id": 1,
    "name": "Mi Tienda",
    "url": "https://mitienda.com/"
  },
  "customer": {
    "id": 43,
    "id_gender": 1,
    "firstname": "Laura",
    "lastname": "Martínez",
    "email": "laura.martinez@example.com",
    "company": "Comercializadora Norte SA",
    "siret": "98765432100045",
    "ape": "4719B",
    "website": "https://comercializadoranorte.com",
    "birthday": "1990-05-15",
    "newsletter": true,
    "optin": false,
    "active": true,
    "is_guest": false,
    "default_group": {
      "id": 3,
      "name": "Cliente"
    },
    "groups": [3],
    "date_add": "2026-10-18 23:35:00",
    "date_upd": "2026-10-18 23:35:00"
  }
}
```

---

## Cómo validar la firma en el receptor

### Node.js / Express
```javascript
const crypto = require('crypto');

function verifySignature(req, secret) {
  const signature = req.headers['x-bridge-signature'];
  if (!signature) return false;

  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(req.rawBody)
    .digest('hex');

  return crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected));
}
```

### PHP (Sitio receptor)
```php
$secret = 'tu_token_secreto';
$signatureHeader = $_SERVER['HTTP_X_BRIDGE_SIGNATURE'] ?? '';
$rawBody = file_get_contents('php://input');

$expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

if (!hash_equals($expectedSignature, $signatureHeader)) {
    http_response_code(401);
    exit('Firma HMAC no válida.');
}

$data = json_decode($rawBody, true);
// Procesar pedido...
http_response_code(200);
```

### Python / FastAPI
```python
import hmac
import hashlib
from fastapi import Request, HTTPException

async def verify_signature(request: Request, secret: str):
    signature = request.headers.get("X-Bridge-Signature")
    if not signature:
        raise HTTPException(status_code=401, detail="Firma faltante")

    body = await request.body()
    expected = "sha256=" + hmac.new(secret.encode(), body, hashlib.sha256).hexdigest()

    if not hmac.compare_digest(signature, expected):
        raise HTTPException(status_code=401, detail="Firma inválida")
```
