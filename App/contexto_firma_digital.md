# Contexto del Módulo de Firma Digital (Erpsoftsas)

Este documento sirve para proporcionar contexto a Claude (o cualquier otro asistente/desarrollador) sobre el estado actual, arquitectura y soluciones implementadas en el módulo de **Firma Digital de Declaraciones ICA**.

## 1. Arquitectura del Flujo de Firma Digital
El proceso de firma digital se basa en una validación por **OTP (One Time Password)** enviado al correo del usuario, seguido de la vinculación de la firma en la base de datos y su posterior visualización en un documento PDF generado dinámicamente.

### Pasos del Flujo:
1. **Generación de OTP (`funcion: 1`):** El frontend solicita al backend generar un código de 6 dígitos que se envía al correo registrado del usuario (usando PHPMailer).
2. **Validación de OTP (`funcion: 2`):** El usuario ingresa el código en el modal de Firma Digital. El backend verifica que el código sea correcto y no haya expirado.
3. **Firma de la Declaración (`funcion: 7`):** Si el OTP es válido, el frontend lanza inmediatamente una petición para firmar. El backend registra la acción en la tabla `firmas_declaraciones`.
4. **Visualización en PDF:** Al descargar o ver el PDF de la declaración, el sistema extrae los datos de la firma y los plasma al final del documento.

## 2. Archivos Clave del Módulo

*   **Frontend (JS):**
    *   `Firma_digital/erpsoftsas/core/icaWebConsultar.js` (y `icaWebPresentar.js`): Controlan la apertura del modal `#modal-FirmaDigital`. Contienen las llamadas AJAX hacia la API para las funciones 1, 2 y 7 (OTP y firma).
*   **Backend API (PHP):**
    *   `Firma_digital/erpsoftsas/microservicios/firmas/api.php`: Es el core del módulo. Se encarga de procesar las peticiones AJAX.
        *   `_generarCodigo()`: Envía el correo.
        *   `_verificarCodigo()`: Valida el OTP contra la base de datos.
        *   `_firmarDeclaracion()`: Guarda la firma en `firmas_declaraciones`.
*   **Generación del PDF:**
    *   `Firma_digital/erpsoftsas/extensiones/declaracion.php`: Utiliza la librería **TCPDF**. Se modificó para realizar un `LEFT JOIN` con `firmas_declaraciones` y `firmas_usuario`, extrayendo la información de la firma (Nombre, Email, Fecha/Hora, Imagen Base64) y dibujándola en el recuadro "FIRMA DEL DECLARANTE".

## 3. Tablas de Base de Datos Relacionadas (SQL Server)
*   `codigos_verificacion`: Almacena el OTP generado, relacionándolo con el usuario y controlando su expiración.
*   `firmas_declaraciones`: Almacena la firma electrónica de una declaración específica (`fd_NumeroDeclaracion` guarda el `dec_Id`). Guarda usuario, nombre, email y `fd_FechaHora`.
*   `firmas_usuario`: Almacena la configuración personal de firma de un usuario, incluyendo la imagen de la firma en formato Base64 (`fu_Base64`).

## 4. Problemas Resueltos y Detalles Técnicos Importantes

*   **Pérdida de Sesión (ID_USUARIO = 0):**
    *   *Problema:* El frontend mandaba `id_usuario: 0` al backend porque los scripts PHP (`icaWebConsultar.php`, `icaWebPresentar.php`, `dashboard.php`) no estaban inicializando la sesión al cargarse.
    *   *Solución:* Se agregó `session_start();` explícitamente en la cabecera de estos archivos PHP (protegido con `if (session_status() === PHP_SESSION_NONE)`) asegurando que el ID del usuario sobreviva al flujo de trabajo tras el login en `class.login.php`.
*   **Mismatches de Nombres de Parámetros:**
    *   *Problema:* El frontend en JS enviaba el identificador bajo la variable `id_declaracion`, pero `api.php` en `_firmarDeclaracion` esperaba `numero_declaracion`.
    *   *Solución:* Se adaptó `api.php` para que reciba adecuadamente el identificador `$_POST['id_declaracion']`.
*   **Error Fatal (DateTime) en TCPDF:**
    *   *Problema:* Al intentar renderizar el PDF (`declaracion.php`), el servidor lanzaba el error: `Object of class DateTime could not be converted to string`.
    *   *Solución:* El driver de SQL Server para PHP retorna las columnas `DATETIME` como objetos `DateTime`. Se implementó un formateo explícito (`$firmaData['fd_FechaHora']->format('Y-m-d H:i:s')`) antes de concatenar la fecha en el HTML del PDF.

## 5. Próximos Pasos (Opcionales / Futuros)
*   **Botón de Edición:** En la vista de "Presentar Declaración", los botones de la tabla principal de establecimientos se han dejado como visuales (`href="#"` o deshabilitados) en favor de que las acciones se realicen en la ventana modal de "Consultar Declaraciones". Se tiene programado habilitar la función de edición en un futuro (actualmente muestra un SweetAlert informativo).
*   **Gestión de Imagen de Firma:** Verificar y probar el módulo (si existe) mediante el cual el usuario puede cargar o dibujar su firma gráfica y convertirla a Base64 para guardarla en `firmas_usuario`.
