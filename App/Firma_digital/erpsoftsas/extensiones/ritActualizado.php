<?php
require_once('tcpdf/tcpdf.php');

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/class.conexionSqlServer.php';
// Trae ControladorAnexos::puedeOperarSobreEstablecimiento(), ya blindado
// contra auto-ejecutarse al incluirse desde otro archivo (mismo patron que
// usa extensiones/anexo.php).
include_once SERVER . '/business/controller/class.anexos.php';

use ConexionMysqlUsuariosSqlServer\ConexionSQLServer;

class ICAPdf extends TCPDF {
    public function Header(){}
    public function Footer(){}
}

$con = \ConexionMysqlUsuariosSqlServer\ConexionSQLServer::getInstance();

/* ===========================
   Sesion y permiso
   ----------------------------------------------------------------------
   Este archivo no comprobaba NADA: ni sesion, ni de quien era el
   establecimiento/contribuyente pedido. Confirmado en vivo: un curl sin
   ninguna cookie descargaba el certificado completo (NIT, direccion,
   telefono, representante legal, contador, revisor con sus cedulas) de
   CUALQUIER contribuyente, solo cambiando un entero secuencial en la URL.
   Mismo chequeo que ya usa extensiones/anexo.php para los anexos.
   =========================== */
if (session_status() === PHP_SESSION_NONE) { @session_start(); }
if (empty($_SESSION['id_usuario'])) {
    http_response_code(401);
    exit('Debe iniciar sesión para descargar este certificado.');
}

// El certificado se puede pedir de dos maneras:
//   ?codigo=<est_Id>          -> como siempre, desde un establecimiento
//   ?contribuyente=<ind_Id>   -> desde el RIT, que ahora es del contribuyente
// En el segundo caso se toma su establecimiento mas antiguo como base para
// los datos de ubicacion; los datos de la PERSONA (matricula, representante,
// contador, revisor) ya no salen del establecimiento sino de
// ind_contribuyentes, que es donde los dejo la migracion 003.
//
// 'codigo' se valida como numerico ANTES de castear: pasarlo tal cual a un
// parametro ligado contra una columna int (est_Id) hacia que un valor no
// numerico reventara con una excepcion sin capturar (500 en blanco) en vez
// del mensaje claro que ya se usa para los demas casos de esta seccion.

/**
 * ¿El RIT de este contribuyente es de quien tiene la sesion abierta?
 *
 * Hace falta desde que el establecimiento dejo de ser obligatorio para
 * imprimir: sin el, no hay establecimiento cuyo dueño comprobar, y sin esta
 * comprobacion bastaria cambiar el numero de la direccion para descargar el
 * RIT de otro contribuyente -datos con reserva tributaria-.
 *
 * Mismo criterio que ControladorAnexos::puedeOperarSobreEstablecimiento: los
 * roles de Alcaldia (1 y 2) pueden ver cualquiera, y el resto solo el suyo,
 * cruzado por numero de documento porque no hay columna que ate el usuario al
 * contribuyente.
 */
/**
 * Primera fecha que traiga valor, ya formateada para el formulario.
 *
 * SQL Server las devuelve como DateTime, pero una base sin migrar puede
 * traerlas como texto, asi que se contempla el caso en vez de llamar
 * ->format() a ciegas y reventar con un fatal.
 */
function _fecha(...$candidatas)
{
    foreach ($candidatas as $f) {
        if (empty($f)) { continue; }

        $texto = ($f instanceof \DateTimeInterface)
            ? $f->format('d-m-Y')
            : (($t = strtotime((string) $f)) !== false ? date('d-m-Y', $t) : null);

        if ($texto === null) { continue; }

        /*
         * 01-01-1900 NO ES UNA FECHA, ES UN HUECO.
         *
         * SQL Server guarda ese valor cuando le llega una cadena vacia en una
         * columna de fecha, y en esta base hay establecimientos con
         * est_Fecha_cierre = 1900-01-01 sin haber cesado nada. El guard que
         * decide si se pinta el bloque de cese SI lo descartaba, pero esta
         * funcion no, asi que la casilla 27 salia con «01-01-1900»: el
         * certificado afirmaba que el negocio cerro en 1900.
         * Medido en el contribuyente 30 el 2026-09-01.
         */
        if ($texto === '01-01-1900') { continue; }

        return $texto;
    }
    return '';
}

function _ritEsDeLaSesion($idContribuyente, $con)
{
    if (session_status() === PHP_SESSION_NONE) { @session_start(); }

    if (empty($_SESSION['id_usuario'])) { return false; }

    $rol = isset($_SESSION['id_Rol']) ? (int) $_SESSION['id_Rol'] : 0;
    if (in_array($rol, [1, 2], true)) { return true; }

    $propio = $con->obnerFila($con->consultar(
        "SELECT c.ind_Id
           FROM ind_contribuyentes c
           INNER JOIN conf_usuarios u ON u.usu_NumeroDocumento = c.ind_NumeroIdentificacion
          WHERE u.usu_Id = ? AND c.ind_Id = ?",
        [(int) $_SESSION['id_usuario'], (int) $idContribuyente]
    ));

    return (bool) $propio;
}

$idEstablecimientoCrudo = $_GET['codigo'] ?? null;
if ($idEstablecimientoCrudo !== null && !ctype_digit((string) $idEstablecimientoCrudo)) {
    exit('El identificador del registro no es válido.');
}
$idEstablecimiento = $idEstablecimientoCrudo !== null ? (int) $idEstablecimientoCrudo : null;
$idContribuyente    = isset($_GET['contribuyente']) ? (int) $_GET['contribuyente'] : null;

if (!$idEstablecimiento && $idContribuyente) {
    $fila = $con->obnerFila($con->consultar(
        "SELECT TOP 1 est_Id FROM ind_establecimientos
          WHERE est_IdContribuyente = ? ORDER BY est_Id",
        [$idContribuyente]
    ));
    $idEstablecimiento = $fila['est_Id'] ?? null;

    /*
     * Aqui se cortaba con "este contribuyente todavia no tiene
     * establecimientos registrados". Reportado el 2026-08-25: un contribuyente
     * recien inscrito no podia descargar su RIT.
     *
     * Era una herencia del diseño anterior, cuando el RIT colgaba del
     * establecimiento. Desde que el RIT es del CONTRIBUYENTE, el
     * establecimiento solo aporta datos de ubicacion del local, y no tenerlo
     * no puede impedir imprimir el registro de la persona: la inscripcion en
     * el RIT es justamente el primer tramite, antes de registrar local alguno.
     *
     * Ahora se sigue sin establecimiento y el formulario sale con esa parte
     * en blanco.
     */
}

if (!$idEstablecimiento && !$idContribuyente) {
    exit('No se indicó de qué registro generar el certificado.');
}

// Si se entro por ?codigo=, se resuelve de quien es ese establecimiento; hace
// falta mas abajo para consultar al contribuyente, que ahora es el ancla.
if ($idEstablecimiento && !$idContribuyente) {
    $fila = $con->obnerFila($con->consultar(
        "SELECT est_IdContribuyente FROM ind_establecimientos WHERE est_Id = ?",
        [$idEstablecimiento]
    ));
    $idContribuyente = $fila['est_IdContribuyente'] ?? null;

    if (!$idContribuyente) {
        exit('No existe información para el registro solicitado.');
    }
}

/*
 * SE COMPRUEBA EL CONTRIBUYENTE QUE SE VA A IMPRIMIR. SIEMPRE.
 *
 * Aqui habia un bypass. La comprobacion se hacia SOLO sobre el establecimiento
 * cuando venia ?codigo=, dando por hecho que «si se entro por ?contribuyente=,
 * ese establecimiento ya salio filtrado por el». Eso es cierto cuando llega UN
 * parametro, y falso cuando llegan LOS DOS: con
 *
 *     ?codigo=<establecimiento propio>&contribuyente=<ajeno>
 *
 * el bloque de arriba no resuelve el contribuyente -solo lo hace si viene
 * vacio-, asi que se autorizaba mirando un establecimiento propio y se
 * imprimia el RIT de otro. Reproducido el 2026-09-01: ?contribuyente=36 daba
 * 403, y ?codigo=43&contribuyente=36 devolvia 200 con los datos del 36.
 *
 * La regla correcta no depende de por donde se entro: lo que hay que autorizar
 * es el DOCUMENTO QUE SE VA A EMITIR, y ese es siempre el del contribuyente
 * resuelto. Si ademas hay establecimiento, tiene que ser suyo y de la sesion.
 */
