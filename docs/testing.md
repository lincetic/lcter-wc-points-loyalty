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

## Pruebas De Clientify

Casos:

* Un canje crea fila en `lcter_wcpl_order_rewards`.
* Un canje guarda metadatos en pedido u order item.
* Se pueden consultar regalos por pedido.
* Se pueden consultar regalos por cliente.

Reglas: BR-015.

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

## Requisitos De Compatibilidad A Validar

Según los ficheros actuales de documentación:

* WordPress 6.5+
* PHP 8.1+
* WooCommerce 8.0+

La cabecera del plugin, la constante `LCTER_WCPL_VERSION` y `readme.txt` coinciden en la versión `0.1.0`. La cabecera y `readme.txt` también coinciden en WooCommerce 8.0+ y probado hasta 10.0.
