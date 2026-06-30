# Decisiones Técnicas

## TD-000 - Metadatos Y Requisitos Base

Decisión: la documentación del proyecto toma como base pública los metadatos actuales del plugin y los ficheros `readme.txt` / `README.md`.

Valores documentados:

* Nombre: LCTER WC Points Loyalty.
* Plugin URI: https://lincetic.es/.
* Autor: Eddie Rapallo.
* Versión: 0.1.0.
* PHP: 8.1+.
* WordPress: 6.5+.
* WooCommerce: 8.0+.
* WooCommerce probado hasta: 10.0.
* Licencia: GPL-2.0-or-later. La cabecera PHP usa la notación equivalente `GPL-2.0+`.

Nota: la cabecera PHP, la constante `LCTER_WCPL_VERSION` y `readme.txt` coinciden en la versión `0.1.0`.

## TD-001 - Usar Tablas Propias

Decisión: el plugin usa tablas propias con prefijo `lcter_wcpl_`.

Motivo:

* El saldo de puntos no debe depender de postmeta.
* El historial de puntos y regalos requiere trazabilidad estructurada.

Tablas:

* `lcter_wcpl_customer_points`
* `lcter_wcpl_transactions`
* `lcter_wcpl_rewards`
* `lcter_wcpl_order_rewards`

## TD-002 - Separar Saldo Y Transacciones

Decisión: el saldo actual se guarda en `lcter_wcpl_customer_points` y el historial en `lcter_wcpl_transactions`.

Motivo:

* Consultar saldo debe ser directo.
* Auditar cambios requiere historial completo.
* Todo cambio de puntos debe generar transacción.

## TD-003 - Usar `balance`, No `points`, Para El Saldo

Decisión: el campo de saldo se llama `balance`.

Motivo:

* Evita ambigüedad con los puntos de cada transacción.
* Refuerza que es el saldo actual del cliente.

## TD-004 - No Usar `product_points` Ni `points_per_currency`

Decisión: no se usa una tabla de puntos por producto ni tasa por producto.

Motivo:

* El cálculo de puntos depende del total del pedido, no del producto.
* La regla documentada es 1 céntimo = 1 punto.

## TD-005 - Regalos Como Productos WooCommerce

Decisión: los regalos son productos WooCommerce configurados como rewards.

Motivo:

* Permite reutilizar catálogo, nombre y SKU.
* Permite añadir regalos al pedido como order items.

## TD-006 - Registrar Regalos Canjeados En Tabla Propia

Decisión: cada regalo canjeado se guarda en `lcter_wcpl_order_rewards`.

Motivo:

* Clientify debe poder consultar qué regalos eligió cada cliente.
* Administración e informes necesitan una fuente estructurada.

## TD-007 - Metadatos En Pedido Y Order Item

Decisión: además de tablas propias, los regalos pueden guardarse como metadatos del pedido u order item.

Motivo:

* Facilita visibilidad en WooCommerce.
* Prepara integraciones que lean datos desde el pedido.

## TD-008 - Seguridad WordPress

Decisión: las acciones deben usar nonces, capacidades, sanitización, escape y `$wpdb->prepare()`.

Motivo:

* Es la base de seguridad documentada para el plugin.

## TD-009 - Estándares De Código

Decisión: el desarrollo debe seguir WordPress Coding Standards, PSR-12 donde sea compatible con WordPress y principios SOLID.

Motivo:

* `README.md` define esos estándares.
* `AGENTS.md` exige una arquitectura mantenible, segura y separada por responsabilidades.

## TD-010 - Repositorios Por Agregado Persistente

Decisión: cada tabla principal dispone de un repositorio y `Database` solo administra el esquema.

Motivo:

* Evitar SQL dentro de administración, adaptadores o servicios.
* Permitir probar servicios sustituyendo sus dependencias.
* Evitar que una única clase de base de datos concentre todas las responsabilidades.

## TD-011 - Operaciones De Saldo Atómicas

Decisión: las operaciones de suma y canje bloquean la fila de saldo y actualizan saldo e historial dentro de la misma transacción SQL.

Motivo:

* Mantener correctos `balance_before` y `balance_after` ante peticiones concurrentes.
* Revertir el saldo si no se puede registrar su transacción.
* Mantener la condición `balance >= puntos` también en el `UPDATE` de canje.

## TD-012 - Idempotencia Mediante Clave Única

Decisión: `lcter_wcpl_transactions.idempotency_key` es nullable y única. La acumulación usa `earned_order:{order_id}`.

Motivo:

* La base de datos es la garantía final frente a callbacks duplicados o concurrentes.
* El metadato del pedido se conserva como ayuda operativa, pero no es la fuente de idempotencia.
* Una clave independiente permite varios canjes u otros movimientos para un mismo pedido.

## TD-013 - Versionado Y Migraciones No Destructivas

