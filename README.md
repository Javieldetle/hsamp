# HSAMP - Sistema de Reservas Hoteleras

Proyecto final de grado desarrollado en PHP + MySQL (phpMyAdmin), Apache (XAMPP) y Bootstrap.

## Estructura del proyecto

- `index.php` y raíz `hsamp/`: zona pública y cliente.
- `admin/`: panel de administración.
- `recep/`: panel de recepción.
- `database/hsamp.sql`: exportación de base de datos.

## Requisitos

- XAMPP (Apache + MySQL)
- PHP 8.x
- MySQL / MariaDB
- Navegador web moderno

## Instalación rápida

1. Copiar el proyecto en:
   - `C:\xampp\htdocs\hsamp`
2. Iniciar Apache y MySQL desde XAMPP.
3. Crear base de datos `hsamp` en phpMyAdmin.
4. Importar el archivo:
   - `database/hsamp.sql`
5. Verificar conexión en los archivos de configuración de BD (`root` sin clave en entorno local, según configuración actual del proyecto).

## Ejecución

- Web principal: `http://localhost/hsamp/`
- Panel admin: `http://localhost/hsamp/admin/`
- Panel recepción: `http://localhost/hsamp/recep/`

## Pagos Stripe

El proyecto usa Stripe para pagos con tarjeta.

Las claves deben configurarse mediante variables de entorno:

- `STRIPE_PUBLIC_KEY`
- `STRIPE_SECRET_KEY`

Si no están definidas, el sistema usa placeholders en los archivos de configuración.

## Notas

- Este repositorio incluye código fuente y estructura completa del proyecto.
- La base de datos está incluida en `database/hsamp.sql` para facilitar despliegue en otro equipo.

## Autor

Javier Detlefsen Sampedro