$permitido = _ritEsDeLaSesion($idContribuyente, $con);

if ($permitido && $idEstablecimiento) {
    // El establecimiento tambien tiene que ser de la sesion...
    $permitido = \erpsoftsas\ControladorAnexos::puedeOperarSobreEstablecimiento($idEstablecimiento, $con);

    // ...y ademas del contribuyente que se esta imprimiendo, o los dos
    // parametros se estarian contradiciendo y el papel mezclaria dos registros.
    if ($permitido) {
        $coherente = $con->obnerFila($con->consultar(
            "SELECT est_Id FROM ind_establecimientos WHERE est_Id = ? AND est_IdContribuyente = ?",
            [(int) $idEstablecimiento, (int) $idContribuyente]
        ));
        $permitido = (bool) $coherente;
    }
}

if (!$permitido) {
    http_response_code(403);
    exit('No tiene permiso para ver este registro.');
}

// Oficio 8.5 x 13 pulgadas = 215.9 x 330.2 mm. Es el mismo tamaño que ya usan
// declaracion.php y liquidacion.php; el certificado era el unico que seguia en
// carta, y por eso se veia mas apretado que los otros dos documentos.
$pdf = new ICAPdf('P','mm',array(215.9, 330.2),true,'UTF-8',false);
$pdf->SetMargins(8,8,8);
$pdf->AddPage();

$pdf->SetFont('helvetica','',8);

//$sql = "SELECT * FROM ind_establecimientos WHERE est_Id = ?";
// Mismo patron que declaracion.php: si el config del municipio no trae estos
// datos, el certificado sale con el campo vacio en vez de reventar. El NIT NO
// hereda el de Paipa a proposito: un NIT ajeno en un certificado tributario es
// peor que uno en blanco.
if (!defined('MUNICIPIO_DEPARTAMENTO')) define('MUNICIPIO_DEPARTAMENTO', '');
if (!defined('MUNICIPIO_NIT'))          define('MUNICIPIO_NIT', '');
if (!defined('MUNICIPIO_DIRECCION'))    define('MUNICIPIO_DIRECCION', '');


$sql = "
SELECT
    e.*,
    c.*,
    ciu.ciu_Nombre,
    ciu.ciu_Departamento
FROM ind_contribuyentes c
LEFT JOIN ind_establecimientos e
    ON e.est_Id = ?
LEFT JOIN conf_ciudades ciu
    ON ciu.ciu_Id = c.ind_IdCiudad
WHERE c.ind_Id = ?
";

// El ancla es el CONTRIBUYENTE y el establecimiento entra por LEFT JOIN. Antes
// era al reves y por eso un contribuyente sin locales no tenia RIT que
// imprimir. Sin establecimiento, las columnas e.* llegan en nulo y esa parte
// del formulario sale en blanco, que es lo correcto.
$row = $con->obnerFila($con->consultar($sql, [$idEstablecimiento, $idContribuyente]));

// Ver la nota de "opcion de uso" mas abajo. Se resuelven aqui, sobre el
// contribuyente, porque puede no haber establecimiento del que leerlas.
/*
 * "Inscripcion" solo la PRIMERA vez.
 *
 * Corregido el 2026-08-26: "no deberia salir inscripcion sino actualizacion;
 * si apenas inicia es inscripcion, por el contrario actualizacion". La regla
 * anterior miraba si el RIT se habia FIRMADO alguna vez, y por eso un registro
 * que llevaba tiempo en el sistema pero sin firmar seguia saliendo como
 * inscripcion.
 *
 * Ahora se mira si el RIT ya EXISTE -ind_RIT_FechaCreacion, que se llena la
 * primera vez que se diligencia-, que es lo que distingue inscribirse de
 * reportar una novedad. La firma se sigue teniendo en cuenta como respaldo:
 * un RIT firmado esta creado por definicion, aunque la fecha no se hubiera
 * registrado en su momento.
 */
$previo = $con->obnerFila($con->consultar(
    "SELECT TOP 1 1 AS x
       FROM ind_contribuyentes c
      WHERE c.ind_Id = ?
        AND (c.ind_RIT_FechaCreacion IS NOT NULL
             OR EXISTS (SELECT 1 FROM ind_rit_firmas f WHERE f.rif_IdContribuyente = c.ind_Id))",
    [$idContribuyente]
));
$ritYaFormalizado = (bool) $previo;

/*
 * Se descarta 1900-01-01 ademas de NULL: SQL Server convierte una cadena
 * vacia en esa fecha al guardarla en una columna de tipo fecha, asi que un
 * cese que se limpio puede quedar con ese valor en vez de en nulo. Sin esta
 * guarda el formulario marcaba "Cese de Actividades" en contribuyentes que no
 * han cesado nada -visto en la base local, establecimiento 43-. La misma
 * defensa ya existe en class.contribuyentes.php al leer el cese.
 */
$filaCese = $con->obnerFila($con->consultar(
    "SELECT TOP 1 c.ind_Id
       FROM ind_contribuyentes c
      WHERE c.ind_Id = ?
        AND (
              -- el cese de la persona (migracion 019)
              c.ind_FechaCese IS NOT NULL
              -- o, en una base sin migrar, el que quedo en alguno de sus locales
              OR EXISTS (SELECT 1 FROM ind_establecimientos e
                          WHERE e.est_IdContribuyente = c.ind_Id
                            AND e.est_Fecha_cierre IS NOT NULL
                            AND CONVERT(DATE, e.est_Fecha_cierre) <> '1900-01-01')
            )",
    [$idContribuyente]
));
$hayCese = (bool) $filaCese;

if (!$row) {
    exit('No existe información para el registro solicitado.');
}

// Punto 18 dice "Actividades Economicas DEL CONTRIBUYENTE" -en plural, de
// todos sus establecimientos-, pero esta consulta estaba anclada a
// a.ace_IdEstablecimiento = e.est_Id: un contribuyente con actividades
// repartidas en mas de un establecimiento perdia en silencio las de
// cualquiera que no fuera el "ancla" resuelta arriba. Se agrega por
// est_IdContribuyente, mismo patron que ya usa _consultarRIT() en
// class.contribuyentes.php y el bloque de "Establecimientos del
// Contribuyente" mas abajo en este mismo archivo.
/*
 * Las actividades salen de ind_actividad_contribuyente, NO de la vieja
 * ind_actividad_establecimiento.
 *
 * Las migraciones 005 y 007 subieron las actividades del establecimiento al
 * contribuyente y les quitaron el año: el RIT es el registro VIGENTE de a que
 * se dedica la persona, y el historico por año ya vive donde corresponde, en
 * las actividades que cada declaracion congela al liquidarse.
 *
 * Este PDF se quedo leyendo la tabla vieja. Hoy las dos coinciden porque la
 * migracion copio el contenido y nadie ha editado desde entonces, pero la
 * pantalla del RIT ya solo escribe en la nueva: la primera edicion de
 * actividades habria hecho que el formulario impreso mostrara una lista
 * distinta a la que ve el contribuyente en pantalla. La tabla vieja quedo sin
 * nadie que le escriba; no se borra -misma regla que las migraciones-, pero
 * deja de leerse desde aqui.
 */
$actividades = [];
$stmtAct = $con->consultar(
    "SELECT ca.acc_Codigo, ca.acc_Nombre
       FROM ind_actividad_contribuyente a
       LEFT JOIN ind_actividadescomercio ca ON ca.acc_Id = a.atc_IdCodigoActividad
      WHERE a.atc_IdContribuyente = ?
      ORDER BY ca.acc_Codigo",
    /*
     * ind_Id, NO est_IdContribuyente.
     *
     * Esta consulta ancla en ind_contribuyentes y el establecimiento entra por
     * LEFT JOIN, asi que est_IdContribuyente es NULL en cuanto el contribuyente
     * no tiene local. Con NULL no casa ninguna fila y la casilla 18 salia
     * «No registra actividades economicas» aunque las tuviera -es decir, en el
     * caso que el RIT a nivel de contribuyente vino justamente a habilitar-.
     * Mismo error que tenia unas lineas mas abajo la busqueda de la firma.
     */
    [$row['ind_Id']]
);
while ($a = $con->obnerFila($stmtAct)) {
    if (!empty($a['acc_Codigo'])) {
        $actividades[] = ['codigo' => $a['acc_Codigo'], 'nombre' => $a['acc_Nombre']];
    }
}

