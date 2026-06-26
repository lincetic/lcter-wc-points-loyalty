# Integraciones

## WooCommerce

WooCommerce es la integración principal del plugin.

### Pedidos

El plugin usa pedidos WooCommerce para:

* Detectar cuándo un pedido está pagado.
* Calcular puntos acumulados.
* Añadir regalos al pedido.
* Guardar metadatos de regalos.

### Cálculo De Puntos

Los puntos se calculan sobre:

* total del pedido con IVA incluido
* excluyendo portes

Equivalencia:

* 1 céntimo = 1 punto
* 1 euro = 100 puntos

### Productos

Los regalos son productos WooCommerce configurados como rewards.

El cálculo de puntos acumulados no depende del producto.

### Order Items

Los regalos canjeados deben:

* añadirse con coste 0
* identificarse como REGALO
* guardar metadatos suficientes para administración e integraciones

## Clientify

Clientify debe poder consultar o recibir qué regalos eligió cada cliente.

Fuentes preparadas:

* `lcter_wcpl_order_rewards`
* metadatos del pedido
* metadatos del order item

Datos disponibles para integración:

* pedido
* cliente
* producto
* order item WooCommerce
* reward
* cantidad
* coste en puntos unitario
* coste en puntos total
* nombre del producto
* SKU
* fecha de registro

## Pendiente De Definir

No está documentado todavía:

* Si Clientify leerá datos mediante API, exportación, webhook o sincronización manual.
* Formato exacto esperado por Clientify.
* Momento exacto de envío o consulta.
* Reintentos, errores y auditoría de sincronización.

Estas dudas están recogidas en `docs/open-questions.md`.
