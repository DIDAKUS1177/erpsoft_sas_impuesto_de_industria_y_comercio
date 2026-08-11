<?php
namespace erpsoftsas;

include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Ciudades.php';

class ControladorCiudades {

    public static function run() {

        $_obj = new \erpsoftsas\DAO_Ciudades();
        $_obj->habilita1ResultadoEnArray();
        $_obj->set_ciu_Estado(1);
        $_obj->setOrdenar(['ciu_Departamento','ciu_Nombre']);

        $arr = $_obj->consultar();
        $datos = [];

        // Se devuelven SOLO los tres campos que consume el frontend
        // (ver core/geografia.js, core/contribuyentes.js, core/icaWebRit.js,
        // core/establecimientos.js y login.js). Con getArray() completo se
        // colaban ciu_CodigoDane, ciu_Estado y sobre todo las dos fechas
        // -serializadas como objetos {date, timezone_type, timezone}-, que
        // nadie usa y pesaban ~60% de la respuesta. Con el catalogo completo
        // de Colombia (1.120 municipios) eso son 333 KB contra ~120 KB, y
        // este endpoint lo llama la pantalla PUBLICA de inscripcion, donde
        // el contribuyente puede estar en una conexion lenta.
        if (is_array($arr)) {
            foreach ($arr as $obj) {
                $datos[] = [
                    'ciu_Id'           => $obj->get_ciu_Id(),
                    'ciu_Nombre'       => $obj->get_ciu_Nombre(),
                    'ciu_Departamento' => $obj->get_ciu_Departamento(),
                ];
            }
        }

        header('Content-type: application/json');
        echo json_encode([
            "ok" => 1,
            "datos" => $datos
        ]);
    }
}

ControladorCiudades::run();