/*
 * FIRMA DEL RIT (casilla 30)
 *
 * Pedido del cliente el 2026-08-19: la casilla 30 -"Contribuyente o
 * Representante Legal"- salia siempre en blanco, mientras la 31 ya traia la
 * firma del funcionario. Se estampa igual que en las declaraciones: sello,
 * nombre de quien firmo y fecha.
 *
 * firmaVigente() solo la da por buena si el hash guardado coincide con el
 * contenido de HOY. Si el contribuyente cambio algo del RIT despues de
 * firmarlo, aqui llega 'firmado' en false y el formulario vuelve a imprimirse
 * sin firma -que es lo correcto: la firma amparaba otro contenido-.
 */
// Misma guarda que declaracion.php: si el config del municipio no la trae,
// se cae al sello por defecto en vez de reventar con constante indefinida
// (en PHP 8 eso es error fatal, no aviso).
if (!defined('MUNICIPIO_SELLO_FIRMA')) define('MUNICIPIO_SELLO_FIRMA', 'Sello_Firma.png');
include_once dirname(__DIR__) . '/business/class.ritFirma.php';
/*
 * La firma se busca por el CONTRIBUYENTE, no por est_IdContribuyente.
 *
 * Esta consulta ancla en ind_contribuyentes y el establecimiento entra por
 * LEFT JOIN, asi que est_IdContribuyente es NULL en cuanto el contribuyente no
 * tiene establecimiento -caso normal desde que el RIT y la declaracion son del
 * contribuyente-. Con NULL el (int) daba 0, firmaVigente no encontraba nada y
 * el formulario salia SIN FIRMA aunque estuviera firmado. Medido en el
 * contribuyente 36, que no tiene establecimientos.
 */
$estadoFirmaRit = \erpsoftsas\RitFirma::firmaVigente($con, (int) $row['ind_Id']);
$firmaRit       = $estadoFirmaRit['firmado'] ? $estadoFirmaRit['firma'] : null;

$fechaFirmaRit = '';
if ($firmaRit) {
    $f = $firmaRit['rif_FechaHora'];
    $fechaFirmaRit = ($f instanceof \DateTime) ? $f->format('d/m/Y h:i a') : (string) $f;
}

$nombreCompleto = trim(
    $row['ind_PrimerNombre'].' '.
    $row['ind_SegundoNombre'].' '.
    $row['ind_PrimerApellido'].' '.
    $row['ind_SegundoApellido']
);


// Todo campo de texto libre que pueda venir de datos guardados por un
// contribuyente (varios de estos los escribe directamente el formulario del
// RIT, sin ser administrador) se escapa aqui, UNA sola vez, antes de entrar a
// $d[]. Antes se interpolaban crudos dentro de writeHTML(): un
// ind_Nombre_representante como '</td></tr><tr><td colspan=99>HACKED'
// rompia literalmente la tabla del certificado oficial -reproducido y
// confirmado, no es un riesgo teorico-.
$esc = function ($v) {
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
};

/* ---------------------------------------------------------------------------
   El usuario que tramita el RIT, para la casilla 31.

   Se prefiere el firmante sobre el de la sesion: si el formulario ya esta
   firmado, quien lo tramito es quien firmo, aunque hoy lo imprima otro.
   --------------------------------------------------------------------------- */
/* ---------------------------------------------------------------------------
   Los documentos adjuntos, para que el formulario diga cuales se aportaron.

   El cliente pregunto el 2026-09-01 «¿donde quedan los archivos que se suben?,
   esta duda es porque subieron el RUT y ya no se ve reflejado». El archivo
   estaba: guardado, registrado y visible en la pantalla del RIT. Lo que no
   aparecia por ningun lado era EN EL PAPEL, y el papel es lo que se lleva a
   ventanilla. Quien lo recibe no tenia como saber que se aporto.

   Se listan solo los vigentes (anx_Activo = 1). Se imprime el tipo y el nombre
   del archivo, no la ruta: la ruta no le sirve a nadie en un papel y ademas
   revela como se guardan.
   --------------------------------------------------------------------------- */
$anexosTexto = '';

$resAnexos = $con->consultar(
    "SELECT anx_Tipo, anx_NombreOriginal
       FROM ind_establecimiento_anexos
      WHERE anx_IdContribuyente = ?
        AND anx_Activo = 1
      ORDER BY anx_Id",
    [(int) $row['ind_Id']]
);

$etiquetasAnexo = [
    'rut'      => 'RUT',
    'camara'   => 'Camara de comercio',
    'cedula'   => 'Documento de identificacion',
    'usosuelo' => 'Uso de suelo',
    'cese'     => 'Cese',
    'otro'     => 'Otro',
];

$listaAnexos = [];
while ($fa = $con->obnerFila($resAnexos)) {
    $tipo = strtolower(trim((string) $fa['anx_Tipo']));
    $listaAnexos[] = ($etiquetasAnexo[$tipo] ?? ($fa['anx_Tipo'] ?: 'Documento'))
                   . ': ' . $fa['anx_NombreOriginal'];
}

$anexosTexto = $listaAnexos ? $esc(implode(' · ', $listaAnexos)) : 'Ninguno';

$usuarioTramite = ['nombre' => '', 'documento' => ''];

$idUsuarioTramite = $firmaRit['rif_IdUsuario'] ?? ($_SESSION['id_usuario'] ?? null);

if ($idUsuarioTramite) {
    $filaUsuario = $con->obnerFila($con->consultar(
        "SELECT usu_Nombres, usu_Apellidos, usu_NumeroDocumento
           FROM conf_usuarios WHERE usu_Id = ?",
        [(int) $idUsuarioTramite]
    ));

    if ($filaUsuario) {
        $usuarioTramite['nombre'] = trim(
            (string) ($filaUsuario['usu_Nombres'] ?? '') . ' ' .
            (string) ($filaUsuario['usu_Apellidos'] ?? '')
        );
        $usuarioTramite['documento'] = (string) ($filaUsuario['usu_NumeroDocumento'] ?? '');
    }
}

// Respaldo: si el usuario no esta en conf_usuarios -no deberia pasar-, al
// menos el nombre que quedo guardado con la firma, antes que una casilla vacia.
if ($usuarioTramite['nombre'] === '' && !empty($firmaRit['rif_NombreUsuario'])) {
    $usuarioTramite['nombre'] = (string) $firmaRit['rif_NombreUsuario'];
}

