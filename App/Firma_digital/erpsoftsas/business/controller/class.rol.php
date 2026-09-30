<?php
namespace erpsoftsas;

/*
 * ============================================================================
 * ROLES Y SUS PERMISOS (panel de Roles)
 * ============================================================================
 *
 * Reescrito el 2026-09-29 (pedido del cliente: el administrador arma los roles
 * con interruptores, uno por accion, y el sistema los hace cumplir):
 *
 *   1 crear rol          "Crear y editar roles"   nombre, descripcion y TIPO
 *   2 editar rol         "Crear y editar roles"
 *   3 consultar roles    "Ver roles", o la pantalla de Usuarios (su lista de
 *                        roles para escoger)
 *   4 activar/inactivar  "Crear y editar roles"
 *   5 permisos de un rol "Ver roles": el catalogo por grupos con lo prendido
 *   6 guardar permisos   "Asignar permisos a los roles"
 *
 * Antes no pedia ni sesion: cualquiera, desde internet, podia crear roles,
 * renombrarlos, inactivarlos (incluido el de los contribuyentes) o, por
 * class.permisos.php, reescribirles los permisos.
 *
 * Reglas que protegen al sistema de un clic:
 *   - El Administrador no se toca: tiene TODO siempre (PermisosRol).
 *   - No se cambia el TIPO de un rol que ya tiene cuentas (convertiria de
 *     golpe a todos los contribuyentes en funcionarios, o al reves).
 *   - No se inactiva un rol con cuentas activas (las dejaria por fuera).
 *   - Nadie cambia su propio rol, y quien no es administrador no reparte lo
 *     que no tiene: solo modifica roles cuyos permisos ya tiene, y a un rol de
 *     la Alcaldia solo le da permisos que el mismo tiene
 *     (PermisosRol::puedeEntregarRol). Si no, "Asignar permisos" bastaria
 *     para darse todo.
 *   - Cada permiso arrastra lo que necesita (PermisosRol::requisitos): quien
 *     edita un borrador tambien lo ve.
 *
 * SQL parametrizado: el DAO del proyecto pega los valores en el texto.
 * ============================================================================
 */

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER . '/business/class.permisosRol.php';

class ControladorRol
{
    private $_ok = 0;
    private $_mensaje = '';

    const TIPOS_NOMBRE = [
        'ADMINISTRADOR' => 'Administrador',
        'ALCALDIA'      => 'Alcaldía',
        'CONTRIBUYENTE' => 'Contribuyente',
        'EXTERNO'       => 'Consulta externa',
    ];

    public static function run()
    {
        $obj = new self();
        $funcion = (int) ($_POST['funcion'] ?? 0);

        if (PermisosRol::idUsuario() <= 0) {
            PermisosRol::negar('Debe iniciar sesión.');
            return;
        }

        $necesita = [
            1 => ['roles.editar'],
            2 => ['roles.editar'],
            3 => ['roles.ver', 'usuarios.ver', 'usuarios.editar'],
            4 => ['roles.editar'],
            5 => ['roles.ver'],
            6 => ['roles.permisos'],
        ][$funcion] ?? null;
        if ($necesita === null) {
            PermisosRol::negar('Función no válida.');
            return;
        }
        if (!PermisosRol::tieneAlguno($necesita)) {
            PermisosRol::negar(PermisosRol::mensaje($necesita[0]));
            return;
        }

        try {
            switch ($funcion) {
                case 1: $datos = $obj->_crear(); break;
                case 2: $datos = $obj->_editar(); break;
                case 3: $datos = $obj->_consultar(); break;
                case 4: $datos = $obj->_estado(); break;
                case 5: $datos = $obj->_permisosDelRol(); break;
                case 6: $datos = $obj->_guardarPermisos(); break;
            }
            header('Content-type: application/json');
            echo json_encode(['ok' => $obj->_ok, 'mensaje' => $obj->_mensaje, 'datos' => $datos]);
        } catch (\Throwable $e) {
            error_log('[roles] funcion ' . $funcion . ': ' . $e->getMessage());
            header('Content-type: application/json');
            echo json_encode(['ok' => 0, 'mensaje' => 'No se pudo completar la operación. Intente de nuevo; si sigue, avise a soporte.', 'datos' => []]);
        }
    }

