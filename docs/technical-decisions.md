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
* Conservar el coste manual como fuente de verdad y ofrecer una acción explícita que copia al campo la sugerencia `precio guardado con IVA incluido × multiplicador configurado`.
* No recalcular ni sobrescribir el coste automáticamente cuando cambian el precio o el multiplicador.

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

## Anexo - Estabilizacion Tecnica

Decision: la administracion general, la configuracion del reward en producto y la trazabilidad del pedido se registran en clases independientes. `Admin` ya no mezcla ajustes, catalogo y lectura de canjes.

No se divide aun el adaptador de checkout: sus 490 lineas son deuda tecnica conocida, pero extraerlo durante esta estabilizacion aumentaria el riesgo sobre el flujo de pago.

PHPStan comienza en nivel 5; PHPCS aplica `WordPress`, `WordPress-Extra` y `WordPress-Docs`; PHPUnit comienza con pruebas unitarias de servicios sin arrancar WordPress. Los hooks, la base de datos y la concurrencia requieren integracion WordPress/WooCommerce.

`WooCommerce_Rewards_Adapter::redeem_reward_for_order()` y `Rewards::redeem_reward_for_order()` conservan firma y tipo de retorno por compatibilidad, pero quedan deprecados y cerrados de forma segura: emiten `_deprecated_function()`, devuelven `false` y no modifican saldo, pedido ni trazabilidad. El unico flujo de canje soportado es el procesamiento idempotente del pedido pagado.

El flujo soportado sigue siendo `WooCommerce_Checkout_Adapter` junto con `Reward_Redemption_Service`.

Los regalos creados durante checkout tienen `_lcter_wcpl_reward_state=reward_selected` en pedido y order item. En ese estado el metadato visible indica `REGALO: PENDIENTE DE PAGO` y administracion muestra que no deben prepararse ni entregarse. Solo despues de completar descuento y `order_rewards` el estado cambia a `reward_redeemed` y el item muestra `REGALO: CANJEADO`. Si el canje falla, permanece seleccionado; si se rechaza y se retiran las lineas, el estado se elimina.

## TD-022 - Servicio De Trazabilidad Neutral

Decisión: `Reward_Traceability_Service` concentra las consultas de regalos canjeados y genera payloads internos neutrales por pedido o cliente.

Motivo:

* Mantener `lcter_wcpl_order_rewards` como fuente principal para administración, informes e integraciones.
* Evitar que una futura integración Clientify dependa de HTML administrativo o de metadatos WooCommerce.
* Normalizar identificadores y cantidades como enteros y conservar nombre, SKU y fecha como snapshot histórico.
* Permitir que API, webhook, CSV o sincronización consuman el mismo servicio cuando se defina el mecanismo externo.

La pantalla de pedido usa capacidad de edición del pedido o `manage_woocommerce` y escapa todos los valores al renderizar. No se implementa ninguna operación externa en esta decisión.

## TD-023 - Bonus Inicial Manual E Idempotente

Decision: el bonus inicial se ejecuta exclusivamente desde una accion manual del dashboard por usuarios con `manage_woocommerce`. Requiere nonce y una confirmacion explicita. No se ejecuta durante activacion ni en tareas automaticas.

En esta fase se procesan solo usuarios WordPress con rol `customer`. Cada cliente recibe el importe configurado —10.000 puntos por defecto— mediante la operacion atomica de saldo y una transaccion `initial_bonus` con clave `initial_bonus:{customer_id}`.

Además de la clave nueva, la comprobación atómica por `customer_id + type=initial_bonus` reconoce transacciones anteriores cuya clave incluía el importe. Así el bonus sigue siendo único aunque cambie la configuración o existan datos legacy.

El resumen se guarda temporalmente por administrador y muestra procesados, bonificados, omitidos y errores tras la redireccion. El criterio de clientes puede cambiar en una fase posterior; no incluye invitados ni otros roles.

## TD-024 - Reversion Total Por Cancelacion O Reembolso Completo

