<?php
namespace erpsoftsas;

/**
 * Firma del RIT.
 *
 * El cliente pidio (reunion del 2026-08-19) que el RIT se firme al inscribirse
 * y en cada novedad, igual que se firman las declaraciones, y que la casilla
 * 30 del formulario impreso -"Contribuyente o Representante Legal", hoy en
 * blanco- salga estampada.
 *
 * EL PROBLEMA QUE RESUELVE ESTA CLASE
 *
 * Una declaracion presentada ya no cambia, asi que para ella basta con anotar
 * "fulano firmo". El RIT si cambia: el formulario se llama, literalmente, "de
 * inscripcion Y/O NOVEDADES". Si solo se anotara quien firmo, bastaria que el
 * contribuyente cambiara la direccion al dia siguiente para que el PDF
 * siguiera estampando una firma que ya no ampara lo que esta impreso.
 *
 * Por eso cada firma guarda el HASH del contenido firmado. Al imprimir se
 * recalcula el hash de lo que se va a imprimir y solo se estampa si coincide.
 * Cualquier cambio en el RIT invalida la firma por su cuenta, sin que nadie
 * tenga que acordarse de invalidarla: es la propiedad que hace que esto sea
 * una firma y no un adorno.
 *
 * El hash cubre EXACTAMENTE lo que el formulario imprime -datos del
 * contribuyente, actividades y establecimientos-, ni mas ni menos. De mas,
 * invalidaria firmas por cambios que el papel no muestra; de menos, dejaria
 * pasar cambios visibles sin volver a firmar.
 */
class RitFirma
{
    /** Version del formato del hash. Si algun dia cambia QUE se firma, subir
     *  este numero invalida las firmas viejas a proposito, en vez de dejar
     *  hashes viejos y nuevos conviviendo sin poder distinguirlos. */
    const VERSION = 'v3';

    /**
     * Los documentos que hay que haber cargado para poder firmar el RIT.
     *
     * La clave es el anx_Tipo con que se guardan; el valor, como se le nombra
     * al contribuyente. El uso de suelo queda fuera a proposito: el cliente lo
     * pidio opcional.
     */
    const DOCUMENTOS_OBLIGATORIOS = [
        'rut'    => 'RUT',
        'camara' => 'Cámara de comercio o acta de constitución',
        'cedula' => 'Documento de identificación del representante legal o propietario',
    ];

    /**
     * Cuales de los documentos obligatorios le faltan al contribuyente.
     *
     * BLOQUEAN GUARDAR Y FIRMAR.
     *
     * El 2026-08-26 el cliente pidio que sin los soportes no se pudiera
     * firmar, y guardar se permitia para diligenciar en varias sesiones. En la
     * revision del 2026-09-25 lo cerro del todo: el RIT no se guarda incompleto.
     * Guardar y firmar lo comprueban con faltantes(), que usa esta lista.
     *
     * Va aqui, junto al hash, porque es tambien una regla de la FIRMA. El
     * navegador lo comprueba para avisar antes de gastar un OTP, pero esa
     * comprobacion se salta desde la consola y esta no.
     *
     * @return array<string,string> tipo => etiqueta, vacio si no falta ninguno
     */
    public static function documentosFaltantes($con, $idContribuyente)
    {
        $idContribuyente = (int) $idContribuyente;
        if ($idContribuyente <= 0) { return self::DOCUMENTOS_OBLIGATORIOS; }

        $stmt = $con->consultar(
            "SELECT DISTINCT anx_Tipo
               FROM ind_establecimiento_anexos
              WHERE anx_IdContribuyente = ?
                AND anx_Activo = 1
                AND anx_Tipo IS NOT NULL",
            [$idContribuyente]
        );

        $cargados = [];
        while ($fila = $con->obnerFila($stmt)) {
            $cargados[strtolower(trim((string) $fila['anx_Tipo']))] = true;
        }

        $faltan = [];
        foreach (self::DOCUMENTOS_OBLIGATORIOS as $tipo => $etiqueta) {
            if (!isset($cargados[$tipo])) { $faltan[$tipo] = $etiqueta; }
        }
        return $faltan;
    }