    private static function _con()
    {
        return \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();
    }

    /**
     * Texto de una linea. Sin < ni >: el nombre de un rol aparece en listas y
     * avisos de otras pantallas, y un nombre con HTML se ejecutaria con la
     * sesion de quien las abre (revision 2026-09-29). Ningun nombre los usa.
     */
    private static function _texto($v, $max)
    {
        $v = str_replace(['<', '>'], '', (string) $v);
        $v = trim(preg_replace('/\s+/u', ' ', $v));
        return mb_substr($v, 0, $max);
    }

    /** El rol que reciben las inscripciones desde internet (class.usuarios.php). */
    const ROL_INSCRIPCIONES = 4;

    private static function _fila($id)
    {
        $col = PermisosRol::hayCatalogo() ? 'rol_Tipo' : 'CAST(NULL AS VARCHAR(20)) AS rol_Tipo';
        return self::_con()->obnerFila(self::_con()->consultar(
            "SELECT rol_Id, rol_Nombre, rol_Descripcion, rol_Estado, $col FROM conf_rol WHERE rol_Id = ?", [(int) $id]
        ));
    }

    private static function _cuentas($id, $soloActivas = false)
    {
        $f = self::_con()->obnerFila(self::_con()->consultar(
            "SELECT COUNT(*) AS n FROM conf_usuarios WHERE usu_Rol = ?" . ($soloActivas ? " AND ISNULL(usu_Estado, 1) = 1" : ''),
            [(int) $id]
        ));
        return (int) ($f['n'] ?? 0);
    }

    /**
     * Mensaje si la sesion no puede modificar este rol (nombre, estado o
     * permisos); null si puede. Ver las reglas del encabezado.
     */
    private static function _noPuedeModificar($id)
    {
        if (PermisosRol::esAdministrador($id)) {
            return 'El rol Administrador no se modifica: tiene todos los permisos, siempre.';
        }
        if (PermisosRol::esAdministrador()) { return null; }
        if ($id === PermisosRol::rol()) {
            return 'No puede modificar su propio rol. Pídaselo al administrador.';
        }
        if (!PermisosRol::puedeEntregarRol($id)) {
            return 'Ese rol tiene permisos que el suyo no tiene: solo el administrador puede modificarlo.';
        }
        return null;
    }

    private static function _nombreRepetido($nombre, $excepto = 0)
    {
        return (bool) self::_con()->obnerFila(self::_con()->consultar(
            "SELECT TOP 1 1 AS x FROM conf_rol
              WHERE LTRIM(RTRIM(rol_Nombre)) = ? AND rol_Id <> ?", [$nombre, (int) $excepto]
        ));
    }

    /* ---------------------------------------------------------- 1 crear */
    private function _crear()
    {
        $nombre = self::_texto($_POST['nombre'] ?? '', 100);
        $descripcion = self::_texto($_POST['descripcion'] ?? '', 500);
        $tipo = strtoupper(trim((string) ($_POST['tipo'] ?? '')));

        if ($nombre === '') { $this->_mensaje = 'Escriba el nombre del rol.'; return []; }
        if (!in_array($tipo, PermisosRol::TIPOS_ELEGIBLES, true)) {
            $this->_mensaje = 'Escoja el tipo del rol: Alcaldía, Contribuyente o Consulta externa.';
            return [];
        }
        if (self::_nombreRepetido($nombre)) {
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un rol con ese nombre.';
            return [];
        }

        $conTipo = PermisosRol::hayCatalogo();
        $fila = self::_con()->obnerFila(self::_con()->consultar(
            "SET NOCOUNT ON;
             INSERT INTO conf_rol (rol_Nombre, rol_Descripcion, rol_Estado, rol_Fecha_Creacion" . ($conTipo ? ", rol_Tipo" : '') . ")
             VALUES (?, NULLIF(?, ''), 1, GETDATE()" . ($conTipo ? ", ?" : '') . ");
             SELECT CAST(SCOPE_IDENTITY() AS INT) AS id;",
            $conTipo ? [$nombre, $descripcion, $tipo] : [$nombre, $descripcion]
        ));

        $this->_ok = 1;
        $this->_mensaje = 'Rol creado. Ahora prenda sus permisos: sin ninguno, sus cuentas no pueden ingresar.';
        return ['rol_Id' => (int) ($fila['id'] ?? 0)];
    }