Decisión: el esquema se versiona con `Database::SCHEMA_VERSION` y la opción `lcter_wcpl_schema_version`.

Motivo:

* Aplicar `dbDelta()` también cuando el plugin se actualiza sin reactivarse.
* Conservar columnas y tablas legacy hasta que exista una política de retirada documentada.
* Evitar pérdida de datos durante esta fase estructural.

## TD-014 - Detección Y Cálculo De Puntos Tras Pago

Decisión: la acumulación escucha `woocommerce_payment_complete`, `woocommerce_order_payment_status_changed`, `woocommerce_order_status_processing` y `woocommerce_order_status_completed`, pero siempre exige que `WC_Order::is_paid()` sea verdadero.

El importe acumulable se convierte a céntimos y se calcula así:

`total del pedido - total de portes - impuesto de portes`

Motivo:

* Cubrir pagos inmediatos, métodos diferidos y cambios manuales a estados pagados.
* Evitar acumulación durante checkout o en pedidos no pagados.
* Aplicar exactamente la equivalencia documentada de un céntimo por punto.
* Restar tanto el importe neto del porte como su IVA, manteniendo incluido el IVA del resto del pedido.

## TD-015 - Pedidos Sin Cliente Registrado

Decisión: durante la Fase 2 solo acumulan pedidos con `customer_id` positivo. Los pedidos de invitado no crean saldo ni transacción.

Motivo:

* `lcter_wcpl_customer_points` identifica el saldo mediante `customer_id`.
* No está definido cómo asociar de forma estable un pedido de invitado a un saldo de fidelización.
* La política futura queda registrada en `docs/open-questions.md`.

## TD-016 - Configuración Manual De Rewards En Producto

Decisión: la presencia de una fila en `lcter_wcpl_rewards` identifica un producto como reward. La edición del producto permite guardar manualmente coste en puntos, estado, orden y fechas.

Desmarcar “Regalo canjeable” elimina la fila. Desmarcar “Regalo activo” conserva la fila con `active=0`.

Motivo:

* Mantener `lcter_wcpl_rewards` como fuente de verdad del catálogo.
* Diferenciar eliminar una configuración de retirarla temporalmente del catálogo.
* No introducir todavía recálculo automático del coste. La interfaz solo muestra como ayuda la regla precio con IVA incluido × 2.000.

## TD-017 - Disponibilidad Y Límite Del Catálogo

Decisión: `Rewards_Service::MAX_VISIBLE_REWARDS` limita la consulta activa a 12 elementos.

Los rewards activos se ordenan por `sort_order` e `id` y se filtran usando la fecha y hora local de WordPress:

* `active = 1`.
* `starts_at` vacío o menor/igual al momento actual.
* `ends_at` vacío o mayor/igual al momento actual.

Motivo:

* Aplicar el límite documentado de aproximadamente 10-12 productos.
* Permitir preparar campañas futuras o conservar campañas finalizadas sin borrarlas.
* Mantener las fechas coherentes con la zona horaria configurada en WordPress.

## TD-018 - Subtotal Mínimo Para Canje

Decisión: en la Fase 4 el mínimo de 60 EUR se calcula con `WC_Cart::get_subtotal()` más `get_subtotal_tax()`.

Motivo:

* Incluye IVA.
* Excluye portes y su impuesto.
* Al usar subtotal, se evalúa antes de cupones y descuentos de carrito.
* Las líneas de reward tienen precio cero y no alteran el mínimo.

## TD-019 - Canje Diferido Hasta El Pago

Decisión: el checkout valida y guarda la selección, pero los puntos se descuentan únicamente cuando `WC_Order::is_paid()` es verdadero.

Los hooks de canje usan prioridad 5 y la acumulación del pedido prioridad 10.

Motivo:

* Un pedido abandonado o impagado no consume saldo.
* El pedido actual no puede financiar su propio canje.
* El saldo y el catálogo se revalidan inmediatamente antes del descuento.

No se reserva saldo entre creación y pago. Esta política queda como pregunta abierta para métodos de pago diferidos.

## TD-020 - Idempotencia Y Recuperación Del Canje

Decisión: cada pedido genera como máximo una transacción `redeemed` con clave `redeemed_order:{order_id}`. Cada reward agregado genera una fila con clave `redeemed_order:{order_id}:reward:{reward_id}`.

Motivo:

* Repetir hooks de pago no vuelve a descontar puntos.
* Si la petición termina después del descuento pero antes de completar `order_rewards`, un reintento rellena únicamente las filas ausentes.
* El pedido guarda estados `pending_payment`, `processing_error`, `rejected` o `completed` para diagnóstico.

## TD-021 - Alcance Del Checkout Clásico

Decisión: el selector de esta fase usa hooks del checkout clásico de WooCommerce.

Motivo:

* Los Checkout Blocks requieren una integración Store API y componentes específicos.
* La API externa y REST están fuera del alcance solicitado para esta fase.