    /**
     * Lo que le falta al RIT, campos y documentos (revisión del cliente
     * 2026-09-25), en frases para mostrarle al usuario.
     *
     * Una sola regla para GUARDAR y para FIRMAR. Al guardar, $datos es lo que
     * llega del formulario (los nombres del POST son los de las columnas); sin
     * $datos se mira lo que ya está en la base, que es lo que se firma: así un
     * RIT guardado a medias antes de esta regla no se puede firmar incompleto.
     *
     * @return string[] vacío si está completo
     */
    public static function faltantes($con, $idContribuyente, ?array $datos = null)
    {
        $idContribuyente = (int) $idContribuyente;
        $campos = [
            'ind_Persona'                => 'tipo de persona',
            'ind_PrimerNombre'           => 'primer nombre o razón social',
            'ind_PrimerApellido'         => 'primer apellido',
            'ind_Direccion'              => 'dirección de notificación',
            'ind_IdCiudad'               => 'departamento y municipio de residencia',
            'ind_Telefono'               => 'teléfono',
            'ind_Email'                  => 'correo electrónico de notificación',
            'ind_Fecha_inicio'           => 'fecha de inicio de actividades en el municipio',
            'ind_Cedula_representante'   => 'cédula del representante legal o propietario',
            'ind_Nombre_representante'   => 'nombre del representante legal o propietario',
            'ind_Email_representante'    => 'correo del representante legal o propietario',
            'ind_Telefono_representante' => 'celular del representante legal o propietario',
        ];

        // La identidad sale siempre de la base: el RIT no la edita.
        $columnas = ['ind_IdTipoDocumento', 'ind_NumeroIdentificacion'];
        if ($datos === null) {
            $columnas = array_merge($columnas, array_keys($campos), ['ind_RegimenTributario']);
        }
        $fila = $con->obnerFila($con->consultar(
            "SELECT " . implode(', ', $columnas) . " FROM ind_contribuyentes WHERE ind_Id = ?",
            [$idContribuyente]
        )) ?: [];
        if ($datos === null) { $datos = $fila; }

        // Lo que viene de la base puede ser DateTime, número o NULL; una fecha
        // 1900-01-01 es un vacío que SQL Server guardó como fecha.
        $v = function ($campo) use ($datos) {
            $x = $datos[$campo] ?? '';
            if ($x instanceof \DateTimeInterface) { $x = $x->format('Y-m-d'); }
            $x = trim((string) $x);
            return strncmp($x, '1900-01-01', 10) === 0 ? '' : $x;
        };

        $faltan = [];
        // La columna del número no admite NULL: "sin número" queda como 0.
        if (empty($fila['ind_IdTipoDocumento']) || (int) ($fila['ind_NumeroIdentificacion'] ?? 0) <= 0) {
            $faltan[] = 'tipo y número de documento (los corrige la Alcaldía en Contribuyentes)';
        }

        if ($v('ind_Persona') === '2') {
            unset($campos['ind_PrimerApellido']);   // persona jurídica
        }
        foreach ($campos as $campo => $rotulo) {
            $valor = $v($campo);
            // Un teléfono sin un solo dígito ("N/A") no es un teléfono: el de
            // notificación se guarda solo con sus dígitos y quedaría en NULL.
            if (in_array($campo, ['ind_Telefono', 'ind_Telefono_representante'], true)) {
                $valor = preg_replace('/\D/', '', $valor);
            }
            // En la base estas dos son enteros: 0 es "sin escoger", como el vacío
            // del formulario. Y el tipo de persona solo es natural (1) o jurídica (2).
            if ($campo === 'ind_IdCiudad' && $valor === '0') { $valor = ''; }
            if ($campo === 'ind_Persona' && !in_array($valor, ['1', '2'], true)) { $valor = ''; }
            if ($valor === '') { $faltan[] = $rotulo; }
        }

        // Régimen e IVA viajan juntos, separados por coma; de cada grupo, uno.
        $marcadas = array_map('trim', explode(',', $v('ind_RegimenTributario')));
        $grupos = [
            'régimen tributario (ordinario, simple o especial)' => ['ORDINARIO', 'SIMPLE', 'ESPECIAL'],
            'responsable o no responsable de IVA'               => ['RESP_IVA', 'NO_RESP_IVA'],
        ];
        foreach ($grupos as $rotulo => $opciones) {
            $n = count(array_intersect($opciones, $marcadas));
            if ($n === 0) { $faltan[] = $rotulo; }
            if ($n > 1)   { $faltan[] = 'una sola opción en ' . $rotulo; }
        }

        foreach (self::documentosFaltantes($con, $idContribuyente) as $tipo => $etiqueta) {
            $faltan[] = 'el documento "' . $etiqueta . '"'
                      . ($tipo === 'camara' ? ' (si no tiene cámara de comercio, comuníquese con la Alcaldía)' : '');
        }

        return $faltan;
    }

