<?php
namespace erpsoftsas;

/**
 * Qué año de catálogo rige una declaración de retención o autorretención.
 *
 * Los dos catálogos van por año -los renglones del formulario
 * (ind_renglones_retencion) y las actividades con su tarifa
 * (ind_actividadescomercio)- y ninguno tiene todos los años: al 2026-09-28 hay
 * renglones solo de 2026 y actividades solo de 2025. Pedir el año EXACTO de la
 * declaración dejaba en $0 toda retención de 2025 o 2024 -años que la pantalla
 * ofrece, porque en enero se declara diciembre- y, desde el 1 de enero, TODAS
 * las de 2027. Sin ningún error: el formulario salía sin liquidación y se
 * firmaba y presentaba en cero.
 *
 * La regla es la que ya usaban las actividades: rige el año más reciente que no
 * pase del declarado (el acuerdo vigente mientras no se expida el nuevo). Si no
 * hay ninguno anterior -una declaración de antes del primer año cargado-, el
 * más antiguo que haya: el formulario es el mismo, y es mejor que uno vacío. El
 * día que la Alcaldía cargue un año nuevo, se empieza a usar solo.
 *
 * Lo usan el motor (business/class.retenciones.php y sus dos módulos), los dos
 * PDF y el recibo de pago: un solo sitio para la regla.
 */
class CatalogoAnio
{
    /** Año de ind_renglones_retencion que rige ese módulo y año declarado. */
    public static function renglones($con, $modulo, $anio)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT COALESCE(
                    (SELECT MAX(ren_Anio) FROM ind_renglones_retencion
                      WHERE ren_Modulo = ? AND ren_Estado = 1 AND ren_Anio <= ?),
                    (SELECT MIN(ren_Anio) FROM ind_renglones_retencion
                      WHERE ren_Modulo = ? AND ren_Estado = 1)) AS anio",
            [(string) $modulo, (int) $anio, (string) $modulo]
        ));
        return (isset($f['anio']) && $f['anio'] !== null) ? (int) $f['anio'] : (int) $anio;
    }

    /** Año de ind_actividadescomercio (las tarifas) que rige el año declarado. */
    public static function actividades($con, $anio)
    {
        $f = $con->obnerFila($con->consultar(
            "SELECT COALESCE(
                    (SELECT MAX(acc_Anio) FROM ind_actividadescomercio WHERE acc_Anio <= ?),
                    (SELECT MIN(acc_Anio) FROM ind_actividadescomercio)) AS anio",
            [(int) $anio]
        ));
        return (isset($f['anio']) && $f['anio'] !== null) ? (int) $f['anio'] : (int) $anio;
    }
}
