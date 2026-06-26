# Arquitectura

LCTER WC Points Loyalty se organiza por capas para separar responsabilidades y evitar clases enormes o lógica mezclada con HTML.

La arquitectura se alinea con `README.md`: separación limpia entre lógica de negocio e infraestructura, WordPress Coding Standards, PSR-12 donde sea compatible y principios SOLID.

## Requisitos Técnicos Documentados

* WordPress 6.5+.
* PHP 8.1+.
* WooCommerce 8.0+.
* MySQL.
* WordPress Coding Standards.
* PHPStan.
* PHPUnit.
* Docker y WP-CLI como entorno recomendado.

## Capas

### Bootstrap

Archivo principal del plugin:

* `lcter-wc-points-loyalty.php`

Responsabilidades:

* Declarar metadatos del plugin.
* Definir constantes.
* Cargar el loader.
* Registrar hooks de activación y desactivación.
* Comprobar que WooCommerce está activo.

### Loader

Responsabilidades:

* Cargar clases del plugin.
* Registrar hooks generales.
* Inicializar componentes.

### Base De Datos

Responsabilidades:

* Crear y actualizar tablas propias con `dbDelta()`.
* Encapsular lecturas y escrituras SQL.
* Mantener consultas dinámicas con `$wpdb->prepare()`.
* Evitar que el saldo principal dependa de postmeta.

Tablas:

* `lcter_wcpl_customer_points`
* `lcter_wcpl_transactions`
* `lcter_wcpl_rewards`
* `lcter_wcpl_order_rewards`

### Dominio

Responsabilidades:

* Aplicar reglas de puntos.
* Validar saldo suficiente.
* Evitar saldo negativo.
* Registrar transacciones.
* Gestionar regalos disponibles y regalos canjeados.

Esta capa debe concentrar reglas de negocio y mantenerse separada de persistencia, HTML y detalles de WooCommerce siempre que sea posible.

### WooCommerce

Responsabilidades:

* Escuchar eventos de pedido pagado.
* Calcular puntos acumulables a partir del pedido.
* Añadir regalos al pedido con coste 0.
* Guardar metadatos de pedido y order item para identificar regalos.

### Administración

Responsabilidades:

* Configurar opciones del plugin.
* Configurar productos WooCommerce como regalos canjeables.
* Mostrar información operativa del sistema.

### Cliente / Frontend / Checkout

Responsabilidades:

* Mostrar saldo de puntos.
* Permitir al cliente elegir regalos o no canjear puntos.
* Respetar el pedido mínimo de 60 EUR IVA incluido para canje.

### Integraciones Externas

Responsabilidades:

* Exponer o preparar información para Clientify.
* Usar `lcter_wcpl_order_rewards`, metadatos del pedido o metadatos del order item como fuente de regalos elegidos.

## Flujo Principal

1. WooCommerce marca un pedido como pagado.
2. El plugin calcula puntos según las reglas documentadas.
3. El saldo del cliente se actualiza en `lcter_wcpl_customer_points`.
4. Se registra una transacción en `lcter_wcpl_transactions`.
5. Durante un canje, se valida pedido mínimo y saldo disponible.
6. El regalo se añade al pedido con coste 0.
7. El canje se registra como transacción y como fila en `lcter_wcpl_order_rewards`.
8. El pedido y el order item reciben metadatos para identificar el regalo.
