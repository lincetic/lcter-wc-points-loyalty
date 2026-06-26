# Roadmap

## Fase 1 - Base Técnica

Objetivo: disponer de una estructura mantenible y segura.

Incluye:

* Bootstrap del plugin.
* Loader y separación inicial de clases.
* Activación y desactivación.
* Tablas propias del plugin.
* Convenciones de namespace, prefijo de tablas y textdomain.

## Fase 2 - Modelo De Puntos

Objetivo: permitir acumulación y consulta de saldo.

Incluye:

* Tabla `lcter_wcpl_customer_points`.
* Tabla `lcter_wcpl_transactions`.
* Cálculo de puntos tras pedido pagado.
* Registro de transacciones `earned`.
* Protección contra saldo negativo.

## Fase 3 - Catálogo De Regalos

Objetivo: permitir configurar productos WooCommerce como regalos.

Incluye:

* Tabla `lcter_wcpl_rewards`.
* Configuración de coste en puntos.
* Activación, orden y fechas de disponibilidad.
* Catálogo limitado aproximadamente a 10-12 productos.

## Fase 4 - Canje En Checkout / Pedido

Objetivo: permitir que el cliente canjee puntos por regalos.

Incluye:

* Validación de pedido mínimo de 60 EUR IVA incluido.
* Opción de no canjear puntos.
* Selección de uno o varios regalos.
* Selección de varias unidades si hay saldo suficiente.
* Añadir regalos al pedido con coste 0.
* Identificar regalos como REGALO.
* Registrar transacciones `redeemed`.

## Fase 5 - Trazabilidad E Integración

Objetivo: dejar los canjes preparados para administración, informes y Clientify.

Incluye:

* Tabla `lcter_wcpl_order_rewards`.
* Metadatos en pedido y order item.
* Consulta de regalos por pedido.
* Consulta de regalos por cliente.

## Fase 6 - Bonus Inicial

Objetivo: registrar el saldo inicial documentado.

Incluye:

* Proceso para añadir 10.000 puntos iniciales.
* Transacciones de tipo `initial_bonus`.
* Evitar duplicados según criterio pendiente de definir.

## Fase 7 - Calidad

Objetivo: consolidar el plugin para mantenimiento.

Incluye:

* PHPUnit.
* PHPStan.
* WP Coding Standards.
* PSR-12 donde sea compatible con WordPress.
* Pruebas de integración con WooCommerce.
* Revisión de seguridad.
* Entorno recomendado con Docker y WP-CLI.
