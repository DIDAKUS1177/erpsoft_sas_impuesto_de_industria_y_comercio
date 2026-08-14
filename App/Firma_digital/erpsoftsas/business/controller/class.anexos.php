<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.sessions.php';

/**
 * Anexos de los establecimientos (puntos 17 y 18).
 *
 * La carga de archivos no existia en el modulo: est_Pdf1..4 estaba dentro de
 * un bloque comentado, el PDF de cese solo se validaba, y no habia FormData ni
 * $_FILES en ningun lado. Esto lo implementa desde cero.
 *
 * Decisiones que NO son cosmeticas:
 *
 *  - El nombre del archivo en disco lo genera el servidor. Nunca se usa el que
 *    manda el navegador: con el se puede escribir fuera de la carpeta
 *    (../../) o dejar un .php que despues alguien ejecuta pidiendolo por URL.
 *  - Solo se aceptan extensiones de una lista corta, y se comprueba ademas el
 *    tipo real del contenido con finfo. Un .pdf puede traer cualquier cosa.
 *  - Los archivos se sirven por un script que valida de quien son
 *    (extensiones/anexo.php). La carpeta se coloca un nivel arriba de la app,
 *    que en Plesk queda fuera del docroot, PERO eso depende del despliegue: en
 *    el contenedor local ese mismo nivel SI se sirve por HTTP (se comprobo
 *    pidiendo el archivo por URL y respondio 200). Por eso la proteccion no se
 *    deja en manos de donde este la carpeta: al crearla se escribe un
 *    .htaccess que niega todo acceso directo.
 *    Aviso para el despliegue: .htaccess solo lo respeta Apache. Si algun dia
 *    el sitio pasa a servirse solo con nginx, hay que negar esa ruta en su
 *    configuracion o mover la carpeta fuera del docroot.
 *  - Toda consulta va parametrizada (el DAO de este proyecto concatena, ver
 *    CLAUDE.md).
 */
class ControladorAnexos extends \erpsoftsas\Cabecera
{
    private $_funcion;
    private $_ok = 0;
    private $_mensaje = '';

