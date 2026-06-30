# LCTER WC Points Loyalty

## Objetivo

Desarrollar y mantener un plugin profesional de fidelización para WordPress y WooCommerce. Los clientes registrados acumulan puntos tras pagar sus pedidos y pueden canjearlos por productos configurados como regalos.

El plugin debe mantenerse seguro, trazable, idempotente y separado por capas.

## Fuentes De Verdad

Antes de implementar o modificar reglas revisar:

* `docs/architecture.md`
* `docs/business-rules.md`
* `docs/use-cases.md`
* `docs/database.md`
* `docs/domain-model.md`
* `docs/technical-decisions.md`
* `docs/testing.md`
* `docs/roadmap.md`
* `docs/open-questions.md`

No inventar reglas de negocio. Si una decisión no está definida, registrarla en `docs/open-questions.md` antes de ampliar comportamiento.

## Metadatos Y Compatibilidad

* Plugin: LCTER WC Points Loyalty
* Plugin URI: https://lincetic.es/
* Autor: Eddie Rapallo
* Versión actual: `0.1.0`
* WordPress: 6.5+
* PHP: 8.1+
* WooCommerce: 8.0+
* Namespace: `LCTER_WCPL`
* Text domain: `lcter-wc-points-loyalty`
* Prefijo lógico de tablas: `lcter_wcpl_`
* Licencia: GPL-2.0-or-later

La cabecera del plugin, `readme.txt`, `README.md` y las constantes públicas deben mantenerse coherentes.

## Arquitectura Implementada

* `includes/repositories/`: todo el SQL de negocio y las operaciones sobre tablas.
* `includes/services/`: casos de aplicación, reglas de saldo, rewards, bonus, cancelaciones y trazabilidad.
* `includes/adapters/`: traducción entre servicios y APIs/hooks de WooCommerce.
* `includes/admin/`: edición de rewards, trazabilidad, bonus y recuperación operativa.
* `includes/class-database.php`: esquema, versión y migraciones no destructivas.
* Fachadas `Points`, `Points_Service`, `Rewards` y `WooCommerce`: compatibilidad pública.

Dependencias permitidas:

1. Administración, frontend y adaptadores llaman a servicios.
2. Los servicios coordinan repositorios.
3. Los repositorios encapsulan SQL.
4. Los servicios de saldo no deben depender de objetos WooCommerce.

No añadir SQL en administración, servicios, adaptadores o plantillas.

## Reglas Críticas

* Los puntos se generan únicamente cuando `WC_Order::is_paid()` es verdadero.
* Cálculo: total con IVA menos portes e impuesto de portes; 1 céntimo = 1 punto.
* Los pedidos invitados no acumulan ni canjean en la fase actual.
* El checkout soportado es el checkout clásico.
* El pedido actual no financia su propio canje.
* Nunca permitir saldo negativo.
* Los regalos se añaden a coste cero y se identifican como REGALO.
* `lcter_wcpl_order_rewards` es la fuente principal de regalos canjeados.
* Los metadatos de pedido/item son trazabilidad auxiliar, no fuente principal del saldo.
* El bonus inicial es manual, de 10.000 puntos y solo para usuarios con rol `customer`.
* Cancelaciones y reembolsos totales revierten puntos `earned`; los reembolsos parciales siguen pendientes.

## Idempotencia Obligatoria

* Acumulación: `earned_order:{order_id}`.
* Canje: `redeemed_order:{order_id}`.
* Reward de pedido: `redeemed_order:{order_id}:reward:{reward_id}`.
* Bonus inicial: `initial_bonus:{customer_id}:10000`.
* Cancelación/reembolso total: `cancelled_order:{order_id}`.

La restricción única de base de datos es la garantía final. Los metadatos nunca deben sustituirla.

## Seguridad

* Combinar nonces y comprobaciones de capacidad en toda acción administrativa.
* Usar `manage_woocommerce` para bonus y reintentos operativos.
* Leer claves concretas de `$_POST`, `$_GET` o `$_REQUEST` usando `wp_unslash()` y validación de tipo.
* Sanitizar entradas y escapar toda salida en el contexto correcto.
* Usar `$wpdb->prepare()` para valores dinámicos.
* No confiar en costes, IDs o cantidades enviados por el frontend.

## Funcionalidades Implementadas

* Fases 1-6 del roadmap.
* Acumulación y consulta de saldo.
* Catálogo manual de rewards.
* Canje múltiple en checkout clásico.
* Trazabilidad por pedido y cliente.
* Bonus inicial administrativo.
* Reversión de puntos por cancelación o reembolso total.
* Diagnóstico y reintento manual de `processing_error` desde el pedido.
* Configuración inicial de PHPUnit, PHPStan y PHPCS/WPCS.

## Fuera De Alcance Actual

* Reembolsos parciales.
* Caducidad efectiva de puntos.
* Ajustes manuales de saldo.
* Checkout Blocks / Store API.
* REST, webhooks y sincronización real con Clientify.
* Emails de bonus o movimientos.
* WP-CLI, reintentos automáticos y operaciones por lotes.

## Validación

Ejecutar, según disponibilidad del entorno:

```bash
composer validate --no-check-publish
composer test
composer phpstan
composer phpcs
```

Además:

* `php -l` en todos los PHP modificados.
* `node --check` en todo JS modificado.
* `git diff --check`.
* Confirmar que no aparece SQL fuera de repositorios, esquema o desinstalación.

Si `vendor/` no está instalado, documentar qué herramientas no pudieron ejecutarse.

## Documentación

Actualizar con cada cambio:

* `docs/roadmap.md` para estado y alcance.
* `docs/testing.md` para casos automatizados y manuales.
* `docs/technical-decisions.md` para decisiones relevantes.
* `docs/open-questions.md` para dudas pendientes.
* `docs/database.md` solo cuando cambie el esquema o su semántica.
* `docs/business-rules.md` y `docs/use-cases.md` cuando cambien reglas funcionales aprobadas.
