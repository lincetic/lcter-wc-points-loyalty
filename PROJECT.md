# LCTER WC Points Loyalty - Project

## Resumen

LCTER WC Points Loyalty es un plugin profesional para WordPress y WooCommerce que implementa un sistema de fidelizacion basado en puntos y canje de regalos.

Los clientes acumulan puntos despues de compras pagadas y pueden canjearlos por regalos configurables durante el checkout. El plugin usa tablas propias para el saldo, el historico de movimientos, el catalogo de regalos y los regalos canjeados por pedido.

## Identidad

* Plugin: LCTER WC Points Loyalty
* Plugin URI: https://lincetic.es/
* Autor: Eddie Rapallo
* Version: 0.1.0
* Text domain: `lcter-wc-points-loyalty`
* Namespace: `LCTER_WCPL`
* Prefijo de tablas: `lcter_wcpl_`
* Licencia: GPL-2.0-or-later

## Requisitos

* WordPress 6.5+
* PHP 8.1+
* WooCommerce 8.0+
* MySQL

## Objetivo Funcional

El plugin permite:

* Acumular puntos por compras realizadas en WooCommerce.
* Sumar puntos solo cuando el pedido esta pagado.
* Calcular puntos sobre el total del pedido con IVA incluido, excluyendo portes.
* Canjear puntos por regalos.
* Anadir regalos al pedido con coste 0.
* Identificar regalos como REGALO.
* Registrar todo cambio de puntos.
* Registrar que regalos eligio cada cliente para administracion, informes e integracion con Clientify.

## Reglas Principales

* 1 centimo = 1 punto.
* 1 euro = 100 puntos.
* El pedido minimo para canje es de 60 EUR IVA incluido.
* El pedido actual no suma puntos para el saldo disponible durante su propio canje.
* El cliente puede elegir uno o varios regalos.
* El cliente puede elegir varias unidades del mismo regalo si tiene saldo suficiente.
* Nunca debe permitirse saldo negativo.
* El cliente debe poder elegir no canjear puntos.
* El bonus inicial previsto es de 10.000 puntos y debe quedar registrado como transaccion.
* Todo cambio de puntos debe crear una transaccion.

## Arquitectura

El proyecto sigue una arquitectura por capas:

* Bootstrap del plugin
* Loader
* Base de datos
* Dominio / servicios de negocio
* Integracion WooCommerce
* Administracion
* Cliente / frontend / checkout
* Integraciones externas

La logica de negocio debe mantenerse separada de HTML, persistencia y detalles de presentacion. El desarrollo debe seguir WordPress Coding Standards, PSR-12 donde sea compatible y principios SOLID.

## Base De Datos

El saldo de puntos no se guarda en postmeta. La fuente principal son tablas propias:

* `lcter_wcpl_customer_points`
* `lcter_wcpl_transactions`
* `lcter_wcpl_rewards`
* `lcter_wcpl_order_rewards`

No se debe usar:

* `product_points`
* `points_per_currency`

## Integraciones

### WooCommerce

WooCommerce aporta:

* pedidos
* productos configurables como regalos
* order items
* hooks de pedido pagado
* metadatos de pedido y order item

### Clientify

Clientify debe poder consultar o recibir los regalos elegidos por cada cliente.

Fuentes previstas:

* `lcter_wcpl_order_rewards`
* metadatos del pedido
* metadatos del order item

El mecanismo exacto de integracion con Clientify esta pendiente de definicion y debe mantenerse en `docs/open-questions.md`.

## Seguridad

Siempre usar:

* nonces
* `current_user_can()`
* `sanitize_text_field()`
* `absint()`
* `wp_unslash()`
* `esc_html()`
* `esc_attr()`
* `$wpdb->prepare()`

No acceder directamente a `$_POST`, `$_GET` o `$_REQUEST` sin sanitizar.

## Documentacion Del Proyecto

La documentacion funcional y tecnica vive en `docs/`:

* `docs/vision.md`
* `docs/architecture.md`
* `docs/business-rules.md`
* `docs/use-cases.md`
* `docs/database.md`
* `docs/domain-model.md`
* `docs/roadmap.md`
* `docs/integrations.md`
* `docs/testing.md`
* `docs/technical-decisions.md`
* `docs/open-questions.md`

Antes de implementar una funcionalidad revisar:

* `docs/business-rules.md`
* `docs/use-cases.md`
* `docs/database.md`
* `docs/domain-model.md`
* `docs/roadmap.md`

Si una regla no esta documentada, no se debe inventar. La duda debe ir a `docs/open-questions.md`.

## Estado Del Proyecto

Estado actual documentado:

* Arquitectura del plugin: iniciada
* Diseno de base de datos: definido
* Documentacion: iniciada
* Acumulacion de puntos: en desarrollo
* Canje de regalos: en desarrollo
* Integracion checkout: pendiente
* Panel de administracion: pendiente de completar
* Integracion Clientify: pendiente de definir
* Pruebas automatizadas: pendiente

## Calidad Y Herramientas

Herramientas y practicas previstas:

* WordPress Coding Standards
* PSR-12 donde sea compatible
* PHPStan
* PHPUnit
* Docker
* WP-CLI

## Criterio De Evolucion

Cada cambio funcional debe actualizar la documentacion relacionada:

* `docs/roadmap.md`
* `docs/business-rules.md`
* `docs/use-cases.md`
* `docs/database.md` si cambia la base de datos
* `docs/technical-decisions.md` si se toma una decision tecnica relevante
* `docs/open-questions.md` si aparece una duda funcional o tecnica
