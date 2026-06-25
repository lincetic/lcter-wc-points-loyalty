=== LCTER WC Points Loyalty ===
Contributors: LCTER
Donate link: https://lcter.com/
Tags: woocommerce, loyalty, points, rewards
Requires at least: 6.9
Requires PHP: 7.2.24
Tested up to: 6.9
WC requires at least: 5.0
WC tested up to: 8.0
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sistema de puntuación por compra para clientes de WooCommerce. Los productos tienen una puntuación por precio, y los clientes ganan puntos que pueden canjear por regalos.

== Description ==

LCTER WC Points Loyalty es un plugin de WordPress y WooCommerce que permite crear un sistema completo de puntuación y recompensas para tus clientes.

**Características principales:**

* Asignación automática de puntos por cada compra basada en el monto gastado
* Configuración personalizada de puntos por producto
* Gestión de tasa de puntos global por defecto
* Panel de control para administradores con estadísticas de puntos
* Historial de transacciones de puntos para auditoría
* Interfaz de cliente para ver puntos disponibles
* Soporte para expiración de puntos (opcional)
* Notificaciones opcionales a clientes

== Installation ==

1. Sube el archivo del plugin a `/wp-content/plugins/lcter-wc-points-loyalty` o instálalo desde el repositorio de plugins de WordPress
2. Activa el plugin desde el menú "Plugins" en WordPress
3. Asegúrate de tener WooCommerce activado
4. Ve a "Points Loyalty" en el menú de administración para configurar el plugin

== Requirements ==

* **WordPress:** 6.9 o superior
* **PHP:** 7.2.24 o superior
* **WooCommerce:** 5.0 o superior

== Security ==

Este plugin sigue las mejores prácticas de seguridad de WordPress:

* Usa nonces para todas las acciones administrativas
* Valida y desinfecta todas las entradas del usuario
* Escapa todas las salidas
* Verifica capacidades antes de cualquier acción privilegiada
* Usa $wpdb->prepare() para todas las consultas de base de datos

== Configuration ==

1. **Tasa de Puntos por Defecto:** Configura cuántos puntos ganan los clientes por cada unidad de moneda gastada
2. **Expiración de Puntos:** Establece si los puntos expiran después de ciertos días (0 = nunca expiran)
3. **Notificaciones:** Habilita o deshabilita notificaciones a clientes

== Database Tables ==

El plugin crea las siguientes tablas personalizadas:

* `wp_lcter_wcpl_customer_points` - Almacena puntos de clientes
* `wp_lcter_wcpl_transactions` - Historial de transacciones de puntos
* `wp_lcter_wcpl_product_points` - Configuración de puntos por producto

== Changelog ==

= 1.0.0 =
* Versión inicial del plugin
* Sistema de puntuación automática por compra
* Panel de administración
* Gestión de configuración
* Historial de transacciones

== Frequently Asked Questions ==

= ¿Qué sucede cuando se desactiva el plugin? =
Los datos de puntos se conservan. Al reactivar el plugin, todos los datos se restauran.

= ¿Se pierden los datos al desinstalar? =
Sí, la desinstalación elimina todas las tablas personalizadas y opciones del plugin.

= ¿Puedo personalizar la tasa de puntos por producto? =
Sí, cada producto puede tener su propia tasa de puntos, o usar la tasa global por defecto.

== Support ==

Para reportar problemas o sugerir mejoras, por favor contacta a LCTER.

== License ==

Este plugin está bajo la licencia GPL-2.0+. Ver LICENSE para más detalles.
