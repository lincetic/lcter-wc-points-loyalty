# Plan De Pruebas

## Objetivo

Validar que LCTER WC Points Loyalty cumple las reglas documentadas sin permitir saldos negativos ni canjes no trazados.

## Pruebas Unitarias

### Cálculo De Puntos

Casos:

* Pedido de 60,00 EUR sin portes genera 6.000 puntos.
* Pedido de 60,15 EUR sin portes genera 6.015 puntos.
* Los portes no suman puntos.
* Pedido total de 66,20 EUR, con 5,00 EUR de portes y 1,05 EUR de IVA de portes, genera 6.015 puntos.

Reglas: BR-003.

### Saldo

Casos:

* Sumar puntos incrementa `balance` y `total_earned`.
* Canjear puntos reduce `balance` e incrementa `total_redeemed`.
* Un canje superior al saldo disponible falla.
* El saldo nunca queda negativo.

Reglas: BR-008, BR-016.

### Transacciones

Casos:

* Cada acumulación genera transacción.
* Cada canje genera transacción.
* El bonus inicial genera transacción `initial_bonus`.
* Se guardan `balance_before` y `balance_after`.
* La acumulación crea `type=earned` e `idempotency_key=earned_order:{order_id}`.
* Repetir el procesamiento del mismo pedido no crea una segunda transacción ni modifica otra vez el saldo.
* Una transacción legacy con el mismo `order_id` y `type=earned`, aunque no tenga `idempotency_key`, impide duplicar la acumulación.

Reglas: BR-014, BR-016.

## Pruebas De Integración WooCommerce

Casos:

* Un pedido no pagado no genera puntos.
* Un pedido pagado genera puntos.
* Un pedido en `pending`, `on-hold`, `failed` o `cancelled` no genera puntos porque `is_paid()` es falso.
* Los hooks `woocommerce_payment_complete`, `woocommerce_order_payment_status_changed`, `woocommerce_order_status_processing` y `woocommerce_order_status_completed` pueden ejecutarse para el mismo pedido sin duplicar puntos.
* Un pedido sin `customer_id` positivo no genera saldo ni transacción.
* Tras acumular, el pedido guarda `_lcter_wcpl_points_awarded` como metadato auxiliar.
* El pedido actual no suma puntos disponibles para su propio canje.
* Un regalo se añade al pedido con coste 0.
* El order item queda identificado como REGALO.
* Se permite más de un regalo si hay saldo suficiente.
* Se permite más de una unidad del mismo regalo si hay saldo suficiente.

Reglas: BR-002, BR-006, BR-007, BR-009, BR-010.

## Pruebas Manuales De Fase 2

Mientras no exista infraestructura PHPUnit, validar UC-001 y UC-002 en una instalación de desarrollo:

1. Crear un cliente registrado con saldo inexistente o cero.
2. Crear un pedido de 66,20 EUR que incluya 5,00 EUR de portes y 1,05 EUR de IVA de portes.
3. Mantener el pedido sin pagar y confirmar que no existen filas nuevas en `lcter_wcpl_customer_points` ni `lcter_wcpl_transactions`.
4. Marcar el pedido como pagado y confirmar saldo y `total_earned` iguales a 6.015.
5. Confirmar una única transacción `earned`, con `points=6015`, saldos anterior/posterior correctos, `order_id` e `idempotency_key=earned_order:{order_id}`.
6. Volver a guardar los estados `processing` y `completed`; confirmar que saldo y número de transacciones no cambian.
7. Eliminar únicamente el metadato auxiliar del pedido y repetir el hook; confirmar que la transacción sigue evitando el duplicado.
8. Consultar el saldo desde Mi cuenta y mediante `[lcter_wcpl_customer_points]`.
9. Repetir con un pedido de invitado y confirmar que no se crea saldo ni transacción.

Resultado esperado: UC-001 y UC-002 se cumplen sin depender del metadato del pedido como garantía de idempotencia.

## Pruebas De Base De Datos

Casos:

* `dbDelta()` crea las cuatro tablas.
* No se usa `product_points`.
* No se usa `points_per_currency`.
* No se crean registros huérfanos en `lcter_wcpl_order_rewards` sin pedido, cliente o producto.
* Las consultas con datos dinámicos usan `$wpdb->prepare()`.

Fuentes: AGENTS.md, database.md.

## Pruebas Del Catálogo De Rewards

Casos de servicio y repositorio:

* Crear un reward guarda `product_id`, `points_cost`, `active`, `sort_order`, `starts_at` y `ends_at`.
* Guardar de nuevo el mismo `product_id` actualiza la fila existente.
* Consultar por producto devuelve su reward.
* Desactivar conserva la fila con `active=0`; eliminar borra la configuración.
* Una fecha inválida o una fecha de fin anterior al inicio no se guarda.
* La consulta activa excluye rewards inactivos, aún no iniciados y finalizados.
* Los límites de fecha son inclusivos.
* Los resultados se ordenan por `sort_order` y después por `id`.
* La consulta activa devuelve como máximo 12 elementos.
* No se crean ni consultan `product_points` o `points_per_currency`.