    /* ---------------------------------------------------------- 2 editar */
    private function _editar()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $rol = self::_fila($id);
        if (!$rol) { $this->_mensaje = 'El rol no existe.'; return []; }
        if (($motivo = self::_noPuedeModificar($id)) !== null) {
            $this->_mensaje = $motivo;
            return [];
        }

        $nombre = self::_texto($_POST['nombre'] ?? '', 100);
        $descripcion = self::_texto($_POST['descripcion'] ?? '', 500);
        $tipo = strtoupper(trim((string) ($_POST['tipo'] ?? ($rol['rol_Tipo'] ?? ''))));

        if ($nombre === '') { $this->_mensaje = 'Escriba el nombre del rol.'; return []; }
        if (!in_array($tipo, PermisosRol::TIPOS_ELEGIBLES, true)) {
            $this->_mensaje = 'Escoja el tipo del rol: Alcaldía, Contribuyente o Consulta externa.';
            return [];
        }
        if (self::_nombreRepetido($nombre, $id)) {
            $this->_ok = 2;
            $this->_mensaje = 'Ya existe un rol con ese nombre.';
            return [];
        }

        $tipoActual = PermisosRol::tipo($id);
        if (PermisosRol::hayCatalogo() && $tipo !== $tipoActual) {
            if ($id === self::ROL_INSCRIPCIONES) {
                $this->_mensaje = 'Este es el rol que reciben las inscripciones desde internet: es de contribuyentes y su tipo no se cambia.';
                return [];
            }
            $n = self::_cuentas($id);
            if ($n > 0) {
                $this->_mensaje = 'Hay ' . $n . ' cuenta' . ($n === 1 ? '' : 's') . ' con este rol: para cambiarle el tipo, '
                                . 'páselas antes a otro rol en Usuarios. Cambiarlo con cuentas les daría (o quitaría) de golpe '
                                . 'el acceso de funcionarios de la Alcaldía.';
                return [];
            }
            // Pasar a la Alcaldia hace contar sus permisos sobre CUALQUIER
            // contribuyente: quien no es administrador solo puede si ya los tiene.
            if ($tipo === PermisosRol::ALCALDIA && !PermisosRol::esAdministrador()) {
                foreach (PermisosRol::clavesGuardadas($id) as $c) {
                    if (!PermisosRol::tiene($c)) {
                        $this->_mensaje = 'Ese rol tiene permisos que el suyo no tiene: solo el administrador puede volverlo de la Alcaldía.';
                        return [];
                    }
                }
            }
        }