    /**
     * Los datos del RIT que quedan amparados por la firma, en un orden fijo.
     * El orden importa: json_encode de un arreglo asociativo respeta el orden
     * de insercion, y dos ejecuciones tienen que producir el mismo texto.
     */
    public static function datosFirmables($con, $idContribuyente)
    {
        $idContribuyente = (int) $idContribuyente;

        $campos = [
            'ind_NumeroIdentificacion', 'ind_DV', 'ind_IdTipoDocumento',
            'ind_PrimerNombre', 'ind_SegundoNombre', 'ind_PrimerApellido', 'ind_SegundoApellido',
            'ind_Direccion', 'ind_IdCiudad', 'ind_Persona', 'ind_IdRegimen',
            'ind_Telefono', 'ind_Email',
            'ind_Matricula', 'ind_Fecha_matricula', 'ind_Fecha_inicio', 'ind_Ind_camara_comercio',
            'ind_Cedula_representante', 'ind_Nombre_representante', 'ind_Email_representante',
            'ind_Telefono_representante',
            'ind_CedulaContador', 'ind_NombreContador', 'ind_TarjetaProfContador', 'ind_EmailContador',
            'ind_CedulaRevisor', 'ind_NombreRevisor', 'ind_TarjetaProfRevisor', 'ind_EmailRevisor',
            'ind_Rut', 'ind_Rut_segundo', 'ind_Rut_tercero',
            'ind_Autorizacion',
            // Regimen y responsabilidades (migracion 014) y las dos exenciones
            // que subieron del establecimiento (016). Van en el hash porque el
            // formulario impreso las muestra: si no estuvieran, se podrian
            // cambiar despues de firmar sin que la firma se invalidara.
            'ind_RegimenTributario', 'ind_Responsabilidades',
            'ind_NoSujetas', 'ind_SinAvisosTableros',
            // El cese subio a la persona con la migracion 019, y el formulario
            // lo imprime: si no estuviera aqui, se podria declarar un cese
            // despues de firmar sin que la firma se invalidara.
            'ind_FechaCese', 'ind_CausalCese', 'ind_ObservacionCese',
        ];

        $fila = $con->obnerFila($con->consultar(
            'SELECT ' . implode(', ', $campos) . ' FROM ind_contribuyentes WHERE ind_Id = ?',
            [$idContribuyente]
        ));

        if (!$fila) { return null; }

        $datos = ['_v' => self::VERSION, 'contribuyente' => []];
        foreach ($campos as $c) {
            $datos['contribuyente'][$c] = self::_texto($fila[$c] ?? null);
        }

        // Actividades economicas (tabla nueva, sin año: migraciones 005 y 007).
        $datos['actividades'] = [];
        $st = $con->consultar(
            'SELECT atc_IdCodigoActividad FROM ind_actividad_contribuyente
              WHERE atc_IdContribuyente = ? ORDER BY atc_IdCodigoActividad',
            [$idContribuyente]
        );
        while ($a = $con->obnerFila($st)) {
            $datos['actividades'][] = (string) $a['atc_IdCodigoActividad'];
        }

        // Establecimientos: el formulario los lista, y el cese se imprime.
        $datos['establecimientos'] = [];
        $st = $con->consultar(
            'SELECT est_Id, est_Codigo, est_Nombre, est_Direccion, est_Barrio,
                    est_Activo, est_Fecha_cierre, est_Causal
               FROM ind_establecimientos
              WHERE est_IdContribuyente = ? ORDER BY est_Id',
            [$idContribuyente]
        );
        while ($e = $con->obnerFila($st)) {
            $datos['establecimientos'][] = array_map(
                [self::class, '_texto'],
                $e
            );
        }

        return $datos;
    }

    /**
     * Normaliza a texto. Sin esto el hash seria inestable: sqlsrv devuelve las
     * fechas como objetos DateTime y los numeros unas veces como int y otras
     * como string, de modo que el mismo contenido podria producir dos hashes
     * distintos y la firma se "caeria" sin que nada hubiera cambiado.
     */
    private static function _texto($v)
    {
        if ($v === null)                 { return ''; }
        if ($v instanceof \DateTime)     { return $v->format('Y-m-d H:i:s'); }
        if (is_bool($v))                 { return $v ? '1' : '0'; }
        return trim((string) $v);
    }

    /** Huella del contenido del RIT. */
    public static function hash($datos)
    {
        if (!$datos) { return ''; }
        return hash('sha256', json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Huella del RIT tal como esta AHORA en la base. */
    public static function hashActual($con, $idContribuyente)
    {
        return self::hash(self::datosFirmables($con, $idContribuyente));
    }

    /**
     * Firma VIGENTE del RIT: la ultima firma, y solo si ampara el contenido
     * actual. Si el contribuyente cambio algo despues de firmar, devuelve null
     * -el RIT vuelve a estar sin firmar-.
     *
     * Devuelve tambien la ultima firma aunque este vencida, en 'desactualizada',
     * para que la pantalla pueda decir "firmado el X, pero hubo cambios
     * despues" en vez de un simple "sin firmar" que se ve como si nunca se
     * hubiera firmado.
     */
    public static function firmaVigente($con, $idContribuyente)
    {
        $idContribuyente = (int) $idContribuyente;
        $actual = self::hashActual($con, $idContribuyente);

        $ultima = $con->obnerFila($con->consultar(
            'SELECT TOP 1 rif_Id, rif_IdUsuario, rif_NombreUsuario, rif_EmailUsuario,
                          rif_Hash, rif_Opcion, rif_FechaHora
               FROM ind_rit_firmas
              WHERE rif_IdContribuyente = ?
              ORDER BY rif_FechaHora DESC, rif_Id DESC',
            [$idContribuyente]
        ));

        if (!$ultima) {
            return ['firmado' => false, 'firma' => null, 'desactualizada' => null];
        }

        if (hash_equals((string) $ultima['rif_Hash'], $actual)) {
            return ['firmado' => true, 'firma' => $ultima, 'desactualizada' => null];
        }

        return ['firmado' => false, 'firma' => null, 'desactualizada' => $ultima];
    }
}
