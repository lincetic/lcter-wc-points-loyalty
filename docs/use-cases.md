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
3. Administración puede mantener el coste manual o pulsar la acción que propone `precio guardado con IVA incluido × multiplicador configurado`.
4. El cálculo sugerido no sobrescribe el coste sin esa acción explícita.
5. El catálogo se mantiene limitado aproximadamente a 10-12 productos.
6. El catálogo puede cambiar cada trimestre.

Reglas: BR-012, BR-013.

## UC-007 - Registrar Bonus Inicial

Actor: administración o proceso de inicialización.

1. Se identifica a los clientes que deben recibir el bonus.
2. Se lee el importe entero positivo configurado, con 10.000 puntos como valor por defecto.
3. Se añade ese saldo solo si el cliente nunca recibió un bonus inicial.
4. Cada movimiento queda registrado como transacción de tipo `initial_bonus`.

Reglas: BR-014, BR-016.

## UC-008 - Consultar Regalos Para Clientify

Actor: integración Clientify.

1. Clientify necesita saber qué regalos eligió un cliente.
2. El plugin conserva esa información en `lcter_wcpl_order_rewards`.
3. La información también puede estar disponible como metadatos del pedido u order item.

Reglas: BR-015, database.md.

## UC-009 - Configurar Valores Generales

Actor: administración con `manage_woocommerce`.

1. Administración abre Points Loyalty > Configuración.
2. Indica enteros positivos para el bonus inicial y el multiplicador de rewards.
3. WordPress verifica el nonce de Settings API y la capacidad.
4. El plugin valida y guarda las opciones; un valor inválido conserva el último valor válido.

Reglas: BR-012, BR-014.

## UC-010 - Ajustar Manualmente Puntos De Cliente

Actor: administración con `manage_woocommerce` y permiso para editar el usuario.

1. Administración abre la edición de un usuario con rol `customer`.
2. En el formulario independiente de puntos introduce un entero firmado distinto de cero, un motivo obligatorio y pulsa “Registrar ajuste”.
3. El plugin verifica nonce, capacidades, cliente y entradas.
4. El servicio bloquea el saldo y rechaza la operación si el resultado sería negativo.
5. En una única transacción atómica actualiza el saldo y crea `type=manual_adjustment` con saldos anterior y posterior, motivo y administrador responsable.

Reglas: BR-008, BR-016, BR-017.

## UC-011 - Cerrar Pedido Pagado Con Regalos

Actor: WooCommerce o administración.

1. Un pedido pagado llega a `cancelled`, `refunded` o `failed`.
2. El plugin sincroniza el estado visual de los regalos antes de los emails automáticos.
3. Si el pedido generó puntos, registra la reversión idempotente correspondiente.
4. Si el pedido descontó puntos por regalos, devuelve esos puntos con una transacción `returned_redeemed`.
5. Repetir el mismo evento o pasar posteriormente de `cancelled` a `refunded` no duplica saldo ni transacciones.

Reglas: BR-008, BR-016, BR-018, BR-019.

## UC-012 - Cancelar Pedido No Pagado Con Regalos Seleccionados

Actor: WooCommerce o administración.

1. Un pedido con regalo seleccionado se cancela antes del pago.
2. El plugin no revierte puntos ganados ni devuelve puntos canjeados porque no existieron esas transacciones.
3. El regalo se muestra como `REGALO: CANCELADO`.

Reglas: BR-008, BR-018, BR-019.

## UC-013 - Reabrir Pedido Con Movimientos Revertidos

Actor: administraciÃ³n.

1. Un pedido pagado fue cancelado, reembolsado o marcado como fallido y el plugin revirtiÃ³ sus movimientos.
2. AdministraciÃ³n cambia el pedido a `processing` o `completed`.
3. WooCommerce muestra el pedido como pagado.
4. El plugin no otorga puntos ganados ni descuenta de nuevo puntos de regalos.
5. El pedido conserva `_lcter_wcpl_loyalty_movements_state=loyalty_movements_reversed`.
6. La ediciÃ³n del pedido muestra la advertencia de restauraciÃ³n pendiente.
7. Los regalos se muestran como `REGALO: PENDIENTE DE RESTAURAR PUNTOS`.

Reglas: BR-019, BR-020, BR-021.

## UC-014 - Restaurar Movimientos De FidelizaciÃ³n

Actor: administraciÃ³n con `manage_woocommerce`.

1. AdministraciÃ³n abre un pedido pagado con movimientos revertidos.
2. Pulsa "Restaurar movimientos de fidelizaciÃ³n".
3. WordPress valida nonce y capacidad.
4. El servicio comprueba pedido pagado, movimientos originales, reversiÃ³n y ausencia de restauraciÃ³n.
5. Si el saldo alcanza para descontar de nuevo los regalos, se crean `restored_earned` y `restored_redeemed` de forma atÃ³mica.
6. El pedido pasa a `loyalty_movements_restored`, aÃ±ade nota operativa y los regalos vuelven a `REGALO: CANJEADO`.
7. Si el saldo no alcanza, no se crea ningÃºn movimiento, el pedido queda en `loyalty_restore_error` y muestra saldo necesario/disponible.
8. Repetir la acciÃ³n no duplica movimientos por la clave `restored_order:{order_id}:cycle:{new_cycle}` ni por las claves `restored_earned_order`/`restored_redeemed_order` del mismo ciclo.

Reglas: BR-008, BR-016, BR-020, BR-021, BR-022.