Decision: `woocommerce_order_status_cancelled` y `woocommerce_order_fully_refunded` revierten todos los puntos de la transaccion `earned` del pedido. Ambos casos generan una transaccion `cancelled` con clave `cancelled_order:{order_id}`; el segundo hook corresponde exclusivamente a reembolso total y aporta tambien `refund_id` a la metadata.

La transaccion `earned` es la fuente de verdad para el importe. El metadato `_lcter_wcpl_points_awarded` no autoriza por si solo una reversion. La operacion bloquea el saldo, registra `balance_before` y `balance_after` y no permite saldo negativo.

Si el cliente ya no conserva saldo suficiente, no se descuenta parcialmente: el pedido queda con `_lcter_wcpl_points_cancellation_status=processing_error`, detalle interno y nota operativa. La repeticion del hook es segura y una reversion completada se reconoce por clave y por `order_id + type`.

`total_earned` se mantiene como acumulado historico bruto y no se reduce; `total_redeemed` tampoco cambia. El neto se obtiene del historial incluyendo movimientos `cancelled`. Reembolsos parciales, restauracion de regalos canjeados y reapertura posterior del pedido quedan fuera de esta decision.

## TD-025 - Recuperacion Manual De Errores De Pedido

Decision: la edicion administrativa del pedido muestra una seccion de incidencias cuando el canje o la reversion de puntos tienen estado `processing_error`. Los canjes `rejected` tambien se muestran para diagnostico, pero no son reintentables porque sus lineas de regalo ya fueron retiradas.

Cada incidencia muestra tipo, mensaje, puntos, fecha y accion recomendada. Los reintentos exigen `manage_woocommerce`, nonce ligado a pedido y operacion y una comprobacion final de que el estado sigue siendo recuperable.

El reintento de canje vuelve a ejecutar `WooCommerce_Checkout_Adapter::process_paid_order()`: `redeemed_order:{order_id}` evita otro descuento y las claves de `order_rewards` completan solo filas ausentes. El reintento de cancelacion usa `WooCommerce_Orders_Adapter::retry_order_reversal()` y conserva `cancelled_order:{order_id}`. No se introducen operaciones alternativas sobre saldo ni nuevas reglas de negocio.

## TD-026 - Opciones De Negocio Mediante Settings API

Decisión: `lcter_wcpl_initial_bonus_points` y `lcter_wcpl_reward_cost_multiplier` son opciones independientes registradas con Settings API. Ambas aceptan únicamente enteros positivos dentro del rango `INT`; sus valores por defecto son 10.000 y 2.000.

La pantalla y el guardado usan `manage_woocommerce`. Settings API aporta el nonce; los callbacks de sanitización conservan el último valor válido cuando la entrada no cumple la regla. `Settings` centraliza nombres, valores por defecto y lectura defensiva.

Motivo: evitar constantes dispersas y garantizar que bonus, ayuda administrativa y futuras lecturas consuman la misma configuración validada.

## TD-027 - Ajuste Manual Atómico Desde El Cliente

Decisión: la edición de usuarios con rol `customer` muestra el saldo y los controles asociados a un formulario HTML independiente que envía directamente a `admin-post.php`. La operación usa `action=lcter_wcpl_adjust_customer_points` y requiere `manage_woocommerce`, permiso para editar el usuario y un nonce específico.

El formulario se renderiza fuera del formulario principal de WordPress y sus controles se asocian mediante el atributo `form`. Así no existe HTML anidado ni se acopla el movimiento de saldo al guardado general del perfil. Tras procesar, la acción redirige a `user-edit.php?user_id={id}`.

`Points_Service::adjust_points()` bloquea la fila del cliente y coordina `Customer_Points_Repository::adjust()` con `Transactions_Repository::insert()` dentro de la misma transacción. Registra `type=manual_adjustment`, delta firmado, `balance_before`, `balance_after`, `source=woocommerce_customer_admin`, `description` y `created_by`; no usa SQL en administración ni en el servicio.

Un ajuste negativo que supere el saldo se rechaza por el servicio y también por la condición del repositorio. `total_earned` y `total_redeemed` no cambian porque representan acumulados históricos brutos de compras y canjes, no correcciones administrativas.
