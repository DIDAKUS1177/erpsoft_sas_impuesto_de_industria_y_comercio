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

        if (is_array($arr)) {
            foreach ($arr as $obj) {
                $datos[] = $obj->getArray();
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