Reglas: BR-012, BR-013. Caso de uso: UC-006.

## Pruebas Manuales De Fase 3

Mientras no exista infraestructura PHPUnit:

1. Editar un producto WooCommerce y abrir la pestaña “Puntos de Lealtad”.
2. Marcar “Regalo canjeable” y comprobar que aparecen coste, activo, orden y fechas.
3. Confirmar que la ayuda del coste muestra “precio con IVA incluido × 2.000” sin modificar automáticamente el valor.
4. Guardar un coste positivo, orden, inicio y fin; comprobar una única fila en `lcter_wcpl_rewards` con todos los valores.
5. Editar los valores y confirmar que se actualiza la misma fila para el `product_id`.
6. Desmarcar “Regalo activo” y confirmar que la fila permanece con `active=0` y no aparece en la consulta activa.
7. Configurar fechas futuras y pasadas para verificar que solo aparece dentro de su ventana inclusiva.
8. Configurar más de 12 rewards activos y confirmar que la consulta devuelve los 12 primeros por `sort_order` e `id`.
9. Desmarcar “Regalo canjeable” y confirmar que se elimina la fila del producto.
10. Repetir un guardado con nonce inválido o sin capacidad de edición y confirmar que no cambia la tabla.

## Pruebas Del Canje En Checkout

Casos de servicio:

* Subtotal inferior a 6.000 céntimos rechaza el canje.
* El mínimo usa subtotal más IVA y no incluye portes.
* Uno o varios rewards y varias unidades calculan el coste total desde la base de datos.
* Cantidades negativas, decimales o no numéricas se rechazan.
* Rewards inactivos, futuros o caducados se rechazan.
* El coste enviado por el navegador se ignora.
* El total superior al balance se rechaza.
* Los puntos generados por el propio pedido se excluyen del saldo disponible en reintentos.

Casos de carrito y pedido:

* “No canjear mis puntos” elimina cualquier línea de reward del carrito.
* Un producto normal y el mismo producto como reward permanecen como líneas separadas.
* Repetir la selección sustituye controladamente las líneas reward y no crea duplicados.
* Todas las líneas reward tienen subtotal, total e impuestos iguales a cero.
* Carrito y pedido muestran la etiqueta `REGALO`.
* Cada order item contiene `_lcter_wcpl_is_reward`, `_lcter_wcpl_reward_id`, `_lcter_wcpl_points_cost_each` y `_lcter_wcpl_points_cost_total`.
* Crear un pedido impagado deja el canje en `pending_payment` y no descuenta puntos.
* Al pagar se crea una transacción `redeemed` y una fila por reward en `lcter_wcpl_order_rewards`.
* Repetir todos los hooks de pago no descuenta ni registra dos veces.
* Un fallo después de descontar puede reintentarse para completar filas ausentes.

## Pruebas Manuales De Fase 4

1. Con un cliente sin sesión, confirmar que solo se ofrece no canjear y se solicita iniciar sesión.
2. Con subtotal de 59,99 EUR más portes, confirmar que no se permite canjear.
3. Con subtotal de 60 EUR IVA incluido y cualquier porte, seleccionar dos rewards y varias unidades.
4. Manipular desde el navegador cantidades, reward ID y valores visuales; confirmar que servidor usa rewards y costes de base de datos o rechaza la petición.
5. Enviar nonce inválido y confirmar que no se crean líneas reward.
6. Elegir “No canjear mis puntos” y confirmar que no se añaden regalos ni se descuentan puntos.
7. Crear un pedido con pago diferido y confirmar líneas a coste cero, metadatos completos y estado `pending_payment`, sin transacción `redeemed`.
8. Completar el pago y confirmar que el canje se ejecuta antes de la acumulación del pedido.
9. Confirmar una única transacción `redeemed_order:{order_id}`, con saldo anterior/posterior y puntos negativos.
10. Confirmar una fila por reward con clave `redeemed_order:{order_id}:reward:{reward_id}`.
11. Confirmar `_lcter_wcpl_order_rewards`, `_lcter_wcpl_reward_redemption_status=completed` y `_lcter_wcpl_order_reward_id` en los items.
12. Repetir cambios a `processing` y `completed`; comprobar que saldo y filas no cambian.
13. Reducir el saldo entre creación y pago; confirmar que el canje se rechaza sin saldo negativo y los regalos se retiran.
14. Simular un fallo al insertar una fila de `order_rewards`; reintentar el hook y comprobar que no se vuelve a descontar.