$d = [

// Estos cuatro iban fijos en Paipa. Con varios municipios sobre el mismo
// codigo, el certificado de Guateque salia diciendo "MUNICIPIO DE PAIPA".
'entidad' => $esc('MUNICIPIO DE ' . mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8')),
// Encabezado pedido por el cliente el 2026-08-19, tomado del formulario
// oficial en papel. Va por constante y no en duro para que otro municipio
// pueda poner el nombre de SU dependencia sin tocar este archivo.
'dependencia' => $esc(defined('MUNICIPIO_DEPENDENCIA_TRIBUTARIA')
        ? MUNICIPIO_DEPENDENCIA_TRIBUTARIA
        : 'DIRECCIÓN DE IMPUESTOS, RENTAS Y JURISDICCIÓN COACTIVA'),
'secretaria'  => $esc(defined('MUNICIPIO_SECRETARIA')
        ? MUNICIPIO_SECRETARIA
        : 'SECRETARIA DE HACIENDA'),
'nit_entidad' => $esc(MUNICIPIO_NIT),
'direccion_entidad' => $esc(MUNICIPIO_DIRECCION),
'ciudad_entidad' => $esc(mb_strtoupper(MUNICIPIO_CIUDAD, 'UTF-8') . ' - ' . mb_strtoupper(MUNICIPIO_DEPARTAMENTO, 'UTF-8')),

// OPCIÓN USO
/*
 * Opcion de uso. Pedido el 2026-08-25: "si estan en la base de datos, solo
 * salga ACTUALIZACION; si no estan, salga INSCRIPCION".
 *
 * Antes salia de est_Opcion_uso, una casilla del ESTABLECIMIENTO que el
 * usuario elegia a mano. Tenia dos problemas: nada impedia marcar
 * "Inscripcion" en un RIT que llevaba años registrado, y desde que el
 * establecimiento es opcional (arreglo del 2026-08-25) podia no haber ninguno
 * de donde leerla.
 *
 * La regla se resuelve ahora sobre el CONTRIBUYENTE, y "estar en la base" se
 * interpreta como "este RIT ya se formalizo alguna vez", que es lo que
 * distingue una inscripcion de una novedad: la primera vez es inscripcion, de
 * ahi en adelante es actualizacion. Un registro creado pero nunca firmado
 * sigue siendo una inscripcion en tramite.
 *
 * El cese manda sobre las dos: si hay fecha de cese, el formulario es de cese
 * de actividades aunque el RIT ya estuviera formalizado.
 */
'opcion_inscripcion'   => !$ritYaFormalizado && !$hayCese,
'opcion_actualizacion' =>  $ritYaFormalizado && !$hayCese,
'opcion_cese'          =>  $hayCese,

// IDENTIFICACIÓN
'nit' => $esc($row['ind_NumeroIdentificacion']),
'dv' => $esc($row['ind_DV']),

// PERSONA
/*
 * CASILLA 6 — EL FORMULARIO DE UNA EMPRESA SALIA SIN EL NOMBRE DE LA EMPRESA.
 *
 * Decia: si es persona natural, el nombre completo; si no, est_Nombre. Pero
 * est_Nombre es el nombre del ESTABLECIMIENTO, no la razon social, y desde que
 * la declaracion es del contribuyente hay contribuyentes SIN establecimiento.
 * Medido en el contribuyente 36 (persona juridica, NIT 902016224): la casilla 6
 * salia EN BLANCO teniendo ind_PrimerNombre = 'inversiones panama'.
 *
 * La razon social de una juridica se guarda en ind_PrimerNombre -es donde la
 * escribe la pantalla del RIT-, asi que es de ahi de donde tiene que salir. El
 * nombre del establecimiento queda como ultimo respaldo, no como fuente.
 */
'razon' => $esc(
    $row['ind_Persona'] == 1
        ? $nombreCompleto
        : (trim((string) $row['ind_PrimerNombre']) !== ''
            ? $row['ind_PrimerNombre']
            : ($row['est_Nombre'] ?? ''))
),

'direccion' => $esc($row['ind_Direccion']),
'municipio' => $esc($row['ciu_Nombre']),
'departamento' => $esc($row['ciu_Departamento']),

'telefono' => $esc($row['ind_Telefono']),

/*
 * El telefono del REPRESENTANTE es otro dato.
 *
 * La casilla 26, dentro del bloque de Representacion Legal, venia imprimiendo
 * ind_Telefono -el del contribuyente-, asi que el papel presentaba el telefono
 * de la empresa como si fuera el del representante. En una persona juridica son
 * dos personas distintas. Lo reporto el cliente el 2026-08-31.
 *
 * Se cae al del contribuyente cuando el del representante esta vacio: es lo que
 * el formulario hacia hasta ahora, asi que las declaraciones ya impresas no
 * cambian de aspecto, y en cuanto alguien escriba el suyo empieza a salir el
 * correcto.
 */
'telefono_representante' => $esc(
    trim((string) ($row['ind_Telefono_representante'] ?? '')) !== ''
        ? $row['ind_Telefono_representante']
        : $row['ind_Telefono']
),
'correo' => $esc($row['ind_Email']),

// MATRÍCULA
// Punto 8: la matricula de la PERSONA vive ahora en el
// contribuyente. Se cae a la del establecimiento solo para las
// bases donde la migracion 003 todavia no dejo el dato.
'matricula' => $esc($row['ind_Matricula'] ?: $row['est_Matricula']),
/*
 * Estas dos leian SOLO del establecimiento, y la pantalla del RIT las
 * guarda en el contribuyente desde la migracion 003: el contribuyente
 * escribia una fecha y el formulario imprimia otra, o ninguna.
 *
 * Se quedo asi por herencia -antes el RIT colgaba del local-, y desde
 * que el establecimiento es opcional (2026-08-25) el sintoma empeoro:
 * quien no tiene local no tiene de donde leerlas y salian siempre en
 * blanco.
 *
 * Mismo patron que 'matricula' aqui arriba: manda el contribuyente y se
 * cae al establecimiento solo para las bases donde la migracion 003
 * todavia no dejo el dato.
 */
'fecha_matricula' => _fecha($row['ind_Fecha_matricula'] ?? null, $row['est_Fecha_matricula'] ?? null),

// ACTIVIDAD
'fecha_inicio'    => _fecha($row['ind_Fecha_inicio'] ?? null, $row['est_Fecha_inicio'] ?? null),

'actividades' => $actividades,

'nombre_comercial' => $esc($row['est_Nombre']),
/*
 * CASILLA 21 — sale del establecimiento, que es lo correcto: el rotulo dice
 * «lugar en donde se ejerce la actividad». El cliente lo noto el 2026-09-01
 * («parece que saca la direccion del establecimiento») y asi debe ser.
 *
 * Lo que faltaba era el respaldo: un contribuyente sin establecimiento dejaba
 * la casilla EN BLANCO -medido en el contribuyente 36-. Cuando no hay
 * establecimiento se cae a la direccion de notificacion del contribuyente, que
 * es el unico domicilio que el sistema conoce de el, antes que dejar vacia una
 * casilla del formulario oficial.
 */
'telefono_actividad' => $esc(
    trim((string) ($row['est_Telefono'] ?? '')) !== ''
        ? $row['est_Telefono']
        : ($row['ind_Telefono'] ?? '')
),

'direccion_actividad' => $esc(
    trim((string) ($row['est_Direccion'] ?? '')) !== ''
        ? $row['est_Direccion']
        : ($row['ind_Direccion'] ?? '')
),

// REPRESENTANTE
'representante' => $esc($row['ind_Nombre_representante'] ?: $row['est_Nombre_representante']),
// Los tres campos de abajo solo leian la columna legacy est_*, nunca su
// equivalente ind_* -que es el que _guardarRIT() (class.contribuyentes.php)
// SI actualiza-. Cualquier correccion de cedula/correo del representante
// hecha desde el RIT nunca se veia reflejada en el certificado. Mismo
// patron ind_X ?: est_X que ya se aplicaba en 'representante'.
'cc_representante' => $esc($row['ind_Cedula_representante'] ?: $row['est_Cedula_representante']),
'email_representante' => $esc($row['ind_Email_representante'] ?: $row['est_Email_representante']),

/*
 * QUIEN TRAMITA EL RIT (casilla 31).
 *
 * Aqui habia un nombre y una cedula ESCRITOS EN DURO:
 * 'JUAN GABRIEL SUAREZ AVENDAÑO' y '10101010'. Es decir que TODO formulario
 * que saliera del sistema -de cualquier contribuyente, y en cualquier
 * municipio que instalara esto- afirmaba haber sido tramitado por esa persona.
 *
 * El cliente pidio el 2026-09-01 que debajo de la casilla 31 salga «el usuario
 * con el que se realiza el RIT, el que esta registrado». Se resuelve en dos
 * pasos, para que el papel diga la verdad tanto si el RIT ya se firmo como si
 * todavia no:
 *
 *   1. Si hay firma del RIT, el que la hizo -viene en ind_rit_firmas-. Es el
 *      dato correcto: el formulario lo tramito quien lo firmo, no quien lo
 *      esta imprimiendo ahora.
 *   2. Si aun no se ha firmado, el usuario de la sesion que lo esta generando.
 *
 * De conf_usuarios se toman nombre COMPLETO y numero de documento, porque
 * ind_rit_firmas solo guarda rif_NombreUsuario y ahi cabe unicamente el
 * nombre de pila -medido: la firma del contribuyente 30 dice solo «Prueba»-.
 */
'nombre_funcionario' => $esc($usuarioTramite['nombre']),
'cc_funcionario' => $esc($usuarioTramite['documento']),


// CONTADOR
// Los nombres de columna son ind_NombreContador (sin guion bajo entre
// palabras), no ind_Nombre_contador: son los que ya trae produccion desde
// migracion_2026-08_contribuyente.sql BLOQUE 4 (2026-08-04). La migracion
// 003 habia creado un juego paralelo con otro nombre; se corrigio para usar
// estos (ver el propio archivo de esa migracion).
'contador_nombre' => $esc($row['ind_NombreContador'] ?: $row['est_Nombre_contador']),
'contador_cc' => $esc($row['ind_CedulaContador'] ?: $row['est_Cedula_contador']),
'contador_tp' => $esc($row['ind_TarjetaProfContador'] ?: $row['est_Tarjeta_profesional']),

// REVISOR
'revisor_nombre' => $esc($row['ind_NombreRevisor'] ?: $row['est_Nombre_revisor']),
'revisor_cc' => $esc($row['ind_CedulaRevisor'] ?: $row['est_Cedula_revisor']),
'revisor_tp' => $esc($row['ind_TarjetaProfRevisor'] ?: $row['est_Tarjeta_profesional_revisor']),

// CATASTRAL
'codigo_catastral' => $esc($row['est_Codigo_catastral']),

// CESE
// 1900-01-01 es el centinela de "nunca se lleno" de esta base: se imprime
// vacio, igual que ya se hace con las fechas de los establecimientos. Sin
// esto el certificado afirmaba que el negocio ceso actividades en 1900.
/*
 * El cese que imprime el formulario es el del CONTRIBUYENTE (migracion 019):
 * lo que se declara aqui es que la persona dejo de ejercer actividades en el
 * municipio. Cerrar un local suelto y seguir con los otros es otro hecho, y
 * vive en el estado del registro del establecimiento.
 *
 * Se cae al del establecimiento para las bases que todavia no tengan la 019,
 * igual que hacen 'matricula' y las dos fechas de arriba.
 */
'fecha_cese' => _fecha($row['ind_FechaCese'] ?? null, $row['est_Fecha_cierre'] ?? null),

// El codigo de la causal (1 Fusion, 2 Escision, 3 Liquidacion, 4 Otro). Las
// cuatro casillas del formulario se imprimian SIEMPRE vacias: el valor se leia
// pero no se usaba para marcar ninguna.
'causal' => trim((string) ($row['ind_CausalCese'] ?: ($row['est_Causal'] ?? ''))),

'resolucion_cese' => $esc($row['est_Resolucion_cierre']),

// Punto 12: la observacion del cese se captura desde hace dias pero no se
// imprimia en ninguna parte.
'observacion_cese' => $esc((string) ($row['ind_ObservacionCese'] ?: ($row['est_Observacion_cierre'] ?? ''))),

];

$tipoDoc = $row['ind_IdTipoDocumento'];
/*
 * TIPO DE DOCUMENTO — EL PDF USABA UN CATALOGO QUE NO ES EL DEL SISTEMA.
 *
 * Aqui se daba por hecho 1=C.C., 2=NIT, 3=T.I. El catalogo real, el que pinta
 * la pantalla (core/icaWebRit.js), es:
 *
 *     1 Cedula de Ciudadania · 3 Cedula de Extranjeria · 4 Pasaporte · 5 NIT
 *
 * O sea que el 2 no existe y el NIT es el 5. Medido: los tres contribuyentes
 * con NIT salian con las TRES casillas vacias -ninguna X-, y una cedula de
 * extranjeria se habria marcado como «T.I».
 *
 * El formulario impreso solo tiene tres casillas y una de ellas es T.I, que el
 * sistema ya no ofrece. Se aprovecha esa tercera para lo que si existe:
 * la cedula de extranjeria y el pasaporte, que de otro modo no tendrian donde
 * marcarse. El rotulo de esa casilla se cambia mas abajo en consecuencia.
 */
$d['doc_cc']   = ($tipoDoc == 1);
$d['doc_nit']  = ($tipoDoc == 5);
$d['doc_otro'] = ($tipoDoc == 3 || $tipoDoc == 4);
$d['doc_ti']   = $d['doc_otro'];   // compatibilidad con el nombre viejo

$tipoPersona = $row['ind_Persona'];
$d['persona_natural']   = ($tipoPersona == 1);
$d['persona_juridica']  = ($tipoPersona == 2);
$d['persona_hecho']     = ($tipoPersona == 3);

$regimen = $row['ind_IdRegimen'];
$d['regimen_comun'] = ($regimen == 1);
$d['regimen_simplificado']        = ($regimen == 2);
$d['regimen_simple']     = ($regimen == 3);
$d['regimen_especial']     = ($regimen == 4);
$d['regimen_otro']     = ($regimen == 5);

$regimen = $row['ind_IdRegimen'];

if($row['ind_IdRegimen']== 1) $regimenNombre = 'Responsable de IVA';
if($row['ind_IdRegimen']== 2) $regimenNombre = 'No Responsable de IVA';
if($row['ind_IdRegimen']== 3) $regimenNombre = 'Autoretenedor';
if($row['ind_IdRegimen']== 4) $regimenNombre = 'Régimen Simple de Tributación (RST)';
if($row['ind_IdRegimen']== 5) $regimenNombre = 'Régimen Tributario Especial (RTE)';
if($row['ind_IdRegimen']== 6) $regimenNombre = 'Otro';

/*
 * Desde la migracion 014 el regimen es de seleccion MULTIPLE y vive en
 * ind_RegimenTributario como codigos separados por coma. El ind_IdRegimen de
 * arriba es el de un solo valor y se conserva como respaldo para las bases que
 * todavia no tengan la 014, o para los contribuyentes que nunca abrieron el
 * RIT desde que existe la casilla nueva.
 */
$ETIQUETAS_MULTIPLE = [
    'ORDINARIO'          => 'Régimen ordinario',
    'SIMPLE'             => 'Régimen Simple de Tributación',
    'ESPECIAL'           => 'Régimen Especial',
    'RESP_IVA'           => 'Responsable de IVA',
    'NO_RESP_IVA'        => 'No responsable de IVA',
    'AGENTE_RETENCION'   => 'Agente de retención',
    'AUTORRETENEDOR'     => 'Autorretenedor',
    'INFORMANTE_EXOGENA' => 'Informante de exógena',
];

/** Convierte 'ORDINARIO,RESP_IVA' en 'Régimen ordinario, Responsable de IVA'. */
$enPalabras = function ($codigos) use ($ETIQUETAS_MULTIPLE) {
    $partes = array_filter(array_map('trim', explode(',', (string) $codigos)));
    $texto  = [];
    foreach ($partes as $c) { $texto[] = $ETIQUETAS_MULTIPLE[$c] ?? $c; }
    return htmlspecialchars(implode(', ', $texto), ENT_QUOTES, 'UTF-8');
};

$regimenTexto = $enPalabras($row['ind_RegimenTributario'] ?? '');
if ($regimenTexto === '') { $regimenTexto = htmlspecialchars((string) $regimenNombre, ENT_QUOTES, 'UTF-8'); }

/*
 * Responsabilidades y condiciones frente al impuesto van en la MISMA casilla.
 *
 * Estaban en dos filas separadas; el cliente las junto el 2026-08-26. Se
 * guardan aparte -las condiciones son banderas de si/no de la migracion 016 y
 * las responsabilidades una lista de codigos-, pero se imprimen juntas porque
 * para quien lee el formulario son lo mismo: lo que el contribuyente declara
 * sobre su situacion frente al impuesto.
 */
$partes = [];
$resp = $enPalabras($row['ind_Responsabilidades'] ?? '');
if ($resp !== '')                          { $partes[] = $resp; }
if (!empty($row['ind_NoSujetas']))         { $partes[] = 'Realiza actividades no sujetas o no gravadas'; }
if (!empty($row['ind_SinAvisosTableros'])) { $partes[] = 'Sin Avisos y Tableros'; }

$responsabilidadesTexto = $partes ? implode(' · ', $partes) : 'Ninguna';

// Codigos CIIU del RUT (migracion 005): son los de la DIAN, de cuatro
// digitos, distintos de los del acuerdo municipal que salen en la tabla de
// actividades economicas.
$ciiu = array_filter([
    trim((string) ($row['ind_Rut'] ?? '')),
    trim((string) ($row['ind_Rut_segundo'] ?? '')),
    trim((string) ($row['ind_Rut_tercero'] ?? '')),
], function ($v) { return $v !== '' && $v !== '0000'; });
$ciiuTexto = $ciiu ? htmlspecialchars(implode(' · ', $ciiu), ENT_QUOTES, 'UTF-8') : '';

$autorizaTexto = !empty($row['ind_Autorizacion']) ? 'SÍ' : 'NO';

$actividadesHtml = '';

// Dos columnas y un encabezado, no cuatro celdas por fila.
//
// Antes cada actividad ocupaba cuatro celdas -"Código Actividad
// Principal/Secundaria" | codigo | la palabra "Descripción" | nombre-, de modo
// que los rotulos se repetian en TODAS las filas y la descripcion quedaba
// aplastada en la mitad del ancho. El cliente pidio lo evidente: una cabecera
// con "Código de Actividad" y "Descripción", y debajo las actividades.
if (!empty($d['actividades'])) {

    $actividadesHtml = '
    <tr>
        <td width="22%" align="center" bgcolor="#cae6e7"><b>Código de Actividad</b></td>
        <td width="78%" bgcolor="#cae6e7"><b>Descripción</b></td>
    </tr>';

    foreach ($d['actividades'] as $act) {
        $actividadesHtml .= '
        <tr>
            <td width="22%" align="center">'.$act['codigo'].'</td>
            <td width="78%">'.$act['nombre'].'</td>
        </tr>';
    }

} else {

    $actividadesHtml = '
    <tr>
        <td width="100%">No registra actividades</td>
    </tr>';
}

/* ===========================
   Punto 19: el certificado debe incluir nombre, matricula, direccion y fecha
   de inicio de LOS ESTABLECIMIENTOS -en plural-. Antes solo salia el
   establecimiento por el que se habia entrado, asi que un contribuyente con
   varios locales no tenia forma de verlos todos en un mismo documento.
   =========================== */

$establecimientosHtml = '';
$stmtEst = $con->consultar(
    "SELECT est_Nombre, est_Matricula, est_Direccion, est_Fecha_inicio, est_Activo
       FROM ind_establecimientos
      WHERE est_IdContribuyente = ?
      ORDER BY est_Id",
    [$row['est_IdContribuyente']]
);

while ($e = $con->obnerFila($stmtEst)) {

    // 1900-01-01 es el centinela de "nunca se lleno", no una fecha real.
    $fechaIni = '';
    if (!empty($e['est_Fecha_inicio']) && $e['est_Fecha_inicio'] instanceof \DateTime
        && $e['est_Fecha_inicio']->format('Y') > 1900) {
        $fechaIni = $e['est_Fecha_inicio']->format('d-m-Y');
    }

    $establecimientosHtml .= '
    <tr>
        <td width="34%">'.htmlspecialchars((string) $e['est_Nombre'], ENT_QUOTES, 'UTF-8').'</td>
        <td width="16%">'.htmlspecialchars((string) $e['est_Matricula'], ENT_QUOTES, 'UTF-8').'</td>
        <td width="34%">'.htmlspecialchars((string) $e['est_Direccion'], ENT_QUOTES, 'UTF-8').'</td>
        <td width="16%">'.$fechaIni.'</td>
    </tr>';
}

if ($establecimientosHtml === '') {
    $establecimientosHtml = '<tr><td width="100%">No registra establecimientos</td></tr>';
}

/* ===========================
HTML
=========================== */

/*
 * LOS COLORES SON LOS MISMOS DE LA DECLARACION
 *
 * Pedido del cliente el 2026-08-26: los dos formularios se entregan juntos y
 * en blanco y negro el RIT parecia de otro sistema. La paleta sale de
 * extensiones/declaracion.php:
 *
 *   #cae6e7  celeste de los rotulos
 *   #e1dada  gris de los encabezados de bloque
 *   negro    barras de seccion (esas ya coincidian)
 *
 * Los rotulos llevan el color como atributo bgcolor celda a celda, que es como
 * lo hace la declaracion, y no como clase.
 *
 * OJO AL EDITAR EL <style> DE ABAJO: el parser de TCPDF no entiende comentarios
 * CSS. Un bloque de comentario ahi dentro se imprime como texto plano y, si
 * menciona nombres de etiqueta, se los come como si fueran etiquetas de verdad
 * -asi se perdio el formulario entero en una prueba de este mismo cambio-. Por
 * eso esta explicacion vive aqui, en PHP, y no dentro de la hoja de estilo.
 */
$html = '

<style>

table{
border-collapse: collapse;
}

td{
border:0.1px solid #000;
font-size:10px;
padding:3px;
}

.header{
border:0.1px solid #000;
font-size:10px;
font-weight:bold;
text-align:center;
background-color:#e1dada;
}

.section{
background-color:#000;
color:#fff;
font-weight:bold;
font-size:8px;
}

.celda{
border:1px solid #000;
}

.center{
text-align:center;
}

.right{
text-align:right;
}

.titulo{
font-weight:bold;
}

</style>


<table cellpadding="4">

<tr>

<td width="20%" rowspan="4" align="center">
    <!-- El escudo es 200x285: MUY alto en proporcion (1.43 de alto por cada
         ancho). A width=80 mide unos 40mm y se derramaba sobre las barras
         negras de "A. OPCION DE USO" y "B. DATOS DEL CONTRIBUYENTE" -TCPDF no
         reajusta la fila, la imagen simplemente se pinta encima-.

         Se salio de sitio al acortar el encabezado: antes eran 9 lineas de
         texto y ahora son 6, asi que la fila bajo ~9mm y el logo, que es de
         alto fijo, dejo de caber. Se le da ALTO explicito en vez de solo
         ancho, que es lo que de verdad hay que controlar aqui. -->
    <img src="tcpdf/pdf/img/logopazysalvo.png" width="49" height="70">
</td>

<td width="80%" class="header">

'.$d['entidad'].'<br>
'.$d['dependencia'].'<br>
'.$d['secretaria'].'<br><br>

<b>REGISTRO DE INFORMACIÓN TRIBUTARIA R.I.T</b><br>
FORMATO DE INSCRIPCION Y/O NOVEDADES DE CONTRIBUYENTES

</td>

</tr>

</table>

<br>


<table>

<tr class="section">
<td colspan="6">A. OPCION DE USO (Sólo puede marcar una casilla por formulario)</td>
</tr>

<tr>
<td width="30%">1. Inscripción</td>
<td width="5%">'.($d['opcion_inscripcion']?'X':'').'</td>
<td width="25%">2. Actualización</td>
<td width="5%">'.($d['opcion_actualizacion']?'X':'').'</td>
<td width="30%">3. Cese de Actividades</td>
<td width="5%">'.($d['opcion_cese']?'X':'').'</td>
</tr>

</table>

<br>

<table>

<tr class="section">
<td colspan="6">B. DATOS DEL CONTRIBUYENTE</td>
</tr>

<tr>
<td  class="center" width="50%" bgcolor="#cae6e7"><b>4. Tipo de Documento</b></td>
<td  class="center" width="50%" bgcolor="#cae6e7"><b>5. Naturaleza Jurídica</b></td>
</tr>

<tr>
<td width="5%">C.C.</td>
<td width="3%">'.($d['doc_cc'] ? 'X' : '').'</td>
<td width="5%">NIT</td>
<td width="3%">'.($d['doc_nit'] ? 'X' : '').'</td>
<td width="7%">OTRO</td>
<td width="3%">'.($d['doc_otro'] ? 'X' : '').'</td>
<td width="4%">No.</td>
<td width="11%">'.$d['nit'].'</td>
<td width="4%">DV</td>
<td width="5%">'.$d['dv'].'</td>

<td width="13%">Persona Natural</td>
<td width="3%">'.($d['persona_natural'] ? 'X' : '').'</td>
<td width="14%">Persona Juridica</td>
<td width="3%">'.($d['persona_juridica'] ? 'X' : '').'</td>
<td width="14%">Sociedad de Hecho</td>
<td width="3%">'.($d['persona_hecho'] ? 'X' : '').'</td>
</tr>

<!-- Punto 6 de la revision del 2026-08-21: se quita "Nombre Comercial" porque
     no existe en el formulario oficial del RIT. La razon social pasa a ocupar el
     ancho que quedaba. El dato en la base NO se toca; solo deja de imprimirse. -->
<tr>
<td width="25%" bgcolor="#cae6e7"><b>6. Apellidos y Nombres ó Razón Social</b></td>
<td width="75%">'.$d['razon'].'</td>
</tr>

<tr>
<td width="25%" bgcolor="#cae6e7"><b>8. Dirección de Notificación</b></td>
<td width="25%">'.$d['direccion'].'</td>
<td width="13%" bgcolor="#cae6e7"><b>9. Municipio</b></td>
<td width="11%">'.$d['municipio'].'</td>
<td width="16%" bgcolor="#cae6e7"><b>10. Departamento</b></td>
<td width="10%">'.$d['departamento'].'</td>
</tr>


<tr>
<td width="25%" bgcolor="#cae6e7"><b>11. Teléfono</b></td>
<td width="25%">'.$d['telefono'].'</td>

<td width="20%" bgcolor="#cae6e7"><b>12. Régimen Tributario:</b></td>
<td width="30%">'.$regimenTexto.'</td>

</tr>

<!-- Casillas anadidas el 2026-08-25. El cliente pidio las dos primeras
     ("para seleccionar 1 o varias") y las dos exenciones con estos nombres.
     Los codigos CIIU del RUT se capturaban desde la migracion 005 pero no se
     imprimian en ninguna parte. Cada fila lleva dos celdas al 50% para no
     mezclar numeros de columna dentro de la misma tabla: TCPDF pinta trozos
     de borde sueltos cuando las filas no cuadran entre si. -->
<!-- Las dos condiciones frente al impuesto se imprimen dentro de
     Responsabilidades, no en una fila aparte: el cliente las junto en la
     pantalla el 2026-08-26 y el papel tiene que decir lo mismo que se ve. -->
<tr>
<td width="30%" bgcolor="#cae6e7"><b>Responsabilidades:</b></td>
<td width="70%">'.$responsabilidadesTexto.'</td>
</tr>

<tr>
<td width="30%" bgcolor="#cae6e7"><b>Códigos CIIU del RUT:</b></td>
<td width="70%">'.$ciiuTexto.'</td>
</tr>

<tr>
<td width="30%" bgcolor="#cae6e7"><b>Autoriza notificación electrónica:</b></td>
<td width="70%">'.$autorizaTexto.'</td>
</tr>

<tr>
<td width="15%" bgcolor="#cae6e7"><b>13. Contador</b></td>
<td width="10%" bgcolor="#cae6e7"><b>Nombre:</b></td>
<td width="15%">'.$d['contador_nombre'].'</td>
<td width="10%" bgcolor="#cae6e7"><b>Cedula:</b></td>
<td width="15%">'.$d['contador_cc'].'</td>
<td width="22%" bgcolor="#cae6e7"><b>Tarjeta Profesional No:</b></td>
<td width="13%">'.$d['contador_tp'].'</td>
</tr>

<!-- Los anchos se igualan con los de la casilla 13: antes la 14 usaba
     20/15 en las dos ultimas celdas y "Tarjeta Profesional No:" se partia en
     dos lineas, dejando la fila mas alta que la de arriba. -->
<tr>
<td width="15%" bgcolor="#cae6e7"><b>14. Revisor Fiscal</b></td>
<td width="10%" bgcolor="#cae6e7"><b>Nombre:</b></td>
<td width="15%">'.$d['revisor_nombre'].'</td>
<td width="10%" bgcolor="#cae6e7"><b>Cedula:</b></td>
<td width="15%">'.$d['revisor_cc'].'</td>
<td width="22%" bgcolor="#cae6e7"><b>Tarjeta Profesional No:</b></td>
<td width="13%">'.$d['revisor_tp'].'</td>
</tr>

<!-- Los correos del contador y del revisor no salian en ninguna parte, y son
     el dato por el que les llega el codigo para firmar: si estan mal escritos
     la declaracion no se puede presentar y no habia donde comprobarlo. Van en
     su propia fila -y no dentro de las casillas 13 y 14- porque esas ya tienen
     siete celdas y meter dos mas desalinearia la tabla entera. -->
<tr>
<td width="30%" bgcolor="#cae6e7"><b>Correo del contador:</b></td>
<td width="70%">'.$esc($row['ind_EmailContador']).'</td>
</tr>

<tr>
<td width="30%" bgcolor="#cae6e7"><b>Correo del revisor fiscal:</b></td>
<td width="70%">'.$esc($row['ind_EmailRevisor']).'</td>
</tr>

<tr>
<!-- Punto 7: el cliente pidio dejarlo en "Numero de matricula mercantil". -->
<td width="40%" bgcolor="#cae6e7"><b>15. Número de matrícula mercantil:</b></td>
<td width="10%">'.$d['matricula'].'</td>
<td width="39%" bgcolor="#cae6e7"><b>16. Fecha de la Matricula mercantil:</b></td>
<td width="11%">'.$d['fecha_matricula'].'</td>

</tr>

<tr>
<td class="titulo" width="100%">18. Actividades Economicas del Contribuyente</td>
</tr>


'.$actividadesHtml.'

<!-- Los documentos aportados. Va en una sola fila y en una sola linea a
     proposito: el formulario es de UNA hoja y no tiene
     SetAutoPageBreak, asi que cualquier bloque que crezca sin control lo parte
     en dos sin avisar. Comprobado tras el cambio: sigue en una pagina. -->
<tr>
<td width="30%" bgcolor="#cae6e7"><b>Documentos adjuntos:</b></td>
<td width="70%">'.$anexosTexto.'</td>
</tr>

<!-- Punto 13 de la revision del 2026-08-21: "Quitar establecimientos del
     contribuyente".

     OJO, esto REVIERTE el punto 19 de la lista escrita anterior, que pedia
     justamente incluir nombre, matricula, direccion y fecha de inicio de cada
     establecimiento en el certificado. Por eso el 2026-08-20 se devolvieron al
     formulario de establecimientos las casillas de matricula y fecha de inicio.

     Esas dos casillas se DEJAN donde estan: siguen siendo datos utiles del
     local aunque ya no se impriman aqui. Si el cliente confirma que tampoco las
     quiere capturar, se retiran en otro paso.

     La consulta que arma $establecimientosHtml se conserva mas arriba; solo
     deja de pintarse. -->

<tr>
<td width="15%" bgcolor="#cae6e7"><b>19. Fecha inicio actividades</b></td>
<td width="35%">'.$d['fecha_inicio'].'</td>
<!-- Casilla 20: el telefono del LUGAR, no el de la persona. Ese es el de la
     casilla 11, y hasta el 2026-09-01 las dos imprimian lo mismo porque no
     existia donde guardar el del local (migracion 028). Mientras el
     establecimiento no lo tenga, cae al del contribuyente: es lo que hacia
     antes, asi que nada empeora, pero deja de ser una copia por diseno. -->
<td width="15%" bgcolor="#cae6e7"><b>20. Teléfono</b></td>
<td width="35%">'.$d['telefono_actividad'].'</td>
</tr>

<!-- Pedido del cliente, 2026-09-01: la casilla 22 decia solo "Correo
     Electronico", que no dice para que sirve. Es la direccion a la que la
     Alcaldia notifica.

     Se cambio SOLO EL ROTULO. En una primera version se movio ademas la
     casilla para que el correo fuera antes que la direccion; el cliente
     aclaro que no queria eso ("era solo el nombre, no cambiarla de
     ubicacion"), asi que el orden es el original: 21 y luego 22. -->
<tr>
<td width="25%" bgcolor="#cae6e7"><b>21. Dirección del lugar en donde se ejerce la actividad</b></td>
<td width="25%">'.$d['direccion_actividad'].'</td>
<td width="25%" bgcolor="#cae6e7"><b>22. Correo electrónico de notificación</b></td>
<td width="25%">'.$d['correo'].'</td>
</tr>

</table>

<br>

<table>

<tr class="section">
<!-- Pedido del cliente, 2026-09-01: antes decia solo "REPRESENTACION
     LEGAL". Una persona natural no tiene representante, es propietaria de
     su negocio, asi que el rotulo dejaba fuera a media base. -->
<td width="100%">C. REPRESENTANTE LEGAL O PROPIETARIO</td>
</tr>

<tr>
<td width="25%" bgcolor="#cae6e7"><b>23. Apellidos y Nombres</b></td>
<td width="25%">'.$d['representante'].'</td>
<td width="25%" bgcolor="#cae6e7"><b>24. Identificación</b></td>
<td width="25%">'.$d['cc_representante'].'</td>
</tr>

<tr>
<td width="25%" bgcolor="#cae6e7"><b>25. Correo Electronico</b></td>
<td width="25%">'.$d['email_representante'].'</td>
<td width="25%" bgcolor="#cae6e7"><b>26. Telefono</b></td>
<td width="25%">'.$d['telefono_representante'].'</td>
</tr>


</table>

<br>

<table>

<tr class="section">
<td width="100%">D. CESE DE ACTIVIDADES</td>
</tr>

<!-- Punto 12 de la revision del 2026-08-21: "Solamente las casillas Fecha cese
     de actividades, causal y observacion".

     Se retira la casilla 29 ("Numero de Establecimiento que clausura"): el cese
     que se declara aqui es el del CONTRIBUYENTE, y cerrar un local concreto pasa
     a manejarse desde el estado del registro del establecimiento.

     Se agrega Observacion, que estaba en la pantalla pero no se imprimia -o sea
     que el funcionario escribia algo que nadie volvia a ver-. -->
<!-- "Ajustar los espacios para que guarde simetria con el resto del
     documento" (2026-08-25).

     Lo que rompia la simetria era una celda vacia del 17% al final de la fila
     de la causal: sobraba desde que se retiro la casilla 29, y dejaba un hueco
     a la derecha que ninguna otra fila del formulario tiene. Los anchos se
     reparten ahora entre las celdas que si dicen algo, y suman 100.

     La observacion lleva el numero 29, por indicacion del cliente el
     2026-08-26. Ese numero era antes "Numero de Establecimiento que clausura",
     casilla que ellos mismos retiraron; al quedar libre, lo reutilizan para la
     observacion. Su rotulo se alinea con el de la fila de arriba (20%) para
     que las dos columnas empiecen en la misma vertical. -->
<tr>
<td width="20%" bgcolor="#cae6e7"><b>27. Fecha de cese actividades:</b></td>
<td width="15%">'.$d['fecha_cese'].'</td>
<td width="11%" bgcolor="#cae6e7"><b>28. Causal:</b></td>
<td width="8%">Fusión</td>
<td width="5%">'.($d['causal'] === '1' ? 'X' : '').'</td>
<td width="8%">Escisión</td>
<td width="5%">'.($d['causal'] === '2' ? 'X' : '').'</td>
<td width="11%">Liquidación</td>
<td width="5%">'.($d['causal'] === '3' ? 'X' : '').'</td>
<td width="7%">Otro</td>
<td width="5%">'.($d['causal'] === '4' ? 'X' : '').'</td>
</tr>

<tr>
<td width="20%" bgcolor="#cae6e7"><b>29. Observación:</b></td>
<td width="80%">'.$d['observacion_cese'].'</td>
</tr>

</table>

<br>

<table>

<tr class="section">
<td width="100%">E. FIRMAS</td>
</tr>

<!-- Rotulo y firma van en la MISMA celda, no en dos filas.

     Con el rotulo en una fila y la firma en la siguiente, TCPDF dibujaba un
     trozo de borde suelto a la derecha de "31. Firma del Funcionario": esta
     tabla mezcla filas de 2 columnas con filas de 7, y en esos cortes el
     motor de tablas pinta segmentos de linea que no corresponden a ningun
     recuadro. Juntandolo en una celda desaparece el corte.

     El rotulo se alinea a la izquierda y la firma al centro con <div align>,
     que es lo que TCPDF entiende dentro de una celda.
-->
<!-- Fila de las dos firmas.

     Ojo con dos trampas de TCPDF que ya costaron una vuelta aqui:

     1. height="40" en un <td> lo IGNORA el parser HTML: la fila mide lo que
        mida su contenido. Para reservar alto hay que usar <br> (~3.1mm cada
        uno). Antes esta fila confiaba en ese height y la firma del funcionario
        -que a height=40 mide ~14mm- se salia por arriba, cruzando el borde de
        la fila del rotulo. Y como la imagen esta aplanada contra blanco (no
        puede llevar alfa, revienta en Plesk), ese blanco TAPABA la linea del
        recuadro en vez de dejarla ver: de ahi que se viera rota.

     2. Las dos celdas tienen que ocupar alto parecido o la fila queda
        descuadrada. Por eso el sello del contribuyente y la firma del
        funcionario van a una altura comparable (~10mm) y las dos celdas
        cierran con el mismo <br>.
-->
<tr>
<td width="50%">
<div align="left"><b>30. Contribuyente, Representante Legal o propietario</b></div>
<div align="center">'.
($firmaRit
    /* Mismo sello que usan las declaraciones. Sin canal alfa por lo dicho
       arriba. A 28 unidades son ~10mm, igual que la firma de al lado. */
    ? '<img src="'.MUNICIPIO_SELLO_FIRMA.'" width="28" height="28"><br>'.
      '<span style="font-size:6px;">'.$esc($firmaRit['rif_NombreUsuario']).' &nbsp;·&nbsp; '.$esc($fechaFirmaRit).'</span>'
    /* Sin firma digital queda el espacio para firmar a mano, como toda la vida. */
    : '<br><br><br>').
'</div>
</td>
<td width="50%">
<div align="left"><b>31. Firma del Funcionario</b></div>
<div align="center"><img src="tcpdf/pdf/img/firma_rit.png" width="84" height="28"><br>
<span style="font-size:6px;">&nbsp;</span></div>
</td>
</tr>

<!-- Punto 8: "Representante Legal o propietario". Una persona natural no es
     representante de si misma, es propietaria. -->
<tr>
<td width="50%" bgcolor="#cae6e7"><b>NOMBRE (Representante Legal o propietario) </b> '.$d['representante'].'</td>
<td width="50%" bgcolor="#cae6e7"><b>NOMBRE </b> '.$d['nombre_funcionario'].' </td>
</tr>

<tr>
<td width="7%">CC</td>
<td width="3%">X</td>
<td width="7%">CE</td>
<td width="3%"></td>
<td width="7%">OTRO</td>
<td width="3%"></td>
<td width="20%">No. '.$d['cc_representante'].'</td>

<td width="7%">CC</td>
<td width="3%">X</td>
<td width="7%">CE</td>
<td width="3%"></td>
<td width="7%">OTRO</td>
<td width="3%"></td>
<td width="20%">No. '.$d['cc_funcionario'].'</td>
</tr>

</table>

<br>

<table>

<tr>
<td class="center">
<b>PRESENTE ESTE FORMULARIO EN ORIGINAL Y COPIA CON CEDULA O NIT RESPECTIVO</b>
</td>
</tr>

<tr>
<td class="center">

La inscripción o cierre al RIT está establecida en el estatuto tributario así como las sanciones por no realizarlo oportunamente conforme las normas vigentes.

</td>
</tr>

</table>

';

$pdf->writeHTML($html,true,false,true,false,'');

/*
 * Marca de agua "SIN FIRMAR".
 *
 * El PDF se sigue generando siempre -la Alcaldia necesita poder imprimir el
 * formulario en blanco para ventanilla-, pero si no hay firma vigente el papel
 * lo dice, en vez de parecer un RIT en regla. Mismo criterio que el
 * BORRADOR/PRESENTADA/PAGADA de la declaracion.
 *
 * Va al FINAL, justo antes de Output(), y no despues de AddPage(): llamar
 * Rotate() antes de escribir el contenido cuelga el worker de PHP-FPM al 99%
 * de CPU (documentado en CLAUDE.md, costo encontrarlo).
 */
if (!$firmaRit) {
    $familia = $pdf->getFontFamily();
    $estilo  = $pdf->getFontStyle();
    $tamano  = $pdf->getFontSizePt();

    $pdf->StartTransform();
    $pdf->SetAlpha(0.10);
    $pdf->SetTextColor(200, 0, 0);
    $pdf->SetFont('helvetica', 'B', 60);

    $cx = $pdf->getPageWidth()  / 2;
    $cy = $pdf->getPageHeight() / 2;
    $pdf->Rotate(45, $cx, $cy);

    // Dentro de un Rotate(), Cell()/SetXY() interpretan las coordenadas en el
    // espacio YA rotado; Text() con un unico punto de anclaje es lo que centra
    // de verdad (ver CLAUDE.md).
    $texto = 'SIN FIRMAR';
    $pdf->Text($cx - $pdf->GetStringWidth($texto) / 2, $cy, $texto);

    $pdf->StopTransform();
    $pdf->SetAlpha(1);
    $pdf->SetTextColor(0, 0, 0);
    // StartTransform/StopTransform NO restauran la fuente: si no se repone, la
    // de 60pt se queda pegada al resto del documento.
    $pdf->SetFont($familia, $estilo, $tamano);
}

$pdf->Output('RIT_PAIPA.pdf','I');