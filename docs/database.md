# Base de datos

El plugin utilizará tablas propias con prefijo dinámico de WordPress.

Ejemplo:

wp_lcter_wcpl_customer_points

## 1. lcter_wcpl_customer_points

Guarda el saldo actual de cada cliente.

Campos:

* id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
* customer_id BIGINT UNSIGNED NOT NULL
* balance INT NOT NULL DEFAULT 0
* total_earned INT NOT NULL DEFAULT 0
* total_redeemed INT NOT NULL DEFAULT 0
* created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
* updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

Índices:

* UNIQUE KEY customer_id (customer_id)
* KEY updated_at (updated_at)

---

## 2. lcter_wcpl_transactions

Guarda el histórico de movimientos de puntos.

Campos:

* id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
* customer_id BIGINT UNSIGNED NOT NULL
* order_id BIGINT UNSIGNED NULL
* order_item_id BIGINT UNSIGNED NULL
* type VARCHAR(50) NOT NULL
* points INT NOT NULL
* balance_before INT NOT NULL DEFAULT 0
* balance_after INT NOT NULL DEFAULT 0
* source VARCHAR(50) NULL
* description LONGTEXT NULL
* metadata LONGTEXT NULL
* created_by BIGINT UNSIGNED NULL
* idempotency_key VARCHAR(191) NULL
* created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

Índices:

* KEY customer_id (customer_id)
* KEY order_id (order_id)
* KEY order_item_id (order_item_id)
* KEY type (type)
* KEY source (source)
* KEY created_at (created_at)
* UNIQUE KEY idempotency_key (idempotency_key)

Tipos:

* earned
* redeemed
* initial_bonus
* manual_adjustment
* refund
* cancelled

---

## 3. lcter_wcpl_rewards

Guarda los regalos/premios disponibles para canje.

Campos:

* id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
* product_id BIGINT UNSIGNED NOT NULL
* points_cost INT NOT NULL
* active TINYINT(1) NOT NULL DEFAULT 1
* sort_order INT NOT NULL DEFAULT 0
* starts_at DATETIME NULL
* ends_at DATETIME NULL
* created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
* updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

Índices:

* UNIQUE KEY product_id (product_id)
* KEY active (active)
* KEY starts_at (starts_at)
* KEY ends_at (ends_at)
* KEY sort_order (sort_order)

---

## 4. lcter_wcpl_order_rewards

Guarda los regalos canjeados en cada pedido.

Esta tabla es importante para administración, informes e integración con Clientify.

Campos:

* id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
* order_id BIGINT UNSIGNED NOT NULL
* customer_id BIGINT UNSIGNED NOT NULL
* product_id BIGINT UNSIGNED NOT NULL
* order_item_id BIGINT UNSIGNED NULL
* reward_id BIGINT UNSIGNED NULL
* quantity INT NOT NULL DEFAULT 1
* points_cost_each INT NOT NULL
* points_cost_total INT NOT NULL
* product_name VARCHAR(255) NULL
* sku VARCHAR(100) NULL
* idempotency_key VARCHAR(191) NULL
* created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

Índices:

* KEY order_id (order_id)
* KEY customer_id (customer_id)
* KEY product_id (product_id)
* KEY order_item_id (order_item_id)
* KEY reward_id (reward_id)
* KEY created_at (created_at)
* UNIQUE KEY idempotency_key (idempotency_key)

---

## Notas técnicas

* No usar product_points.
* No usar points_per_currency.
* La versión actual del esquema es `1.2.0` y se guarda en la opción `lcter_wcpl_schema_version`.
* Las instalaciones y actualizaciones usan `dbDelta()` y migraciones no destructivas.
* Las tablas nuevas se declaran con motor InnoDB para soportar bloqueo de filas y transacciones.
* Las columnas y tablas legacy no se eliminan automáticamente. Si existe la columna legacy `points`, se copia a `balance` cuando este todavía es cero.
* La acumulación usa la clave `earned_order:{order_id}`. El índice único evita duplicados sin impedir otros tipos de movimiento para el mismo pedido.
* También se consulta `order_id` junto con `type` para reconocer acumulaciones anteriores a `idempotency_key`.
* El canje usa `redeemed_order:{order_id}` en transacciones y `redeemed_order:{order_id}:reward:{reward_id}` en regalos de pedido.
* La clave única de `order_rewards` permite reintentar un canje parcialmente registrado sin duplicar filas.
* El cálculo de puntos depende del total del pedido, no del producto.
* Los regalos son productos WooCommerce configurados como rewards.
* Los regalos canjeados también deben guardarse como metadatos del pedido o del order item si es necesario para Clientify.
