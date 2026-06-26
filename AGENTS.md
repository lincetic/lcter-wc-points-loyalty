# LCTER WC Points Loyalty

## Objetivo del proyecto

Desarrollar un plugin profesional para WordPress y WooCommerce que permita crear un sistema de fidelización basado en puntos.

Los clientes acumulan puntos en función de sus compras y posteriormente pueden canjearlos por premios o regalos.

El plugin debe ser mantenible, seguro, escalable y compatible con WooCommerce.

---

## Reglas generales para Codex

Antes de implementar cualquier funcionalidad, revisar siempre:

* docs/business-rules.md
* docs/use-cases.md
* docs/database.md
* docs/domain-model.md
* docs/roadmap.md

No inventar reglas de negocio no documentadas.

Si hay una duda funcional, añadirla a:

* docs/open-questions.md

---

## Tecnología

* WordPress
* WooCommerce 8.0+
* PHP 8.1+
* MySQL
* WP Coding Standards
* PSR-12 donde sea compatible con WordPress
* PHPStan
* PHPUnit
* Docker
* WP-CLI

---

## Metadatos del plugin

Plugin:

LCTER WC Points Loyalty

Plugin URI:

https://lincetic.es/

Autor:

Eddie Rapallo

Licencia:

GPL-2.0-or-later

Requisitos documentados:

* WordPress 6.5+
* PHP 8.1+
* WooCommerce 8.0+

Nota:

Si la cabecera del plugin, `readme.txt` y `README.md` no coinciden en version o requisitos, no asumir el valor correcto. Añadir la discrepancia a `docs/open-questions.md`.

---

## Convenciones

Namespace actual:

LCTER_WCPL

Prefijo de tablas:

lcter_wcpl_

Textdomain:

lcter-wc-points-loyalty

---

## Arquitectura

Separar responsabilidades:

* Base de datos
* Dominio
* WooCommerce
* Administración
* Cliente/frontend
* Checkout
* Integraciones externas

No mezclar lógica de negocio con HTML.

No meter toda la lógica en una única clase.

Evitar clases enormes.

Seguir principios SOLID cuando no entren en conflicto con patrones WordPress.

---

## Seguridad

Siempre usar:

* nonces
* current_user_can()
* sanitize_text_field()
* absint()
* wp_unslash()
* esc_html()
* esc_attr()
* $wpdb->prepare()

No acceder directamente a $_POST, $_GET o $_REQUEST sin sanitizar.

---

## Base de datos

El plugin usará tablas propias.

Tablas principales:

* lcter_wcpl_customer_points
* lcter_wcpl_transactions
* lcter_wcpl_rewards
* lcter_wcpl_order_rewards

No usar postmeta como fuente principal del saldo de puntos.

---

## Documentación

Cada vez que se implemente una funcionalidad, actualizar:

* docs/roadmap.md
* docs/business-rules.md
* docs/use-cases.md
* docs/database.md si cambia la BD
* docs/technical-decisions.md si se toma una decisión importante

---

## Reglas importantes

* Los puntos se generan después del pago.
* Nunca se suman antes de que el pedido esté pagado.
* El pedido actual no cuenta para calcular el saldo disponible en el canje.
* Nunca permitir saldo negativo.
* Los regalos se añaden al pedido con coste 0.
* Los regalos deben identificarse como REGALO.
* Clientify debe poder consultar qué regalos eligió cada cliente.