        if (PermisosRol::hayCatalogo()) {
            self::_con()->consultar(
                "UPDATE conf_rol SET rol_Nombre = ?, rol_Descripcion = NULLIF(?, ''), rol_Tipo = ? WHERE rol_Id = ?",
                [$nombre, $descripcion, $tipo, $id]
            );
            if ($tipo !== PermisosRol::ALCALDIA) {
                // Fuera de la Alcaldia las secciones de la Alcaldia no cuentan: no
                // se dejan guardadas para que no revivan si vuelve a serlo.
                self::_con()->consultar(
                    "DELETE p FROM conf_permisos p JOIN conf_submodulo s ON s.subMod_Id = p.per_IdSubmodulo
                      WHERE p.per_IdRol = ? AND (s.subMod_Clave LIKE 'alcaldia.%' OR s.subMod_Clave LIKE 'parametros.%'
                         OR s.subMod_Clave LIKE 'usuarios.%' OR s.subMod_Clave LIKE 'roles.%')",
                    [$id]
                );
            }
            PermisosRol::olvidar();
        } else {
            self::_con()->consultar(
                "UPDATE conf_rol SET rol_Nombre = ?, rol_Descripcion = NULLIF(?, '') WHERE rol_Id = ?",
                [$nombre, $descripcion, $id]
            );
        }
        $this->_ok = 1;
        $this->_mensaje = 'Rol actualizado.';
        return ['rol_Id' => $id];
    }

    /* ------------------------------------------------------- 3 consultar */
    private function _consultar()
    {
        $filtros = [];
        $params = [];
        if (!empty($_POST['id'])) { $filtros[] = 'r.rol_Id = ?'; $params[] = (int) $_POST['id']; }
        if (isset($_POST['estado']) && $_POST['estado'] !== '') { $filtros[] = 'r.rol_Estado = ?'; $params[] = (int) $_POST['estado']; }
        $donde = $filtros ? 'WHERE ' . implode(' AND ', $filtros) : '';
        $tipo = PermisosRol::hayCatalogo() ? 'r.rol_Tipo' : 'CAST(NULL AS VARCHAR(20)) AS rol_Tipo';
        $permisos = PermisosRol::hayCatalogo()
            ? "(SELECT COUNT(*) FROM conf_permisos p JOIN conf_submodulo s ON s.subMod_Id = p.per_IdSubmodulo
                 WHERE p.per_IdRol = r.rol_Id AND s.subMod_Clave IS NOT NULL AND ISNULL(p.per_Estado, 1) = 1)"
            : "(SELECT COUNT(*) FROM conf_permisos p WHERE p.per_IdRol = r.rol_Id)";

        $st = self::_con()->consultar(
            "SELECT r.rol_Id, r.rol_Nombre, r.rol_Descripcion, r.rol_Estado, $tipo,
                    (SELECT COUNT(*) FROM conf_usuarios u WHERE u.usu_Rol = r.rol_Id) AS rol_Cuentas,
                    $permisos AS rol_Permisos
               FROM conf_rol r $donde
              ORDER BY r.rol_Id",
            $params
        );
        $filas = [];
        while ($f = self::_con()->obnerFila($st)) { $filas[] = $f; }

        $total = 0;
        if (PermisosRol::hayCatalogo()) {
            $t = self::_con()->obnerFila(self::_con()->consultar(
                "SELECT COUNT(*) AS n FROM conf_submodulo WHERE subMod_Clave IS NOT NULL"
            ));
            $total = (int) ($t['n'] ?? 0);
        }
        $lista = [];
        foreach ($filas as $f) {
            $id = (int) $f['rol_Id'];
            $f['rol_Tipo'] = PermisosRol::tipo($id);
            $f['rol_TipoNombre'] = self::TIPOS_NOMBRE[$f['rol_Tipo']] ?? $f['rol_Tipo'];
            $f['rol_Admin'] = PermisosRol::esAdministrador($id) ? 1 : 0;
            $f['rol_TotalPermisos'] = $total;
            // Lo que la pantalla ofrece en cada fila (el servidor lo vuelve a exigir).
            $f['rol_Propio'] = $id === PermisosRol::rol() ? 1 : 0;
            $f['rol_Modificable'] = (PermisosRol::tiene('roles.editar') && self::_noPuedeModificar($id) === null) ? 1 : 0;
            $lista[] = $f;
        }
        $this->_ok = $lista ? 1 : 0;
        $this->_mensaje = $lista ? 'Roles listados' : 'No hay roles';
        return $lista;
    }

    /* ---------------------------------------------- 4 activar / inactivar */
    private function _estado()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $estado = (string) ($_POST['estado'] ?? '');
        if (!in_array($estado, ['0', '1'], true) || !self::_fila($id)) {
            $this->_mensaje = 'El rol no existe o el estado no es válido.';
            return [];
        }
        if (PermisosRol::esAdministrador($id)) {
            $this->_mensaje = 'El rol Administrador no se puede inactivar.';
            return [];
        }
        if ($id === PermisosRol::rol()) {
            $this->_mensaje = 'No puede cambiar el estado de su propio rol.';
            return [];
        }
        if (($motivo = self::_noPuedeModificar($id)) !== null) {
            $this->_mensaje = $motivo;
            return [];
        }
        if ($estado === '0' && $id === self::ROL_INSCRIPCIONES) {
            $this->_mensaje = 'Este es el rol que reciben las inscripciones desde internet: si se inactiva, nadie que se inscriba podría entrar.';
            return [];
        }
        if ($estado === '0') {
            $n = self::_cuentas($id, true);
            if ($n > 0) {
                $this->_mensaje = 'Hay ' . $n . ' cuenta' . ($n === 1 ? '' : 's') . ' activa' . ($n === 1 ? '' : 's')
                                . ' con este rol: si se inactiva, no podrán ingresar. Páselas antes a otro rol en Usuarios.';
                return [];
            }
        }
        self::_con()->consultar("UPDATE conf_rol SET rol_Estado = ? WHERE rol_Id = ?", [(int) $estado, $id]);
        $this->_ok = 1;
        $this->_mensaje = $estado === '1' ? 'Rol activado.' : 'Rol inactivado.';
        return ['rol_Id' => $id];
    }

    /* ------------------------------------------- 5 permisos de un rol */
    private function _permisosDelRol()
    {
        if (!PermisosRol::hayCatalogo()) {
            $this->_mensaje = 'Falta aplicar la migración 040 (roles y permisos).';
            return [];
        }
        $id = (int) ($_POST['id_rol'] ?? 0);
        $rol = self::_fila($id);
        if (!$rol) { $this->_mensaje = 'El rol no existe.'; return []; }

        $admin = PermisosRol::esAdministrador($id);
        $alcaldia = PermisosRol::esAlcaldia($id);
        $tipo = PermisosRol::tipo($id);
        // Lo guardado, este activo o no el rol: si saliera en blanco, guardar le
        // borraria todo sin querer.
        $activos = array_flip(PermisosRol::clavesGuardadas($id));

        $grupos = [];
        $requisitos = [];
        $st = self::_con()->consultar(
            "SELECT m.mod_Clave, m.mod_Nombre, s.subMod_Id, s.subMod_Clave, s.subMod_Nombre, s.subMod_Descripcion
               FROM conf_modulo m
               JOIN conf_submodulo s ON s.subMod_IdModulo = m.mod_Id
              WHERE m.mod_Clave IS NOT NULL AND s.subMod_Clave IS NOT NULL
              ORDER BY m.mod_Orden, s.subMod_Orden"
        );
        while ($f = self::_con()->obnerFila($st)) {
            $g = $f['mod_Clave'];
            $c = $f['subMod_Clave'];
            if (!isset($grupos[$g])) {
                $grupos[$g] = [
                    'clave'        => $g,
                    'nombre'       => $f['mod_Nombre'],
                    // Solo cuentan en un rol de la Alcaldia (PermisosRol).
                    'soloAlcaldia' => PermisosRol::esClaveDeAlcaldia($c) ? 1 : 0,
                    'permisos'     => [],
                ];
            }
            $grupos[$g]['permisos'][] = [
                'clave'       => $c,
                'nombre'      => $f['subMod_Nombre'],
                'descripcion' => $f['subMod_Descripcion'],
                'activo'      => ($admin || isset($activos[$c])) ? 1 : 0,
            ];
            $requisitos[$c] = PermisosRol::requisitos($c);
        }

        $motivo = PermisosRol::tiene('roles.permisos') ? self::_noPuedeModificar($id) : PermisosRol::mensaje('roles.permisos');

        // Quien no es administrador solo le da a un rol de la Alcaldia lo que
        // el mismo tiene: la pantalla deja apagados los demas.
        $propias = null;
        if (!PermisosRol::esAdministrador() && $alcaldia) {
            $propias = PermisosRol::claves();
        }

        $this->_ok = 1;
        $this->_mensaje = 'Permisos del rol';
        return [
            'rol' => [
                'id'          => $id,
                'nombre'      => $rol['rol_Nombre'],
                'descripcion' => $rol['rol_Descripcion'],
                'estado'      => (int) $rol['rol_Estado'],
                'tipo'        => $tipo,
                'tipoNombre'  => self::TIPOS_NOMBRE[$tipo] ?? $tipo,
                'admin'       => $admin ? 1 : 0,
                'alcaldia'    => $alcaldia ? 1 : 0,
                'cuentas'     => self::_cuentas($id),
                'propio'      => $id === PermisosRol::rol() ? 1 : 0,
            ],
            'grupos'      => array_values($grupos),
            'requisitos'  => $requisitos,
            'propias'     => $propias,
            'puedeEditar' => $motivo === null ? 1 : 0,
            'motivo'      => $motivo,
        ];
    }

    /* ------------------------------------------- 6 guardar permisos */
    private function _guardarPermisos()
    {
        if (!PermisosRol::hayCatalogo()) {
            $this->_mensaje = 'Falta aplicar la migración 040 (roles y permisos).';
            return [];
        }
        $id = (int) ($_POST['id_rol'] ?? 0);
        if (!self::_fila($id)) { $this->_mensaje = 'El rol no existe.'; return []; }
        if (($motivo = self::_noPuedeModificar($id)) !== null) {
            $this->_mensaje = $motivo;
            return [];
        }

        // Tiene que llegar la lista (vacia es "[]"): una peticion sin ella o con
        // un JSON roto borraba todos los permisos del rol.
        $pedidas = $_POST['claves'] ?? null;
        if (is_string($pedidas)) { $pedidas = json_decode($pedidas, true); }
        if (!is_array($pedidas)) {
            $this->_mensaje = 'No llegó la lista de permisos. Recargue la página e intente de nuevo.';
            return [];
        }
        $pedidas = array_values(array_unique(array_filter(array_map('strval', $pedidas))));

        $catalogo = [];
        $st = self::_con()->consultar(
            "SELECT subMod_Id, subMod_IdModulo, subMod_Clave FROM conf_submodulo WHERE subMod_Clave IS NOT NULL"
        );
        while ($f = self::_con()->obnerFila($st)) { $catalogo[$f['subMod_Clave']] = $f; }

        // Solo claves del catalogo; las de la Alcaldia, solo en roles de la
        // Alcaldia; y cada una con lo que necesita para servir.
        $alcaldia = PermisosRol::esAlcaldia($id);
        $ignoradas = 0;
        $validas = [];
        foreach ($pedidas as $c) {
            if (!isset($catalogo[$c])) { continue; }
            if (PermisosRol::esClaveDeAlcaldia($c) && !$alcaldia) { $ignoradas++; continue; }
            $validas[] = $c;
        }
        $validas = array_values(array_filter(
            PermisosRol::completarRequisitos($validas),
            function ($c) use ($catalogo, $alcaldia) {
                return isset($catalogo[$c]) && ($alcaldia || !PermisosRol::esClaveDeAlcaldia($c));
            }
        ));

        if (!PermisosRol::esAdministrador() && $alcaldia) {
            $ajenas = array_values(array_filter($validas, function ($c) { return !PermisosRol::tiene($c); }));
            if ($ajenas) {
                $this->_mensaje = 'No puede darle a un rol de la Alcaldía permisos que su rol no tiene ('
                                . implode(', ', array_slice($ajenas, 0, 5)) . (count($ajenas) > 5 ? '…' : '')
                                . '). Pídaselo al administrador.';
                return [];
            }
        }

        $con = self::_con();
        $con->begin();
        try {
            // Todas las filas del rol, tambien las de los botones de antes de
            // la 040: desde aqui el rol tiene exactamente lo que esta prendido.
            $con->consultar("DELETE FROM conf_permisos WHERE per_IdRol = ?", [$id]);
            foreach ($validas as $c) {
                $s = $catalogo[$c];
                $con->consultar(
                    "INSERT INTO conf_permisos (per_IdSubmodulo, per_IdRol, per_IdModulo, per_IdBoton, per_Estado)
                     VALUES (?, ?, ?, ?, 1)",
                    [(int) $s['subMod_Id'], $id, (int) $s['subMod_IdModulo'],
                     (int) ((string) $s['subMod_IdModulo'] . (string) $s['subMod_Id'])]
                );
            }
            $con->commit();
        } catch (\Throwable $e) {
            try { $con->rollback(); } catch (\Throwable $e2) { /* ya cerrada */ }
            throw $e;
        }
        PermisosRol::olvidar();

        error_log(sprintf('[roles] usuario %d guardo %d permisos del rol %d', PermisosRol::idUsuario(), count($validas), $id));

        $n = count($validas);
        $this->_ok = 1;
        $this->_mensaje = $n
            ? 'Permisos guardados: ' . $n . ' activo' . ($n === 1 ? '' : 's') . '.'
            : 'Permisos guardados. El rol quedó sin permisos: sus cuentas no podrán ingresar.';
        sort($validas);
        return ['activos' => $n, 'claves' => $validas, 'ignoradas' => $ignoradas];
    }
}

\erpsoftsas\ControladorRol::run();
