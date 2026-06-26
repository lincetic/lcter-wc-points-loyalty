# Plan De Pruebas

## Objetivo

Validar que LCTER WC Points Loyalty cumple las reglas documentadas sin permitir saldos negativos ni canjes no trazados.

## Pruebas Unitarias

### Cálculo De Puntos

Casos:

* Pedido de 60,00 EUR sin portes genera 6.000 puntos.
* Pedido de 60,15 EUR sin portes genera 6.015 puntos.
* Los portes no suman puntos.

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

Reglas: BR-014, BR-016.

## Pruebas De Integración WooCommerce

Casos:

* Un pedido no pagado no genera puntos.
* Un pedido pagado genera puntos.
* El pedido actual no suma puntos disponibles para su propio canje.
* Un regalo se añade al pedido con coste 0.
* El order item queda identificado como REGALO.
* Se permite más de un regalo si hay saldo suficiente.
* Se permite más de una unidad del mismo regalo si hay saldo suficiente.

Reglas: BR-002, BR-006, BR-007, BR-009, BR-010.

## Pruebas De Base De Datos

Casos:

* `dbDelta()` crea las cuatro tablas.
* No se usa `product_points`.
* No se usa `points_per_currency`.
* No se crean registros huérfanos en `lcter_wcpl_order_rewards` sin pedido, cliente o producto.
* Las consultas con datos dinámicos usan `$wpdb->prepare()`.

Fuentes: AGENTS.md, database.md.

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
