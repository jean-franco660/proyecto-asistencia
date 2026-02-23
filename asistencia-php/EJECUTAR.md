# Cómo ejecutar el proyecto

## Requisitos previos

| Herramienta | Versión mínima | Cómo verificar |
|---|---|---|
| PHP | 8.1 | `php -v` |
| Composer | Cualquiera | `composer -v` |
| MySQL | 5.7 / 8.x | phpMyAdmin o MySQL CLI |

---

## Paso 1 — Configurar la base de datos

1. Abre **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Ve a la pestaña **SQL**
3. Copia y ejecuta el contenido de [`database/setup.sql`](database/setup.sql)

Esto creará la base de datos `asistencia_db`, la tabla `usuarios_web` y un usuario administrador de prueba.

**Credenciales de prueba:**
```
Email:     admin@asistencia.com
Contraseña: Admin123
```

---

## Paso 2 — Configurar el archivo `.env`

Abre el archivo `.env` en la raíz del proyecto y ajusta las credenciales de tu MySQL:

```ini
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=asistencia_db
DB_USERNAME=root
DB_PASSWORD=        ← tu contraseña de MySQL (puede estar vacía en XAMPP)
```

---

## Paso 3 — Instalar dependencias (solo la primera vez)

Abre una terminal en la carpeta del proyecto y ejecuta:

```bash
composer install
```

Esto genera la carpeta `vendor/` con el autoloader. **No toques esa carpeta.**

---

## Paso 4 — Levantar el servidor

Desde la terminal, dentro de la carpeta del proyecto:

```bash
php -S localhost:8080 public/index.php
```

Deja esa terminal abierta mientras usas el proyecto.

---

## Paso 5 — Abrir en el navegador

```
http://localhost:8080/login
```

| URL | Qué hace |
|---|---|
| `/login` | Muestra el formulario de login |
| `/dashboard` | Panel principal (requiere sesión activa) |
| `/logout` | Cierra la sesión y vuelve al login |

---

## Solución de problemas

**"No se pudo conectar a la base de datos"**
→ Verifica que MySQL esté corriendo (XAMPP → Start MySQL) y que las credenciales en `.env` sean correctas.

**"Vista no encontrada"**
→ Verifica que la carpeta `vendor/` exista. Si no, ejecuta `composer install`.

**"404 — Página no encontrada"**
→ Asegúrate de iniciar el servidor desde la raíz del proyecto, no desde `public/`.
