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

El coste en puntos de un regalo se calcula así:

Precio IVA incluido del producto x 2.000

Ejemplo:

Producto de 4,23 €:

4,23 x 2.000 = 8.460 puntos

## BR-013 - Catálogo de regalos

Los regalos estarán limitados aproximadamente a 10-12 productos.

Se cambiarán cada trimestre.

Pueden ser productos, merchandising u otros artículos.

## BR-014 - Bonus inicial

Al iniciar el sistema, se quiere añadir un saldo inicial de 10.000 puntos a todos los clientes.

Este movimiento debe quedar registrado como transacción.

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
