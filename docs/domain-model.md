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

## Relaciones

* Un cliente tiene un saldo en `lcter_wcpl_customer_points`.
* Un cliente puede tener muchas transacciones.
* Un pedido puede generar una transacción `earned`.
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
