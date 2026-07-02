# LCTER WC Points Loyalty - Proyecto

## Resumen

LCTER WC Points Loyalty es un plugin para WordPress y WooCommerce que implementa fidelización mediante puntos y regalos. Los clientes registrados acumulan puntos después del pago y pueden canjear su saldo por productos configurados como rewards durante el checkout clásico.

El sistema mantiene saldo, transacciones, catálogo y regalos canjeados en tablas propias. Las operaciones sensibles son atómicas e idempotentes.

## Identidad

* Plugin: LCTER WC Points Loyalty
* Plugin URI: https://lincetic.es/
* Autor: Eddie Rapallo
* Versión: `0.1.0`
* WordPress: 6.5+
* PHP: 8.1+
* WooCommerce: 8.0+
* Text domain: `lcter-wc-points-loyalty`
* Namespace: `LCTER_WCPL`
* Licencia: GPL-2.0-or-later

## Estado Funcional

Implementado:

* Acumulación tras pago: 1 céntimo = 1 punto, IVA incluido y portes excluidos.
* Consulta de saldo desde servicios, fachada y frontend de cliente.
* Catálogo de hasta 12 rewards activos, con coste manual, orden y fechas.
* Canje de uno o varios regalos y cantidades múltiples en checkout clásico.
* Regalos a coste cero con estados `reward_selected` y `reward_redeemed`.
* Transacciones con `balance_before`, `balance_after` e idempotencia.
* Trazabilidad de `order_rewards` por pedido y cliente.
* Visualización de regalos canjeados en administración.
* Bonus inicial manual y configurable, con 10.000 puntos por defecto, para usuarios con rol `customer`.
* Multiplicador configurable para sugerir el coste de rewards, con 2.000 por defecto.
* Ajustes manuales administrativos trazables y protegidos contra saldo negativo.
* Reversión total de puntos `earned` al cancelar o reembolsar completamente un pedido.
* Sección administrativa de incidencias y reintento seguro de errores recuperables.

No implementado:

* Checkout Blocks y Store API.
* Reembolsos parciales.
* Caducidad de puntos.
* Ajustes manuales de saldo.
* API REST, webhooks o sincronización real con Clientify.
* Emails, WP-CLI, reintentos automáticos o acciones por lotes.

## Reglas Principales

* Solo acumulan pedidos pagados de clientes registrados.
* Los pedidos invitados quedan excluidos en la fase actual.
* El mínimo para canjear es 60 EUR de subtotal con IVA y sin portes.
* El pedido actual no aumenta el saldo disponible para su propio canje.
* Nunca se permite saldo negativo.
* Un canje no se considera concedido hasta completar pago, descuento y trazabilidad.
* El coste en puntos siempre se obtiene de base de datos.
* Las cancelaciones y reembolsos totales no descuentan parcialmente: si falta saldo quedan en `processing_error`.

## Idempotencia

* `earned_order:{order_id}`
* `redeemed_order:{order_id}`
* `redeemed_order:{order_id}:reward:{reward_id}`
* `initial_bonus:{customer_id}`
* `cancelled_order:{order_id}`

Las claves únicas de `lcter_wcpl_transactions` y `lcter_wcpl_order_rewards` son la garantía principal. Los metadatos WooCommerce son auxiliares.

## Arquitectura

```text
Administración / Frontend / Adaptadores WooCommerce
                         |
                    Servicios
                         |
                   Repositorios
                         |
                Tablas del plugin
```

Directorios principales:

* `includes/repositories/`: persistencia y transacciones SQL.
* `includes/services/`: reglas y casos de aplicación.
* `includes/adapters/`: hooks y objetos WooCommerce.
* `includes/admin/`: UI y acciones administrativas.
* `tests/Unit/`: pruebas unitarias de servicios de saldo, bonus y cancelaciones.

## Base De Datos

Tablas con prefijo dinámico de WordPress:

* `lcter_wcpl_customer_points`
* `lcter_wcpl_transactions`
* `lcter_wcpl_rewards`
* `lcter_wcpl_order_rewards`

Versión actual del esquema: `1.2.0`, almacenada en `lcter_wcpl_schema_version`. Las migraciones son no destructivas y usan `dbDelta()`.

No se usan `product_points` ni `points_per_currency`.

## Administración

* Dashboard de puntos y bonus inicial.
* Configuración de rewards en la edición del producto.
* Trazabilidad de rewards dentro del pedido.
* Advertencia para regalos pendientes de pago.
* Diagnóstico de `processing_error` y canjes `rejected`.
* Reintento con `manage_woocommerce`, nonce y comprobación de estado.

## Clientify

La integración externa no está implementada. El plugin solo prepara:

* `lcter_wcpl_order_rewards` como fuente principal.
* Snapshots de producto, SKU, cantidades y puntos.
* Payloads internos neutrales por pedido y cliente.
* Metadatos auxiliares de pedido y order item.

El mecanismo externo sigue pendiente en `docs/open-questions.md`.

## Calidad

Configuración incluida:

* PHPUnit 10.
* PHPStan nivel 5 con extensión WordPress.
* PHPCS con WordPress, WordPress-Extra y WordPress-Docs.

Comandos desde la raíz del plugin:

```bash
composer install
composer test
composer phpstan
composer phpcs
composer qa
```

Las pruebas unitarias cubren saldo, idempotencia, bonus inicial y cancelaciones. Siguen pendientes las pruebas de integración reales con WooCommerce, concurrencia y HPOS.

## Documentación

Las fuentes funcionales y técnicas están en `docs/`. Antes de implementar revisar especialmente `architecture.md`, `business-rules.md`, `use-cases.md`, `database.md`, `technical-decisions.md`, `testing.md`, `roadmap.md` y `open-questions.md`.
