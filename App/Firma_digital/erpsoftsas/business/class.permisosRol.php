<?php
namespace erpsoftsas;

/*
 * ============================================================================
 * PERMISOS DE LA SESION (2026-09-29)
 * ============================================================================
 *
 * Lo que puede hacer quien tiene la sesion abierta. Es LA pregunta de todo el
 * servidor: antes cada controlador decidia por el numero de rol ("1 o 2 es la
 * Alcaldia", "1 es el administrador") y los permisos del panel de Roles solo
 * los miraba el menu. Un rol nuevo no funcionaba aunque tuviera todo marcado.
 *
 *   PermisosRol::tiene('ica.firmar')   ¿tiene prendido ese interruptor?
 *   PermisosRol::esAlcaldia()          ¿el rol es de la Alcaldia (o el admin)?
 *   PermisosRol::gestionaOtros()       ¿puede trabajar sobre CUALQUIER
 *                                      contribuyente? (Alcaldia + "Gestionar")
 *   PermisosRol::esAdministrador()     el administrador: tiene TODO, siempre.
 *
 * El catalogo (claves como 'ica.firmar') y el tipo de rol vienen de la
 * migracion 040. Sin ella se cae al comportamiento anterior (por numero de
 * rol), para que desplegar el codigo antes de correrla no tumbe nada.
 *
 * Las secciones de la Alcaldia (alcaldia.*, parametros.*, usuarios.*, roles.*)
 * solo cuentan en un rol de la Alcaldia: un rol de contribuyente con esos
 * interruptores prendidos por error no gana poderes de funcionario.
 * ============================================================================
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';

class PermisosRol
{
    const ADMINISTRADOR = 'ADMINISTRADOR';
    const ALCALDIA      = 'ALCALDIA';
    const CONTRIBUYENTE = 'CONTRIBUYENTE';
    const EXTERNO       = 'EXTERNO';

    /** Tipos que se pueden escoger al crear un rol (el administrador es uno solo). */
    const TIPOS_ELEGIBLES = [self::ALCALDIA, self::CONTRIBUYENTE, self::EXTERNO];

    /** Secciones que solo cuentan en un rol de la Alcaldia. */
    const PREFIJOS_ALCALDIA = ['alcaldia.', 'parametros.', 'usuarios.', 'roles.'];

    private static $tipos  = [];   // rol => tipo
    private static $claves = [];   // rol => [clave => true]
    private static $hayCatalogo = null;
    private static $conFilas = [];  // rol => tiene alguna fila en conf_permisos (sin la 040)
    private static $guardadas = []; // rol => claves guardadas (activo o no)
    private static $cuenta = null;  // ['id', 'rol', 'activa'] de la cuenta de la sesion

    /* ------------------------------------------------------------------ */

    private static function _con()
    {
        return \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
    }

    private static function _sesion()
    {
        if (session_status() === PHP_SESSION_NONE) { @session_start(); }
    }

    /**
     * Usuario de la sesion, o 0 sin sesion. Tambien 0 si su cuenta ya no existe
     * o fue inactivada: la sesion no sobrevive a eso (ver _cuenta).
     */
    public static function idUsuario()
    {
        self::_sesion();
        $id = (int) ($_SESSION['id_usuario'] ?? 0);
        if ($id <= 0) { return 0; }
        return self::_cuenta($id)['activa'] ? $id : 0;
    }

    /** Rol ACTUAL de la cuenta de la sesion (no el que tenia al iniciar sesion), o 0. */
    public static function rol()
    {
        $id = self::idUsuario();
        return $id > 0 ? self::_cuenta($id)['rol'] : 0;
    }

    /**
     * Rol y estado de la cuenta, leidos de la base UNA vez por peticion. Antes
     * quedaban los del login hasta que vencia la sesion: una cuenta inactivada
     * seguia trabajando, y a una a la que el administrador le cambiaba el rol le
     * seguia valiendo el anterior (con "Asignar permisos" podia deshacer el
     * cambio). Sin base, los datos del login.
     */
    private static function _cuenta($id)
    {
        if (self::$cuenta === null || self::$cuenta['id'] !== $id) {
            $rol = (int) ($_SESSION['id_Rol'] ?? 0);
            $activa = true;
            try {
                $f = self::_con()->obnerFila(self::_con()->consultar(
                    "SELECT usu_Rol, ISNULL(usu_Estado, 1) AS e FROM conf_usuarios WHERE usu_Id = ?", [(int) $id]
                ));
                if (!$f) {
                    $activa = false;
                } else {
                    $rol = (int) $f['usu_Rol'];
                    $activa = (int) $f['e'] === 1;
                }
            } catch (\Throwable $e) { /* sin base: los del login */ }
            if (!$activa) {
                // La sesion deja de ser de nadie: esta peticion ya no puede
                // nada y la siguiente lleva al login ("Debe iniciar sesión").
                unset($_SESSION['id_usuario'], $_SESSION['IdUsuario'], $_SESSION['usu_Id'], $_SESSION['id_Rol']);
            } elseif ($rol !== (int) ($_SESSION['id_Rol'] ?? 0)) {
                // El codigo que aun lee la sesion directo ve el rol de hoy.
                $_SESSION['id_Rol'] = $rol;
            }
            self::$cuenta = ['id' => $id, 'rol' => $rol, 'activa' => $activa];
        }
        return self::$cuenta;
    }

    /**
     * ¿Esta la migracion 040 (tipo de rol y claves de permiso)? Cuenta cuando
     * quedo REGISTRADA, que es su ultimo paso: con las columnas pero sin el
     * catalogo (una corrida que fallo a mitad) nadie tendria permisos.
     */
    public static function hayCatalogo()
    {
        if (self::$hayCatalogo === null) {
            try {
                $f = self::_con()->obnerFila(self::_con()->consultar(
                    "SELECT COL_LENGTH('dbo.conf_rol', 'rol_Tipo') AS t,
                            COL_LENGTH('dbo.conf_submodulo', 'subMod_Clave') AS c,
                            (SELECT COUNT(*) FROM dbo.conf_migraciones
                              WHERE mig_Nombre = '040_roles_y_permisos_por_accion') AS m"
                ));
                self::$hayCatalogo = !empty($f['t']) && !empty($f['c']) && (int) $f['m'] > 0;
            } catch (\Throwable $e) {
                self::$hayCatalogo = false;
            }
        }
        return self::$hayCatalogo;
    }

    /** Tipo del rol (por defecto el de la sesion). */
    public static function tipo($rol = null)
    {
        $rol = ($rol === null) ? self::rol() : (int) $rol;
        if ($rol <= 0) { return ''; }
        if ($rol === 1) { return self::ADMINISTRADOR; }
        if (!array_key_exists($rol, self::$tipos)) {
            $tipo = '';
            if (self::hayCatalogo()) {
                $f = self::_con()->obnerFila(self::_con()->consultar(
                    "SELECT rol_Tipo AS t FROM conf_rol WHERE rol_Id = ?", [$rol]
                ));
                $tipo = strtoupper(trim((string) ($f['t'] ?? '')));
            }
            if ($tipo === '') {
                // Sin la 040 (o un rol sin tipo): el significado de siempre. Antes
                // solo el 1 y el 2 eran de la Alcaldia; cualquier otro rol
                // trabajaba sobre el contribuyente de su propia cuenta.
                $tipo = [2 => self::ALCALDIA, 3 => self::EXTERNO, 4 => self::CONTRIBUYENTE][$rol] ?? self::CONTRIBUYENTE;
            }
            self::$tipos[$rol] = $tipo;
        }
        return self::$tipos[$rol];
    }

    public static function esAdministrador($rol = null)
    {
        return self::tipo($rol) === self::ADMINISTRADOR;
    }

    /** Rol de la Alcaldia: el administrador o uno de tipo ALCALDIA. */
    public static function esAlcaldia($rol = null)
    {
        return in_array(self::tipo($rol), [self::ADMINISTRADOR, self::ALCALDIA], true);
    }

    /**
     * Claves que CUENTAN para el rol: las guardadas, y ninguna si el rol esta
     * inactivo (sin contar la regla del administrador).
     */
    public static function claves($rol = null)
    {
        $rol = ($rol === null) ? self::rol() : (int) $rol;
        if ($rol <= 0) { return []; }
        if (!array_key_exists($rol, self::$claves)) {
            $lista = [];
            if (self::_rolActivo($rol)) {
                foreach (self::clavesGuardadas($rol) as $c) { $lista[$c] = true; }
            }
            self::$claves[$rol] = $lista;
        }
        return array_keys(self::$claves[$rol]);
    }

    /**
     * Claves guardadas del rol, este activo o no. Las usan el panel (un rol
     * inactivo se muestra con lo que tiene, y guardarlo no le borra nada sin
     * querer) y la regla de no escalar (un rol inactivo no pasa por "sin
     * permisos" para que alguien lo reactive o lo entregue).
     */
    public static function clavesGuardadas($rol)
    {
        $rol = (int) $rol;
        if ($rol <= 0 || !self::hayCatalogo()) { return []; }
        if (!array_key_exists($rol, self::$guardadas)) {
            $lista = [];
            $st = self::_con()->consultar(
                "SELECT s.subMod_Clave AS c
                   FROM conf_permisos p
                   JOIN conf_submodulo s ON s.subMod_Id = p.per_IdSubmodulo
                  WHERE p.per_IdRol = ? AND ISNULL(p.per_Estado, 1) = 1
                    AND s.subMod_Clave IS NOT NULL",
                [$rol]
            );
            while ($f = self::_con()->obnerFila($st)) { $lista[] = (string) $f['c']; }
            self::$guardadas[$rol] = array_values(array_unique($lista));
        }
        return self::$guardadas[$rol];
    }

    private static function _rolActivo($rol)
    {
        $f = self::_con()->obnerFila(self::_con()->consultar(
            "SELECT ISNULL(rol_Estado, 1) AS e FROM conf_rol WHERE rol_Id = ?", [(int) $rol]
        ));
        return $f && (int) $f['e'] === 1;
    }

    /** ¿La clave es de una seccion que solo cuenta en roles de la Alcaldia? */
    public static function esClaveDeAlcaldia($clave)
    {
        foreach (self::PREFIJOS_ALCALDIA as $p) {
            if (strpos((string) $clave, $p) === 0) { return true; }
        }
        return false;
    }

    /**
     * ¿El rol (por defecto el de la sesion) puede hacer esto?
     * El administrador puede todo. Sin sesion, nada.
     */
    public static function tiene($clave, $rol = null)
    {
        $rol = ($rol === null) ? self::rol() : (int) $rol;
        if ($rol <= 0) { return false; }
        if (self::esAdministrador($rol)) { return true; }
        if (self::esClaveDeAlcaldia($clave) && !self::esAlcaldia($rol)) { return false; }

        if (!self::hayCatalogo()) {
            return self::_comoAntes((string) $clave, $rol);
        }
        self::claves($rol);
        return isset(self::$claves[$rol][(string) $clave]);
    }

    /** Alguno de estos permisos. */
    public static function tieneAlguno(array $claves, $rol = null)
    {
        foreach ($claves as $c) { if (self::tiene($c, $rol)) { return true; } }
        return false;
    }

    /**
     * ¿Puede trabajar sobre CUALQUIER contribuyente (el "contribuyente activo"
     * que escoge la Alcaldia en Contribuyentes > Gestionar)? Los demas solo
     * sobre el suyo, que se resuelve por el documento de su cuenta.
     */
    public static function gestionaOtros($rol = null)
    {
        return self::esAlcaldia($rol) && self::tiene('alcaldia.contribuyentes.gestionar', $rol);
    }

    /**
     * Lo que un permiso necesita para servir de algo: quien edita un borrador
     * tiene que poder verlo, quien cierra un establecimiento tiene que poder
     * gestionar a su dueño. El panel los prende solos y el servidor los
     * completa al guardar, asi un rol nunca queda con un boton que rebota.
     */
    public static function requisitos($clave)
    {
        $clave = (string) $clave;
        $fijos = [
            'alcaldia.contribuyentes.editar'    => ['alcaldia.contribuyentes.ver'],
            'alcaldia.contribuyentes.gestionar' => ['alcaldia.contribuyentes.ver'],
            // Se cierra desde el formulario del establecimiento (opcion
            // "Cierre", soporte y fecha): hace falta poder abrirlo para editar.
            'alcaldia.establecimientos.cerrar'  => ['alcaldia.contribuyentes.gestionar', 'establecimientos.editar'],
            'alcaldia.establecimientos.reabrir' => ['alcaldia.contribuyentes.gestionar', 'establecimientos.ver'],
            'alcaldia.cese'                     => ['alcaldia.contribuyentes.gestionar', 'rit.ver'],
            'alcaldia.recaudo.asignar'          => ['alcaldia.recaudo.cargar'],
            'alcaldia.recibo.intereses'         => ['alcaldia.contribuyentes.gestionar'],
            'parametros.actividades'            => ['parametros.ver'],
            'parametros.conceptos'              => ['parametros.ver'],
            'parametros.grupos'                 => ['parametros.ver'],
            'establecimientos.editar'           => ['establecimientos.ver'],
            'usuarios.editar'                   => ['usuarios.ver'],
            'usuarios.estado'                   => ['usuarios.ver'],
            'roles.editar'                      => ['roles.ver'],
            'roles.permisos'                    => ['roles.ver'],
        ];
        if (isset($fijos[$clave])) { return $fijos[$clave]; }
        // RIT y las tres declaraciones: toda accion pide "ver" de su seccion.
        // Corregir crea un borrador que despues hay que editar: pide editar.
        if (preg_match('/^(rit|ica|reteica|autorreteica)\.(\w+)$/', $clave, $m) && $m[2] !== 'ver') {
            return $m[2] === 'corregir' ? [$m[1] . '.ver', $m[1] . '.editar'] : [$m[1] . '.ver'];
        }
        return [];
    }

    /** Las claves con todo lo que necesitan (cierre transitivo de requisitos). */
    public static function completarRequisitos(array $claves)
    {
        $todas = [];
        $pendientes = array_values($claves);
        while ($pendientes) {
            $c = (string) array_pop($pendientes);
            if ($c === '' || isset($todas[$c])) { continue; }
            $todas[$c] = true;
            foreach (self::requisitos($c) as $r) { $pendientes[] = $r; }
        }
        return array_keys($todas);
    }

    /**
     * ¿La sesion puede entregar (o modificar) este rol sin repartir mas de lo
     * que ella misma tiene? El administrador, siempre; el rol Administrador,
     * solo el administrador. Un rol de la Alcaldia, solo si todo lo que trae
     * ya lo tiene quien lo entrega: si no, un funcionario con "Crear y editar
     * usuarios" podria hacerse una cuenta con mas poder que la suya. Los de
     * contribuyente y consulta externa trabajan solo sobre el contribuyente de
     * la propia cuenta, asi que no reparten poder sobre nadie mas.
     */
    public static function puedeEntregarRol($rolDestino)
    {
        $rolDestino = (int) $rolDestino;
        if (self::esAdministrador()) { return true; }
        if (self::esAdministrador($rolDestino)) { return false; }
        if (!self::esAlcaldia($rolDestino)) { return true; }
        // Las GUARDADAS: un rol inactivo no pasa por "sin permisos".
        foreach (self::clavesGuardadas($rolDestino) as $c) {
            if (!self::tiene($c)) { return false; }
        }
        return true;
    }

    /**
     * Sin la migracion 040: lo que hacia cada rol por su numero. El 2 (Alcaldia)
     * podia todo lo de la Alcaldia menos lo del administrador; el 4 lo del
     * contribuyente; el 3 la consulta de predial.
     */
    private static function _comoAntes($clave, $rol)
    {
        // Un rol sin ninguna fila de permisos no podia ni iniciar sesion (hoy,
        // el rol 2): tampoco puede nada aqui, mientras no se corra la 040.
        if (!array_key_exists($rol, self::$conFilas)) {
            self::$conFilas[$rol] = (bool) self::_con()->obnerFila(self::_con()->consultar(
                "SELECT TOP 1 1 AS x FROM conf_permisos WHERE per_IdRol = ?", [(int) $rol]
            ));
        }
        if (!self::$conFilas[$rol]) { return false; }

        $soloAdmin = ['alcaldia.establecimientos.reabrir', 'alcaldia.cese', 'parametros.municipio'];
        if (strpos($clave, 'usuarios.') === 0 || strpos($clave, 'roles.') === 0) { return false; }
        if (in_array($clave, $soloAdmin, true)) { return false; }
        $tipo = self::tipo($rol);
        if ($tipo === self::ALCALDIA) {
            return $clave !== 'predial.consultar';
        }
        if ($tipo === self::CONTRIBUYENTE) {
            return (bool) preg_match('/^(rit|establecimientos|ica|reteica|autorreteica)\./', $clave);
        }
        if ($tipo === self::EXTERNO) {
            return $clave === 'predial.consultar';
        }
        return false;
    }

    /**
     * Respuesta de "no puede" para los controladores JSON: ok 0, el motivo y
     * sinSesion (el aviso de sesion vencida de dist/menu.php). Termina.
     */
    public static function negar($mensaje)
    {
        header('Content-type: application/json');
        echo json_encode([
            'ok'         => 0,
            'mensaje'    => $mensaje,
            'datos'      => [],
            'sinSesion'  => self::idUsuario() > 0 ? 0 : 1,
            'sinPermiso' => self::idUsuario() > 0 ? 1 : 0,
        ]);
    }

    /** El texto de "no tiene permiso" para una clave, con el nombre del catalogo. */
    public static function mensaje($clave)
    {
        if (self::idUsuario() <= 0) { return 'Debe iniciar sesión.'; }
        $nombre = '';
        if (self::hayCatalogo()) {
            try {
                $f = self::_con()->obnerFila(self::_con()->consultar(
                    "SELECT s.subMod_Nombre AS n, m.mod_Nombre AS g
                       FROM conf_submodulo s JOIN conf_modulo m ON m.mod_Id = s.subMod_IdModulo
                      WHERE s.subMod_Clave = ?", [(string) $clave]
                ));
                if ($f) { $nombre = ' («' . $f['n'] . '» en ' . $f['g'] . ')'; }
            } catch (\Throwable $e) { /* sin nombre */ }
        }
        return 'Su rol no tiene permiso para esta acción' . $nombre . '. Pídaselo al administrador.';
    }

    /**
     * Lo que la pantalla necesita para mostrar u ocultar opciones: tipo, si es
     * administrador y sus claves. Lo pinta dist/menu.php en window.ERP_PERMISOS.
     * No es seguridad (el servidor revisa cada accion): es para no mostrar
     * botones que van a rebotar.
     */
    public static function paraPantalla()
    {
        $rol = self::rol();
        if ($rol <= 0) { return ['rol' => 0, 'tipo' => '', 'admin' => false, 'alcaldia' => false, 'gestiona' => false, 'activo' => false, 'claves' => []]; }
        $admin = self::esAdministrador($rol);
        $f = self::_con()->obnerFila(self::_con()->consultar("SELECT rol_Estado AS e FROM conf_rol WHERE rol_Id = ?", [$rol]));
        // Un rol inactivo no tiene permisos (claves() lo filtra); el login lo
        // usa para decir por que no se entra.
        $activo = $admin || ($f && (int) $f['e'] === 1);
        $claves = [];
        if ($admin) {
            if (self::hayCatalogo()) {
                $st = self::_con()->consultar("SELECT subMod_Clave AS c FROM conf_submodulo WHERE subMod_Clave IS NOT NULL");
                while ($f = self::_con()->obnerFila($st)) { $claves[] = (string) $f['c']; }
            }
        } else {
            foreach (self::_todasLasClaves() as $c) { if (self::tiene($c, $rol)) { $claves[] = $c; } }
        }
        return [
            'rol'      => $rol,
            'tipo'     => self::tipo($rol),
            'admin'    => $admin,
            'alcaldia' => self::esAlcaldia($rol),
            // Trabaja sobre cualquier contribuyente (barra "Gestionando a").
            'gestiona' => self::gestionaOtros($rol),
            'activo'   => $activo,
            'claves'   => array_values(array_unique($claves)),
        ];
    }

    /** Todas las claves del catalogo (o las conocidas, sin la 040). */
    private static function _todasLasClaves()
    {
        if (self::hayCatalogo()) {
            $lista = [];
            $st = self::_con()->consultar("SELECT subMod_Clave AS c FROM conf_submodulo WHERE subMod_Clave IS NOT NULL");
            while ($f = self::_con()->obnerFila($st)) { $lista[] = (string) $f['c']; }
            return $lista;
        }
        $lista = ['alcaldia.contribuyentes.ver', 'alcaldia.contribuyentes.editar', 'alcaldia.contribuyentes.gestionar',
                  'alcaldia.establecimientos.ver', 'alcaldia.establecimientos.cerrar', 'alcaldia.establecimientos.reabrir',
                  'alcaldia.cese', 'alcaldia.recaudo.cargar', 'alcaldia.recaudo.asignar', 'alcaldia.recibo.intereses',
                  'parametros.ver', 'parametros.actividades', 'parametros.conceptos', 'parametros.grupos', 'parametros.municipio',
                  'rit.ver', 'rit.editar', 'rit.firmar', 'rit.documentos', 'establecimientos.ver', 'establecimientos.editar',
                  'usuarios.ver', 'usuarios.editar', 'usuarios.estado', 'roles.ver', 'roles.editar', 'roles.permisos', 'predial.consultar'];
        foreach (['ica', 'reteica', 'autorreteica'] as $m) {
            foreach (['ver', 'editar', 'firmar', 'presentar', 'corregir', 'pagar'] as $a) { $lista[] = "$m.$a"; }
        }
        return $lista;
    }

    /** Olvida lo leido (pruebas, o despues de guardar permisos en la misma peticion). */
    public static function olvidar()
    {
        self::$tipos = [];
        self::$claves = [];
        self::$hayCatalogo = null;
        self::$conFilas = [];
        self::$guardadas = [];
        self::$cuenta = null;
    }
}
