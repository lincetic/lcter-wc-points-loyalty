# Casos De Uso

## UC-001 - Acumular Puntos Tras Pago

Actor: WooCommerce.

1. Un pedido queda pagado.
2. El plugin obtiene el cliente del pedido.
3. El plugin calcula puntos sobre el total con IVA incluido, excluyendo portes.
4. El plugin suma los puntos al saldo del cliente.
5. El plugin registra una transacción de tipo `earned`.

Reglas: BR-001, BR-002, BR-003, BR-016.

## UC-002 - Consultar Saldo De Cliente

Actor: cliente o administración.

1. Se solicita el saldo de un cliente.
2. El plugin consulta `lcter_wcpl_customer_points`.
3. Se devuelve el campo `balance`.

Reglas: AGENTS.md, database.md.

## UC-003 - Canjear Un Regalo

Actor: cliente.

1. El cliente tiene saldo disponible.
2. El carrito o pedido alcanza un mínimo de 60 EUR IVA incluido.
3. El cliente selecciona un regalo.
4. El plugin valida coste en puntos y saldo.
5. El regalo se añade al pedido con coste 0.
6. El order item se marca como REGALO.
7. El saldo se descuenta sin permitir saldo negativo.
8. El plugin registra la transacción y el regalo canjeado.

Reglas: BR-005, BR-006, BR-008, BR-009, BR-010, BR-012, BR-015, BR-016.

## UC-004 - Canjear Varios Regalos O Varias Unidades

Actor: cliente.

1. El cliente selecciona varios regalos o varias unidades del mismo regalo.
2. El plugin calcula el coste total en puntos.
3. El plugin valida que el saldo actual sea suficiente.
4. El plugin registra cada regalo canjeado de forma trazable.

Reglas: BR-006, BR-007, BR-008, BR-015.

## UC-005 - No Canjear Puntos

Actor: cliente.

1. El cliente llega al checkout.
2. El plugin ofrece la opción de no canjear puntos.
3. Si el cliente elige esa opción, no se descuentan puntos ni se añaden regalos.

Reglas: BR-011.

## UC-006 - Configurar Catálogo De Regalos

Actor: administración.

1. Administración selecciona productos WooCommerce como regalos.
2. Cada regalo queda asociado a un coste en puntos.
3. El catálogo se mantiene limitado aproximadamente a 10-12 productos.
4. El catálogo puede cambiar cada trimestre.

Reglas: BR-012, BR-013.

## UC-007 - Registrar Bonus Inicial

Actor: administración o proceso de inicialización.

1. Se identifica a los clientes que deben recibir el bonus.
2. Se añade un saldo inicial de 10.000 puntos.
3. Cada movimiento queda registrado como transacción de tipo `initial_bonus`.

Reglas: BR-014, BR-016.

## UC-008 - Consultar Regalos Para Clientify

Actor: integración Clientify.

1. Clientify necesita saber qué regalos eligió un cliente.
2. El plugin conserva esa información en `lcter_wcpl_order_rewards`.
3. La información también puede estar disponible como metadatos del pedido u order item.

Reglas: BR-015, database.md.
