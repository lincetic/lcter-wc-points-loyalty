# Reglas de negocio

## BR-001 - Acumulación de puntos

Los clientes acumulan puntos en base a sus compras realizadas en WooCommerce.

## BR-002 - Momento de acumulación

Los puntos se suman siempre a posteriori.

Solo se acumulan cuando el pedido está pagado.

Nunca se acumulan durante el checkout ni antes del pago.

## BR-003 - Cálculo de puntos

Los puntos se calculan sobre el total del pedido con IVA incluido, excluyendo portes.

Regla:

* 1 céntimo = 1 punto
* 1 euro = 100 puntos

Ejemplo:

Pedido de 60,00 € sin portes:

* 60,00 € = 6.000 puntos

Pedido de 60,15 € sin portes:

* 60,15 € = 6.015 puntos

## BR-004 - Compatibilidad con promociones

El sistema de puntos debe ser compatible y acumulable con otras promociones activas en WooCommerce.

## BR-005 - Pedido mínimo para canje

El cliente solo podrá canjear puntos si el carrito/pedido alcanza un mínimo de 60 € IVA incluido.

## BR-006 - Saldo disponible para canje

Durante el checkout, el saldo disponible será el saldo actual del cliente.

El pedido en curso no suma puntos para ese canje.

## BR-007 - Selección de regalos

El cliente podrá seleccionar uno o varios regalos.

También podrá seleccionar varias unidades del mismo regalo si tiene saldo suficiente.

## BR-008 - Límite de saldo

El sistema nunca debe permitir saldo negativo.

## BR-009 - Regalos a coste cero

Los regalos canjeados se añadirán al pedido con precio 0.

## BR-010 - Identificación de regalos

Los regalos añadidos al pedido deberán poder identificarse claramente como REGALO.

Debe quedar visible para administración y para integraciones.

## BR-011 - No canjear puntos

El cliente debe poder elegir la opción:

“No canjear mis puntos”.

## BR-012 - Precio en puntos de regalos

El coste sugerido en puntos de un regalo se calcula así:

Precio IVA incluido del producto x multiplicador configurado

El multiplicador es un entero positivo configurable desde administración y su valor por defecto es 2.000.

La edición del reward mantiene un coste manual. El valor sugerido solo sustituye el campo cuando administración pulsa expresamente la acción de cálculo; nunca se sobrescribe automáticamente.

Ejemplo:

Producto de 4,23 €:

4,23 x 2.000 = 8.460 puntos

## BR-013 - Catálogo de regalos

Los regalos estarán limitados aproximadamente a 10-12 productos.

Se cambiarán cada trimestre.

Pueden ser productos, merchandising u otros artículos.

## BR-014 - Bonus inicial

Al iniciar el sistema, se quiere añadir un saldo inicial a todos los clientes. El importe es un entero positivo configurable desde administración y su valor por defecto es 10.000 puntos.

Este movimiento debe quedar registrado como transacción.

Cada cliente solo puede recibir una transacción `initial_bonus`, aunque el importe configurado cambie después.

## BR-015 - Clientify

Debe registrarse qué regalos ha elegido cada cliente para que Clientify pueda consultar o recibir esa información.

## BR-016 - Historial

Todo cambio de puntos debe generar una transacción.

Tipos previstos:

* earned
* redeemed
* initial_bonus
* manual_adjustment
* refund
* cancelled
* failed
* returned_redeemed
* restored_earned
* restored_redeemed

## BR-017 - Ajuste manual administrativo

Un usuario con capacidad `manage_woocommerce` puede sumar o restar manualmente un número entero de puntos desde la edición de un cliente WooCommerce.

El ajuste requiere un motivo no vacío, nunca puede dejar saldo negativo y debe crear una transacción `manual_adjustment` con delta firmado, `balance_before`, `balance_after`, `description` y `created_by`.

Los ajustes manuales modifican `balance`, pero no los acumulados históricos brutos `total_earned` ni `total_redeemed`.

## BR-018 - Estados Terminales De Pedido

Cuando un pedido pagado pasa a `cancelled`, `refunded` o `failed`, el plugin debe revertir los puntos ganados si existe una transacción `earned`, devolver los puntos gastados en regalos si existe una transacción `redeemed`, mantener idempotencia y no crear movimientos si el pedido nunca llegó a generar o descontar puntos.

La devolución de puntos canjeados incrementa `balance`, crea una transacción `returned_redeemed` y no modifica los acumulados históricos brutos.

## BR-019 - Estado Visual De Regalos

El texto visible de `REGALO` debe derivarse del estado actual de WooCommerce salvo que exista una reversiÃ³n contable pendiente:

* `pending`, `pending_payment` y `on-hold`: `PENDIENTE DE PAGO`
* `processing` y `completed`: `CANJEADO`
* `cancelled`: `CANCELADO`
* `refunded`: `REEMBOLSADO`
* `failed`: `FALLIDO`
* `processing` o `completed` con `loyalty_movements_reversed` o `loyalty_restore_error`: `PENDIENTE DE RESTAURAR PUNTOS`

No se usa el texto `REVERTIDO`.

## BR-020 - Estado Contable De Movimientos De FidelizaciÃ³n

El estado operativo de WooCommerce no es la fuente de verdad contable. El pedido guarda un estado interno `_lcter_wcpl_loyalty_movements_state` con estos valores:

* `loyalty_movements_applied`
* `loyalty_movements_reversed`
* `loyalty_movements_restored`
* `loyalty_restore_error`

Cuando un pedido pagado se revierte por `cancelled`, `refunded` o `failed`, el estado contable pasa a `loyalty_movements_reversed` solo si la reversiÃ³n o devoluciÃ³n se completÃ³ o ya existÃ­a de forma idempotente.

## BR-021 - Reapertura Manual De Pedidos Revertidos

Cambiar manualmente un pedido desde `cancelled`, `refunded` o `failed` a `processing` o `completed` no restaura automÃ¡ticamente puntos ganados ni vuelve a descontar puntos de regalos. El cambio de estado WooCommerce no acredita por sÃ­ solo un nuevo cobro real.

La ediciÃ³n del pedido debe mostrar una advertencia administrativa y requerir una acciÃ³n explÃ­cita para restaurar movimientos de fidelizaciÃ³n.

## BR-023 - Ciclos Contables De Fidelizacion

Cada pedido tiene un ciclo contable interno `_lcter_wcpl_loyalty_cycle`. El primer ciclo aplicado es 1. Si el metadato no existe, el pedido se trata como ciclo 1 para compatibilidad.

Las escrituras nuevas de acumulacion, canje, reversion, devolucion y restauracion deben incluir `order_id`, tipo de operacion y ciclo en `idempotency_key`.

Una restauracion correcta abre el ciclo siguiente: restaura los movimientos revertidos del ciclo actual mediante `restored_earned` y `restored_redeemed` en `cycle + 1`, actualiza `_lcter_wcpl_loyalty_cycle` solo al finalizar correctamente y permite una nueva cancelacion legitima en ese nuevo ciclo.

La restauracion calcula `projected_balance = current_balance + restored_earned_points - restored_redeemed_points`. Si `projected_balance < 0`, no se restaura parcialmente y se guardan `current_balance`, `restored_earned_points`, `restored_redeemed_points`, `projected_balance` y `missing_points`.

Repetir la misma operacion dentro del mismo ciclo debe ser idempotente. Una operacion equivalente en un ciclo posterior debe poder ejecutarse.

Las claves antiguas sin ciclo se reconocen como ciclo 1 sin migracion destructiva.

Una misma aplicacion de puntos solo puede revertirse una vez por pedido y ciclo. La retirada de puntos ganados se protege con `reversed_earned_order:{order_id}:cycle:{cycle}` y la devolucion de puntos canjeados con `returned_redeemed_order:{order_id}:cycle:{cycle}`. Cambiar despues de `cancelled` a `refunded` o de `failed` a `cancelled` no autoriza nuevos movimientos contables en el mismo ciclo.

El primer estado terminal que provoca la reversion debe quedar auditado en metadata con `trigger_status`, `trigger_hook`, `cycle` y `order_id`.

## BR-022 - RestauraciÃ³n Administrativa De Movimientos

La acciÃ³n "Restaurar movimientos de fidelizaciÃ³n" requiere `manage_woocommerce`, nonce, pedido pagado, movimientos originales, reversiÃ³n existente y ausencia de restauraciÃ³n previa.

La restauraciÃ³n crea transacciones `restored_earned` y, si hubo regalos, `restored_redeemed`. Debe registrar `balance_before`, `balance_after`, administrador ejecutor e idempotencia por ciclo con `restored_order:{order_id}:cycle:{new_cycle}`, `restored_earned_order:{order_id}:cycle:{new_cycle}` y `restored_redeemed_order:{order_id}:cycle:{new_cycle}`.

Si `projected_balance = current_balance + restored_earned_points - restored_redeemed_points` queda por debajo de cero, no se restaura parcialmente: no se aÃ±aden puntos ganados, no se descuentan puntos canjeados, el pedido queda en `loyalty_restore_error` y se muestran saldo actual, saldo proyectado y puntos faltantes.
