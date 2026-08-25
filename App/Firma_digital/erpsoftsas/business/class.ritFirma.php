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
    const VERSION = 'v2';

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
