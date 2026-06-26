# Visión

LCTER WC Points Loyalty es un plugin para WordPress y WooCommerce orientado a crear un sistema de fidelización basado en puntos.

El objetivo funcional es que los clientes acumulen puntos por compras realizadas y pagadas en WooCommerce, y que posteriormente puedan canjear esos puntos por premios o regalos.

## Identidad Del Plugin

* Nombre: LCTER WC Points Loyalty.
* Plugin URI: https://lincetic.es/
* Autor: Eddie Rapallo.
* Licencia documentada: GPL-2.0-or-later (`GPL-2.0+` en cabecera PHP).
* WordPress requerido: 6.5+.
* PHP requerido: 8.1+.
* WooCommerce requerido: 8.0+.

## Objetivos

* Acumular puntos después del pago del pedido.
* Calcular puntos sobre el total del pedido con IVA incluido, excluyendo portes.
* Permitir el canje de puntos por regalos configurados como productos WooCommerce.
* Añadir los regalos al pedido con coste 0.
* Identificar claramente los regalos como REGALO.
* Evitar siempre saldos negativos.
* Registrar todo cambio de puntos como una transacción.
* Registrar qué regalos elige cada cliente para administración, informes e integración con Clientify.

## Principios

* El saldo de puntos vive en tablas propias del plugin, no en postmeta.
* La lógica de negocio debe estar separada de HTML y de detalles de presentación.
* El plugin debe ser mantenible, seguro, escalable y compatible con WooCommerce.
* No se deben introducir reglas de negocio que no estén documentadas.

## Alcance Documentado

El plugin cubre:

* Acumulación de puntos por pedidos pagados.
* Consulta de saldo de puntos por cliente.
* Catálogo de regalos canjeables.
* Canje de uno o varios regalos si hay saldo suficiente.
* Registro histórico de movimientos.
* Registro estructurado de regalos canjeados por pedido.
* Preparación de datos para Clientify.

## Fuera De Alcance Hasta Definición

Los detalles no definidos se mantienen en `docs/open-questions.md`.
