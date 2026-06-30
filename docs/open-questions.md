# Preguntas Abiertas

Este documento recoge aspectos no definidos en `AGENTS.md`, `docs/business-rules.md` o `docs/database.md`.

## Producto Y Negocio

1. ¿Cómo debe ejecutarse exactamente el bonus inicial de 10.000 puntos: automáticamente al activar, manual desde administración, por lote o con confirmación?
2. ¿Cómo se evita duplicar el bonus inicial si se ejecuta más de una vez?
3. ¿Qué clientes reciben el bonus inicial: todos los usuarios, solo clientes WooCommerce con pedidos, solo clientes activos u otro criterio?
4. ¿Los puntos caducan realmente o la opción de expiración queda fuera del alcance actual?
5. ¿Qué debe pasar con los puntos si un pedido pagado se cancela o se reembolsa?
6. ¿Qué debe pasar con los regalos si el pedido se cancela o se reembolsa?
7. ¿El mínimo de 60 EUR para canje se calcula sobre carrito antes o después de descuentos?
8. ¿El mínimo de 60 EUR para canje excluye portes o incluye portes?
9. ¿El catálogo trimestral de regalos se cambia manualmente o debe existir automatización por fechas?
10. ¿Debe mostrarse al cliente el coste en puntos calculado desde el precio del producto o debe guardarse manualmente?
11. ¿Deben acumular puntos los pedidos de invitado y, en ese caso, cómo se vinculan posteriormente a un `customer_id` sin duplicar saldo?

## Checkout Y Experiencia De Cliente

1. ¿En qué punto exacto del checkout se eligen los regalos?
2. ¿Cómo debe presentarse la opción "No canjear mis puntos"?
3. ¿Debe el cliente poder modificar regalos después de crear el pedido?
4. ¿Qué mensaje se muestra cuando no hay saldo suficiente?
5. ¿Qué mensaje se muestra cuando el pedido no llega al mínimo de 60 EUR?

## Administración

1. ¿Qué pantallas administrativas son necesarias además de la configuración en producto?
2. ¿Debe existir una pantalla para ver historial de transacciones por cliente?
3. ¿Debe existir una pantalla para ver regalos canjeados por pedido o por cliente?
4. ¿Quién puede hacer ajustes manuales de puntos?
5. ¿Debe existir exportación CSV?

## Clientify

1. ¿Clientify leerá datos desde una API, webhook, exportación CSV, metadatos del pedido o acceso directo a datos?
2. ¿Qué formato exacto necesita Clientify?
3. ¿Cuándo debe enviarse o sincronizarse la información?
4. ¿Qué debe ocurrir si falla una sincronización?
5. ¿Debe registrarse estado de sincronización con Clientify?

## Técnica

1. ¿El plugin debe soportar multisitio?
2. ¿Debe haber WP-CLI para bonus inicial, mantenimiento o exportaciones?
3. ¿Se requiere compatibilidad con HPOS de WooCommerce?
4. ¿Qué nivel de PHPStan se debe usar?
5. ¿Qué conjunto exacto de reglas de WP Coding Standards se aplicará?
6. ¿Cuándo y mediante qué proceso se retirarán las columnas o tablas legacy conservadas por las migraciones no destructivas?
7. ¿Debe verificarse o convertirse explícitamente a InnoDB una instalación existente que use un motor sin transacciones?
8. ¿Cómo deben afectar `refund` y `manual_adjustment` a los acumulados `total_earned` y `total_redeemed`?