Alcance: estas pruebas corresponden al checkout clásico. Checkout Blocks queda pendiente.

## Pruebas De Clientify

Casos:

* Un canje crea fila en `lcter_wcpl_order_rewards`.
* Un canje guarda metadatos en pedido u order item.
* Se pueden consultar regalos por pedido.
* Se pueden consultar regalos por cliente.
* Las consultas por pedido y cliente usan `lcter_wcpl_order_rewards`, no metadatos como fuente principal.
* El payload por pedido incluye totales y filas normalizadas.
* El payload por cliente incluye todos sus pedidos registrados y totales agregados.
* Los métodos preparatorios no realizan llamadas HTTP, webhooks ni exportaciones.

Reglas: BR-015.

## Pruebas Manuales De Fase 5

1. Abrir en administración un pedido sin canjes y confirmar el mensaje de ausencia de regalos.
2. Abrir un pedido con uno o varios rewards y comprobar la sección “Regalos canjeados”.
3. Verificar producto, SKU, cantidad, coste unitario, coste total y fecha contra `lcter_wcpl_order_rewards`.
4. Cambiar posteriormente el nombre o SKU del producto y confirmar que se conserva el snapshot registrado en el canje.
5. Confirmar que un usuario sin capacidad para editar pedidos ni `manage_woocommerce` no obtiene la sección.
6. Probar `Reward_Traceability_Service::get_rewards_by_order()` con ID válido, inválido y sin resultados.
7. Probar `get_rewards_by_customer()` con un cliente con canjes en varios pedidos.
8. Comparar `get_integration_payload_by_order()` y `get_integration_payload_by_customer()` con las filas de la tabla.
9. Confirmar que nombre, SKU y demás valores almacenados se escapan en la pantalla administrativa.
10. Confirmar que no se registra tráfico HTTP, webhook, CSV ni estado de sincronización.

## Pruebas De Seguridad

Casos:

* Acciones administrativas requieren capacidad.
* Acciones administrativas usan nonce.
* Entradas se sanitizan.
* Salidas se escapan.
* No se accede a `$_POST`, `$_GET` o `$_REQUEST` sin sanitizar.

Fuente: AGENTS.md.

## Herramientas

Herramientas documentadas:

* PHPUnit
* PHPStan
* WP Coding Standards
* PSR-12 donde sea compatible con WordPress
* Docker
* WP-CLI

## Base Automatizada De Calidad

Configuracion inicial disponible:

* `composer.json`: dependencias y scripts de calidad.
* `phpunit.xml.dist`: suite unitaria sin arrancar WordPress.
* `phpstan.neon.dist`: nivel inicial 5 con extensiones de WordPress.
* `phpcs.xml.dist`: reglas WordPress, WordPress-Extra y WordPress-Docs.
* `tests/Unit/PointsServiceTest.php`: saldo negativo, saldos anterior/posterior e idempotencia de `earned_order` y `redeemed_order`.
* `tests/Unit/InitialBonusServiceTest.php`: importe, tipo, clave idempotente y resumen de ejecuciones repetidas.
* `tests/Unit/OrderCancellationServiceTest.php`: deteccion de acumulacion, reversion total y error por saldo insuficiente.
* `tests/Unit/PointsServiceTest.php`: tambien cubre atomicidad, saldos e idempotencia de `cancelled_order`.

Comandos desde la raiz del plugin:

```bash
composer install
composer test
composer phpstan
composer phpcs
composer qa
```

La suite unitaria cubre servicios aislados. Siguen siendo necesarias pruebas de integracion en WordPress/WooCommerce para concurrencia, HPOS y hooks de pago.

## Pruebas Manuales De Estabilizacion

1. Ejecutar dos veces cada hook de pago y confirmar una sola fila `earned_order:{order_id}` y una sola `redeemed_order:{order_id}`.
2. Dejar un pedido con rewards en `pending` y confirmar que no existe descuento; no debe prepararse ni enviarse antes del pago.
3. Cambiar coste o disponibilidad antes del pago y confirmar estado `rejected` y retirada de la linea REGALO.
4. Fallar al insertar una fila de `order_rewards`; reintentar y confirmar que no se descuenta de nuevo y se completa la trazabilidad.
5. Confirmar que un invitado no acumula ni puede canjear.
6. Manipular campos en checkout clasico y confirmar revalidacion contra base de datos; un nonce invalido no debe modificar el carrito.
7. Confirmar que no se registran hooks de caducidad, REST, webhooks ni Clientify; el bonus solo dispone de accion administrativa y los reembolsos se limitan al evento total `woocommerce_order_fully_refunded`.
8. Invocar `Rewards::redeem_reward_for_order()` y `WooCommerce_Rewards_Adapter::redeem_reward_for_order()`; ambos deben emitir aviso deprecado, devolver `false` y no cambiar saldo, lineas ni `order_rewards`.
9. Crear un pedido impagado con reward y confirmar `_lcter_wcpl_reward_state=reward_selected` en pedido e item, `REGALO: PENDIENTE DE PAGO` y el aviso administrativo de no preparacion.
10. Completar el pago y confirmar que, solo tras transaccion y trazabilidad correctas, pedido e item cambian a `reward_redeemed` y `REGALO: CANJEADO`.
11. Simular `processing_error` y confirmar que el estado permanece `reward_selected`; rechazar el canje y confirmar que las lineas se retiran y el estado del pedido se elimina.