    /** Extensiones aceptadas y el tipo real que debe traer el contenido. */
    const TIPOS_PERMITIDOS = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];

    const TAMANO_MAXIMO = 10485760; // 10 MB

    public static function run()
    {
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'] ?? null;

        try {
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_subir();
                    break;
                case 2:
                    $respuesta = $_obj->_listar();
                    break;
                case 3:
                    $respuesta = $_obj->_eliminar();
                    break;
                default:
                    throw new \erpsoftsas\AnexosException("Función no válida", 0);
            }

            header('Content-type: application/json');
            echo json_encode([
                "ok"      => $_obj->_ok,
                "mensaje" => $_obj->_mensaje,
                "datos"   => $respuesta,
            ]);

        } catch (\erpsoftsas\AnexosException $e) {
            header('Content-type: application/json');
            echo json_encode([
                "ok"      => 0,
                "mensaje" => "Error: " . $e->getMessage(),
                "datos"   => "",
            ]);
        }
    }

    /**
     * Carpeta donde viven los anexos.
     *
     * SERVER apunta a .../erpsoftsas; se sube un nivel, que es el mismo sitio
     * donde vive config.municipio.php en produccion (ver CLAUDE.md). En Plesk
     * ese nivel queda fuera del docroot, pero en el contenedor local NO: ahi
     * se sirve por HTTP. De ahi que el blindaje real sea blindarCarpeta(), no
     * la ubicacion.
     */
    public static function carpetaBase()
    {
        return dirname(SERVER) . '/anexos_establecimientos';
    }

    /**
     * Niega el acceso directo por HTTP a la carpeta de anexos.
     *
     * Hace falta porque la carpeta no siempre queda fuera del docroot: en el
     * contenedor local se comprobo que un anexo era descargable por URL, sin
     * sesion, solo conociendo el nombre. La unica via legitima es
     * extensiones/anexo.php, que si verifica de quien es el archivo.
     */
    /**
     * Devuelve true solo si el .htaccess quedo escrito (ya existia o se
     * acaba de crear). Antes el @ tragaba cualquier fallo de escritura sin
     * que nadie se enterara -ni un log, ni un aviso-, y _subir() seguia
     * aceptando el archivo igual: si el permiso de Plesk no alcanzaba para
     * escribir fuera del docroot de la app, cada subida quedaba
     * silenciosamente sin blindar, dejando el documento (RUT, Camara de
     * Comercio) alcanzable por URL directa sin sesion -el mismo problema que
     * este blindaje existe para evitar-.
     */
    public static function blindarCarpeta()
    {
        $base = self::carpetaBase();
        if (!is_dir($base)) { return false; }

        $htaccess = $base . '/.htaccess';
        if (is_file($htaccess)) { return true; }

        // 'Require all denied' es Apache 2.4; las dos lineas de Order/Deny son
        // para 2.2. Tener las dos no estorba: cada version ignora la ajena.
        $escrito = @file_put_contents($htaccess,
            "# Los anexos solo se entregan por extensiones/anexo.php, que\n" .
            "# comprueba la sesion y el dueño del archivo.\n" .
            "<IfModule mod_authz_core.c>\n" .
            "    Require all denied\n" .
            "</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n" .
            "    Order allow,deny\n" .
            "    Deny from all\n" .
            "</IfModule>\n"
        );

        if ($escrito === false) {
            error_log("ANEXOS: no se pudo escribir .htaccess en $base -carpeta de anexos SIN blindar-.");
            return false;
        }

        return true;
    }

    private function _idUsuario()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
        return isset($_SESSION['id_usuario']) ? (int) $_SESSION['id_usuario'] : null;
    }

    /**
     * ¿Puede la sesión actual operar sobre este establecimiento?
     *
     * Esta comprobación SOLO existía en extensiones/anexo.php (para VER un
     * archivo ya cargado). Subir, listar y borrar no tenían ninguna: sin
     * este método, cualquiera -sin sesión siquiera- podía subir archivos a
     * cualquier establecimiento, ver los nombres de archivo de cualquiera, o
     * retirar (borrado lógico) los anexos de cualquiera con solo adivinar un
     * anx_Id consecutivo. Se centraliza aquí para que las cuatro vías
     * (subir, listar, eliminar, ver) usen la misma regla y no se desalineen
     * con el tiempo, como ya pasó una vez con NumerosCOP.
     */
    public static function puedeOperarSobreEstablecimiento($idEstablecimiento, $con)
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }

        if (empty($_SESSION['id_usuario'])) { return false; }

        // Rol 1 (Administrador) y 2 (Internos Alcaldia) operan sobre
        // cualquier establecimiento; es su trabajo.
        $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
        if (in_array($rol, [1, 2], true)) { return true; }

        // No hay columna que ate el usuario al contribuyente: el sistema los
        // cruza por número de documento (igual que usu_idContibuyente en
        // DAO_Usuario.php).
        $propio = $con->obnerFila($con->consultar(
            "SELECT e.est_Id
               FROM ind_establecimientos e
               INNER JOIN ind_contribuyentes c ON c.ind_Id = e.est_IdContribuyente
               INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
              WHERE u.usu_Id = ? AND e.est_Id = ?",
            [(int) $_SESSION['id_usuario'], (int) $idEstablecimiento]
        ));

        return (bool) $propio;
    }

    private function _subir()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idEstablecimiento = (int) ($_POST['est_Id'] ?? 0);
        if ($idEstablecimiento <= 0) {
            $this->_mensaje = 'No se indicó el establecimiento';
            return [];
        }

        // El establecimiento tiene que existir: sin esto se podrian crear
        // carpetas con cualquier numero que alguien mande.
        $est = $con->obnerFila($con->consultar(
            "SELECT est_Id FROM ind_establecimientos WHERE est_Id = ?",
            [$idEstablecimiento]
        ));
        if (!$est) {
            $this->_mensaje = 'El establecimiento no existe';
            return [];
        }

        if (!self::puedeOperarSobreEstablecimiento($idEstablecimiento, $con)) {
            $this->_mensaje = 'No tiene permiso para cargar archivos en este establecimiento';
            return [];
        }

        if (!isset($_FILES['anexos'])) {
            $this->_mensaje = 'No llegó ningún archivo';
            return [];
        }

        $tipo = substr(trim((string) ($_POST['tipo'] ?? 'otro')), 0, 40);

        $carpeta = self::carpetaBase() . '/' . $idEstablecimiento;
        if (!is_dir($carpeta) && !mkdir($carpeta, 0750, true)) {
            $this->_mensaje = 'No se pudo crear la carpeta de anexos';
            return [];
        }
        if (!self::blindarCarpeta()) {
            // Sin el .htaccess, cualquier archivo que se suba aqui queda
            // alcanzable por URL directa sin sesion (confirmado: se probo
            // quitando el permiso de escritura y el archivo subido quedo
            // descargable con un curl limpio). Mejor rechazar la subida con
            // un mensaje claro que aceptarla "exitosamente" sin proteccion.
            $this->_mensaje = 'No se pudo proteger la carpeta de anexos. Contacte a soporte antes de volver a intentar.';
            return [];
        }

        // Un solo archivo llega como cadena; varios, como arreglo. Se
        // normaliza para no escribir el bucle dos veces.
        $nombres = (array) $_FILES['anexos']['name'];
        $tmps    = (array) $_FILES['anexos']['tmp_name'];
        $errores = (array) $_FILES['anexos']['error'];
        $tamanos = (array) $_FILES['anexos']['size'];

        $guardados = [];
        $rechazados = [];

        foreach ($tmps as $i => $tmp) {

            $nombreOriginal = basename((string) $nombres[$i]);

            if (($errores[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $rechazados[] = "$nombreOriginal: no se pudo recibir";
                continue;
            }

            // is_uploaded_file impide que alguien pase una ruta del servidor
            // (por ejemplo /etc/passwd) haciendose pasar por una subida.
            if (!is_uploaded_file($tmp)) {
                $rechazados[] = "$nombreOriginal: origen no válido";
                continue;
            }

            if (($tamanos[$i] ?? 0) > self::TAMANO_MAXIMO) {
                $rechazados[] = "$nombreOriginal: supera los 10 MB";
                continue;
            }

            $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            if (!isset(self::TIPOS_PERMITIDOS[$extension])) {
                $rechazados[] = "$nombreOriginal: solo se aceptan PDF, JPG y PNG";
                continue;
            }

            // La extension la escribe quien sube; el contenido no miente
            // tanto. Un .pdf con un ejecutable adentro se cae aqui.
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $tipoReal = $finfo->file($tmp);
            if (!in_array($tipoReal, self::TIPOS_PERMITIDOS[$extension], true)) {
                $rechazados[] = "$nombreOriginal: el contenido no corresponde a un $extension";
                continue;
            }

            // Nombre generado por el servidor. El del usuario se guarda aparte,
            // solo para mostrarlo.
            $nombreDisco = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $destino     = $carpeta . '/' . $nombreDisco;

            if (!move_uploaded_file($tmp, $destino)) {
                $rechazados[] = "$nombreOriginal: no se pudo guardar";
                continue;
            }
            @chmod($destino, 0640);

            $con->consultar(
                "INSERT INTO ind_establecimiento_anexos
                    (anx_IdEstablecimiento, anx_Tipo, anx_NombreOriginal,
                     anx_Ruta, anx_Extension, anx_Tamano, anx_IdUsuario)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $idEstablecimiento, $tipo, $nombreOriginal,
                    $idEstablecimiento . '/' . $nombreDisco,
                    $extension, (int) $tamanos[$i], $this->_idUsuario(),
                ]
            );

            $guardados[] = $nombreOriginal;
        }

        $this->_ok = $guardados ? 1 : 0;
        $this->_mensaje = $guardados
            ? count($guardados) . ' archivo(s) cargado(s)'
            : 'No se cargó ningún archivo';
        if ($rechazados) {
            $this->_mensaje .= '. Rechazados: ' . implode('; ', $rechazados);
        }

        return ['guardados' => $guardados, 'rechazados' => $rechazados];
    }

    /** Punto 17: poder consultar los archivos ya subidos. */
    private function _listar()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idEstablecimiento = (int) ($_POST['est_Id'] ?? 0);
        if ($idEstablecimiento <= 0) {
            $this->_mensaje = 'No se indicó el establecimiento';
            return [];
        }

        if (!self::puedeOperarSobreEstablecimiento($idEstablecimiento, $con)) {
            $this->_mensaje = 'No tiene permiso para ver los anexos de este establecimiento';
            return [];
        }

        $stmt = $con->consultar(
            "SELECT anx_Id, anx_Tipo, anx_NombreOriginal, anx_Extension,
                    anx_Tamano, anx_FechaCarga
               FROM ind_establecimiento_anexos
              WHERE anx_IdEstablecimiento = ? AND anx_Activo = 1
              ORDER BY anx_FechaCarga DESC",
            [$idEstablecimiento]
        );

        $lista = [];
        while ($f = $con->obnerFila($stmt)) {
            if ($f['anx_FechaCarga'] instanceof \DateTime) {
                $f['anx_FechaCarga'] = $f['anx_FechaCarga']->format('Y-m-d H:i');
            }
            $lista[] = $f;
        }

        $this->_ok = 1;
        $this->_mensaje = count($lista) . ' anexo(s)';

        return $lista;
    }

    /**
     * Punto 18: borrado LOGICO. El archivo sigue en disco y la fila se puede
     * recuperar. La queja del cliente era justamente que los archivos
     * desaparecian; borrarlos de verdad seria repetir el problema.
     */
    private function _eliminar()
    {
        $con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

        $idAnexo = (int) ($_POST['anx_Id'] ?? 0);
        if ($idAnexo <= 0) {
            $this->_mensaje = 'No se indicó el anexo';
            return [];
        }

        $anexo = $con->obnerFila($con->consultar(
            "SELECT anx_IdEstablecimiento FROM ind_establecimiento_anexos WHERE anx_Id = ?",
            [$idAnexo]
        ));
        if (!$anexo) {
            $this->_mensaje = 'El anexo no existe';
            return [];
        }

        if (!self::puedeOperarSobreEstablecimiento($anexo['anx_IdEstablecimiento'], $con)) {
            $this->_mensaje = 'No tiene permiso para quitar este anexo';
            return [];
        }

        $con->consultar(
            "UPDATE ind_establecimiento_anexos SET anx_Activo = 0 WHERE anx_Id = ?",
            [$idAnexo]
        );

        $this->_ok = 1;
        $this->_mensaje = 'Anexo retirado';

        return ['anx_Id' => $idAnexo];
    }
}

class AnexosException extends \Exception { }

/*
 * Los demas controladores del proyecto se auto-ejecutan al final del archivo.
 * Este NO puede hacerlo a ciegas: extensiones/anexo.php lo incluye para
 * reutilizar carpetaBase(), y con el arranque incondicional el simple include
 * contestaba un JSON de "Función no válida" antes de tiempo. Con las cabeceras
 * ya enviadas, el http_response_code(401) de anexo.php se perdia y la respuesta
 * salia con estado 200.
 */
if (isset($_SERVER['SCRIPT_FILENAME'])
    && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    \erpsoftsas\ControladorAnexos::run();
}
