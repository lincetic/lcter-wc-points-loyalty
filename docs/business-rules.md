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

## BR-017 - Ajuste manual administrativo

Un usuario con capacidad `manage_woocommerce` puede sumar o restar manualmente un número entero de puntos desde la edición de un cliente WooCommerce.

El ajuste requiere un motivo no vacío, nunca puede dejar saldo negativo y debe crear una transacción `manual_adjustment` con delta firmado, `balance_before`, `balance_after`, `description` y `created_by`.

Los ajustes manuales modifican `balance`, pero no los acumulados históricos brutos `total_earned` ni `total_redeemed`.

## BR-018 - Estados Terminales De Pedido

Cuando un pedido pagado pasa a `cancelled`, `refunded` o `failed`, el plugin debe revertir los puntos ganados si existe una transacción `earned`, devolver los puntos gastados en regalos si existe una transacción `redeemed`, mantener idempotencia y no crear movimientos si el pedido nunca llegó a generar o descontar puntos.

La devolución de puntos canjeados incrementa `balance`, crea una transacción `returned_redeemed` y no modifica los acumulados históricos brutos.

## BR-019 - Estado Visual De Regalos

El texto visible de `REGALO` debe derivarse del estado actual de WooCommerce:

* `pending`, `pending_payment` y `on-hold`: `PENDIENTE DE PAGO`
* `processing` y `completed`: `CANJEADO`
* `cancelled`: `CANCELADO`
* `refunded`: `REEMBOLSADO`
* `failed`: `FALLIDO`

No se usa el texto `REVERTIDO`.