## Pruebas Manuales De Fase 6

1. Acceder al dashboard con `manage_woocommerce` y comprobar el panel "Bonus inicial de clientes".
2. Intentar enviar sin marcar la confirmacion y verificar que no se procesa ningun cliente.
3. Enviar con nonce invalido y confirmar que WordPress rechaza la accion.
4. Ejecutar con un usuario sin `manage_woocommerce` y confirmar acceso denegado.
5. Crear usuarios con rol `customer` y otros roles; ejecutar y confirmar que solo los primeros se procesan.
6. Verificar 10.000 puntos adicionales, incremento de `total_earned` y una transaccion `initial_bonus` con `initial_bonus:{customer_id}:10000` por cliente.
7. Ejecutar de nuevo y confirmar saldo sin cambios, cero bonificados y clientes existentes contabilizados como omitidos.
8. Forzar un fallo de persistencia y confirmar que se contabiliza como error sin dejar saldo sin transaccion.
9. Confirmar que el resumen muestra procesados = bonificados + omitidos + errores.
10. Confirmar que activacion, cron y frontend no ejecutan el bonus y que no se envian emails.

## Pruebas Manuales De Cancelacion Y Reembolso Total

1. Pagar un pedido registrado, confirmar su transaccion `earned` y anotar puntos y saldo.
2. Cambiarlo a `cancelled`; confirmar una transaccion `cancelled` negativa por el mismo importe, clave `cancelled_order:{order_id}` y saldos anterior/posterior correctos.
3. Repetir el estado o callback y confirmar que no cambia saldo ni crea otra transaccion.
4. Cancelar un pedido que nunca genero puntos y confirmar que no se crea movimiento `cancelled`.
5. Repetir con un pedido completamente reembolsado que alcance el estado WooCommerce `refunded`.
6. Crear un reembolso parcial sin estado total `refunded` y confirmar que esta fase no revierte puntos.
7. Reducir previamente el saldo por debajo de los puntos ganados, cancelar y confirmar saldo intacto, ausencia de transaccion `cancelled`, estado `processing_error` y nota operativa.
8. Corregir el saldo y reejecutar el hook; confirmar que la reversion se completa una sola vez.
9. Verificar `source=woocommerce_order_cancellation`, trigger en metadata y `order_id` correcto.
10. Confirmar que `total_earned` permanece como historico bruto y `total_redeemed` no cambia.

## Pruebas Manuales De Recuperacion Operativa

1. Provocar `reward_redemption_status=processing_error` y comprobar en la edicion del pedido tipo, mensaje, puntos, fecha, recomendacion y boton de reintento.
2. Reintentar una interrupcion tras descontar puntos pero antes de completar `order_rewards`; confirmar que no existe segundo descuento y solo se insertan filas ausentes.
3. Provocar una reversion con saldo insuficiente; comprobar la incidencia y reintentar sin corregir el saldo, confirmando que permanece `processing_error` y sin movimiento parcial.
4. Corregir el saldo y reintentar; confirmar una sola transaccion `cancelled_order:{order_id}` y desaparicion de la incidencia.
5. Mostrar un canje `rejected`; confirmar mensaje y recomendacion, pero ausencia de boton porque las lineas fueron retiradas.
6. Enviar el formulario sin `manage_woocommerce`, con nonce invalido, pedido distinto u operacion manipulada; confirmar que no se ejecuta ningun servicio.
7. Abrir un error antiguo sin fecha o codigo detallado y confirmar que la seccion usa mensajes de reserva sin salida insegura.
8. Repetir un formulario ya usado despues de completar la operacion y confirmar que la comprobacion de estado impide otra ejecucion.

## Requisitos De Compatibilidad A Validar

Según los ficheros actuales de documentación:

* WordPress 6.5+
* PHP 8.1+
* WooCommerce 8.0+

La cabecera del plugin, la constante `LCTER_WCPL_VERSION` y `readme.txt` coinciden en la versión `0.1.0`. La cabecera y `readme.txt` también coinciden en WooCommerce 8.0+ y probado hasta 10.0.
