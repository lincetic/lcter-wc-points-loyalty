# Modelo De Dominio

## Entidades

### Cliente

Representa a un usuario comprador de WooCommerce.

Atributos relevantes:

* `customer_id`
* saldo de puntos
* total ganado
* total canjeado

Persistencia principal:

* `lcter_wcpl_customer_points`

### Pedido

Representa un pedido WooCommerce.

Uso en el dominio:

* Fuente para calcular puntos acumulados.
* Contenedor de regalos canjeados.
* Fuente de datos para Clientify junto con los metadatos del pedido.

### Transacción De Puntos

Representa cualquier cambio en el saldo de puntos.

Tipos documentados:

* `earned`
* `redeemed`
* `initial_bonus`
* `manual_adjustment`
* `refund`
* `cancelled`
* `failed`
* `returned_redeemed`
* `restored_earned`
* `restored_redeemed`

Persistencia:

* `lcter_wcpl_transactions`

### Regalo / Reward

Representa un producto WooCommerce disponible para canje.

Atributos relevantes:

* producto WooCommerce asociado
* coste en puntos
* activo o inactivo
* orden
* fecha de inicio
* fecha de fin

Persistencia:

* `lcter_wcpl_rewards`

### Regalo Canjeado En Pedido

Representa un regalo elegido por un cliente dentro de un pedido concreto.

Atributos relevantes:

* pedido
* cliente
* producto
* order item WooCommerce
* reward
* cantidad
* coste unitario en puntos
* coste total en puntos
* nombre del producto
* SKU

Persistencia:

* `lcter_wcpl_order_rewards`

### Estado Contable De Fidelizacion Del Pedido

Representa la situacion contable de los movimientos de puntos asociados al pedido, independiente del estado operativo WooCommerce.

Valores:

* `loyalty_movements_applied`: los movimientos originales estan aplicados.
* `loyalty_movements_reversed`: existen movimientos originales y una reversion/devolucion posterior.
* `loyalty_movements_restored`: una restauracion administrativa explicita completo el ciclo.
* `loyalty_restore_error`: la restauracion fue intentada y rechazada sin movimientos parciales.

Persistencia:

* `_lcter_wcpl_loyalty_movements_state`
* `_lcter_wcpl_loyalty_restore_error`
* `_lcter_wcpl_loyalty_cycle`
* `_lcter_wcpl_loyalty_restore_current_balance`
* `_lcter_wcpl_loyalty_restore_projected_balance`
* `_lcter_wcpl_loyalty_restore_missing_points`
* `_lcter_wcpl_loyalty_restore_earned_points`
* `_lcter_wcpl_loyalty_restore_redeemed_points`
* `_lcter_wcpl_loyalty_restore_required_balance`
* `_lcter_wcpl_loyalty_restore_available_balance`

## Relaciones

* Un cliente tiene un saldo en `lcter_wcpl_customer_points`.
* Un cliente puede tener muchas transacciones.
* Un pedido puede generar una transacción `earned`.
* Un pedido revertido puede generar `restored_earned` y `restored_redeemed` mediante accion administrativa.
* Un pedido puede contener varios regalos canjeados.
* Un regalo canjeado referencia un producto WooCommerce y opcionalmente un reward.
* Un reward referencia un producto WooCommerce.

## Invariantes

* El saldo nunca debe ser negativo.
* Todo cambio de puntos debe generar transacción.
* Los puntos de un pedido solo se acumulan después del pago.
* El pedido actual no incrementa el saldo disponible para el canje del propio pedido.
* Los regalos añadidos al pedido deben tener coste 0.
* Los regalos deben poder identificarse como REGALO.
* Cambiar el estado WooCommerce no cambia por si solo el estado contable de fidelizacion.
* Una restauracion de movimientos se completa entera o no crea transacciones.
* El ciclo contable empieza en 1 y solo avanza cuando una restauracion administrativa finaliza correctamente.
* Los movimientos sin ciclo persistido o con claves legacy sin `:cycle:{n}` pertenecen al ciclo 1.
