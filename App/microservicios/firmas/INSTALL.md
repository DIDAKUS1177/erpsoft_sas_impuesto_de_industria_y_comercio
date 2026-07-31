# Microservicio Firmas Digitales — Guía de instalación

## Requisitos previos

- PHP 7.4+
- SQL Server (base de datos `erpsoftweb`)
- Proyecto `erpsoftsas` desplegado y funcionando
- Cuenta de Gmail con **contraseña de aplicación** habilitada (no la contraseña normal)

---

## Pasos de instalación

### 1. Copiar la carpeta

Copia la carpeta `microservicios/firmas/` dentro del proyecto en la misma ruta:

```
/erpsoftsas/microservicios/firmas/
```

> **Importante:** el proyecto debe estar en una carpeta llamada `erpsoftsas` dentro del DocumentRoot del servidor. Si tienes un nombre diferente, ajusta la línea en `business/globals.php`:
> ```php
> define('SERVER', $_SERVER['DOCUMENT_ROOT']."/erpsoftsas");
> ```

---

### 2. Ejecutar las migraciones SQL

Abre SQL Server Management Studio, conéctate a la base de datos `erpsoftweb` y ejecuta el archivo:

```
microservicios/firmas/sql_migraciones.sql
```

Esto crea las 4 tablas necesarias (solo si no existen):
- `firmas` — firmas por establecimiento (RIT)
- `codigos_verificacion` — códigos OTP
- `firmas_usuario` — firma personal de cada usuario
- `firmas_declaraciones` — registro de declaraciones firmadas

---

### 3. Configurar el correo

Edita el archivo `microservicios/firmas/config.php` con los datos del correo que enviará los OTP:

```php
'smtp_user'     => 'tucorreo@gmail.com',       // tu cuenta Gmail
'smtp_password' => 'xxxx xxxx xxxx xxxx',      // contraseña de app (16 caracteres)
'from_name'     => 'Alcaldía de Tu Municipio', // nombre que verá el usuario
```

**Cómo obtener una contraseña de aplicación en Gmail:**
1. Entra a tu cuenta Google → Seguridad
2. Activa la verificación en dos pasos (si no la tienes)
3. Ve a "Contraseñas de aplicaciones"
4. Genera una para "Correo" / "Otro (nombre personalizado)"
5. Copia las 16 letras y pégalas en `smtp_password`

---

### 4. Verificar dependencias JS en las páginas que usen el módulo

Las páginas que incluyan los modales (`modal.php`, `modalDeclaracion.php`, `modalFirmaUsuario.php`) deben cargar **antes** de los scripts del módulo:

| Librería | Uso |
|---|---|
| jQuery 3.x | `$.ajax`, manipulación del DOM |
| Bootstrap 4 | `.modal('show')` |
| SweetAlert2 (o SweetAlert) | `swal(...)` para alertas |
| `signature_pad.min.js` | Canvas de dibujo de firma |

Ejemplo de carga en la página:
```html
<script src="ruta/jquery.min.js"></script>
<script src="ruta/bootstrap.bundle.min.js"></script>
<script src="ruta/sweetalert2.min.js"></script>
<script src="ruta/signature_pad.min.js"></script>

<!-- Scripts del módulo firmas -->
<script src="../microservicios/firmas/firmas.js"></script>
<script src="../microservicios/firmas/firmaDeclaracion.js"></script>
```

> Los paths `../microservicios/firmas/` asumen que la página está en la carpeta `dist/`. Si la página está en otra ubicación, ajusta el path.

---

## Archivos del módulo y su función

| Archivo | Descripción |
|---|---|
| `api.php` | Endpoint POST que maneja las 8 funciones del módulo |
| `config.php` | **Configuración del correo — editar antes de activar** |
| `getFirma.php` | Helpers PHP para consultar firmas desde otros archivos |
| `overlayFirma.php` | Helper para incrustar firma en PDFs generados con TCPDF |
| `modal.php` | Modal HTML para firma de establecimiento/RIT |
| `modalDeclaracion.php` | Modal HTML para firma de declaraciones |
| `modalFirmaUsuario.php` | Modal HTML para firma personal del usuario |
| `firmas.js` | Lógica JS del modal de firma de establecimiento |
| `firmaDeclaracion.js` | Lógica JS del modal de firma de declaraciones |
| `perfilFirma.js` | Lógica JS del modal de firma personal (perfil) |
| `sql_migraciones.sql` | Script SQL con las 4 tablas — ejecutar una sola vez |
| `demo.html` | Demo standalone del flujo (no requiere servidor) |

---

## Cómo usar el módulo desde otras páginas

### Firma de establecimiento (RIT)
```php
<?php include '../microservicios/firmas/modal.php'; ?>
<script src="../microservicios/firmas/firmas.js"></script>
```
```javascript
// Abrir modal para el establecimiento con id 42
firmas.abrirModal(42);
```

### Firma de declaración
```php
<?php include '../microservicios/firmas/modalDeclaracion.php'; ?>
<script src="../microservicios/firmas/firmaDeclaracion.js"></script>
```
```javascript
firmaDeclaracion.abrirModal('2024000797', function(numero) {
    // callback cuando la declaración queda firmada
    console.log('Firmada:', numero);
});
```

### Firma personal del usuario (perfil)
```php
<?php include '../microservicios/firmas/modalFirmaUsuario.php'; ?>
<script src="../microservicios/firmas/perfilFirma.js"></script>
```
```javascript
perfilFirma.abrirModal(idUsuario);
```

### Incrustar firma en PDF TCPDF
```php
require_once __DIR__ . '/../microservicios/firmas/getFirma.php';
require_once __DIR__ . '/../microservicios/firmas/overlayFirma.php';

$firmaBase64 = firmas_getBase64($idEstablecimiento);
firmas_overlayPdf($pdf, $firmaBase64, $sigY);
```

---

## Flujo OTP por tipo de firma

| Tipo | `id_establecimiento` en OTP |
|---|---|
| Firma RIT (establecimiento) | ID del establecimiento (> 0) |
| Firma de declaración | `0` |
| Firma personal (perfil) | `-1` |
