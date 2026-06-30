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

## Estructura Implementada

La implementación separa físicamente y por responsabilidad:

* `includes/repositories/`: acceso SQL a `customer_points`, `transactions`, `rewards` y `order_rewards`, más el límite técnico de transacciones.
* `includes/services/`: coordinación de casos de aplicación y reglas que no dependen de objetos WooCommerce.
* `includes/adapters/`: traducción de pedidos, productos y order items WooCommerce.
* `includes/class-admin.php`: capa administrativa, inicializada únicamente mediante `admin_init` y consumidora de servicios.

`Database` queda limitado a nombres de tabla, instalación, versión y migración de esquema. Las clases públicas `Points`, `Points_Service`, `Rewards` y `WooCommerce` se conservan como fachadas de compatibilidad.

El sentido de las dependencias es:

1. Administración, frontend y adaptadores llaman a servicios.
2. Los servicios coordinan repositorios.
3. Los repositorios encapsulan SQL y usan `Database` solo para resolver nombres de tablas.
4. Los objetos WooCommerce no entran en los repositorios ni en los servicios de saldo.

Las sumas y canjes bloquean la fila del cliente con `SELECT ... FOR UPDATE`. La actualización del saldo y la inserción de su transacción se confirman o revierten juntas. La acumulación por pedido añade una clave de idempotencia única.

El loader ejecuta `Database::maybe_upgrade()` para aplicar cambios de esquema cuando cambia la versión almacenada, incluso si una actualización no reactiva el plugin.

## Flujo Principal

1. WooCommerce marca un pedido como pagado.
2. El plugin calcula puntos según las reglas documentadas.
3. El saldo del cliente se actualiza en `lcter_wcpl_customer_points`.
4. Se registra una transacción en `lcter_wcpl_transactions`.
5. Durante un canje, se valida pedido mínimo y saldo disponible.
6. El regalo se añade al pedido con coste 0.
7. El canje se registra como transacción y como fila en `lcter_wcpl_order_rewards`.
8. El pedido y el order item reciben metadatos para identificar el regalo.
