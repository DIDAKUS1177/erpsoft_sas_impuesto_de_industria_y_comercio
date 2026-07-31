<?php

include_once '../DAO/DAO_MTablas.php';

//require_once '../class.sessions.php';
//Prueba de GIT

class MaestroTablas {

    private $_mensaje;
    private $_ok = 0;

    /**
     * Arrancar el programa
     */
    public static function run() {
        $funcion = NULL;
        if (isset($_POST['funcion'])) {
            $funcion = $_POST['funcion'];
        }
        $_obj = new self();
        $datos = NULL;
        switch ($funcion) {
            
            case 2: // ingresar datos al maestro de tablas
                $datos = $_obj->crearMoodificarElemento($_REQUEST['id_tabla'],$_REQUEST['valor_nombre'], $_REQUEST['valor_valor']);
                break;
            case 1: // listar tabla
            default :
                if (isset($_POST['m_id_tabla'])) {
                    $datos = $_obj->_listarElementos($_POST['m_id_tabla'], isset($_POST['m_id_elemento']) && !empty($_POST['m_id_elemento']) ? $_POST['m_id_elemento'] : NULL);
                }
        }
        header('Content-type: application/json');
        echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, 'datos' => $datos));
    }

    /**
     * 
     * @param type $idTabla
     * @param type $idElemento
     * @return array
     */
    private function _listarElementos($idTabla, $idElemento = NULL) {
        $_mtablas = new DAO_MTablas();
        $_mtablas->habilita1ResultadoEnArray();
        $_mtablas->set_id_maestro($idTabla);
        if (!empty($idElemento)) {
            $_mtablas->set_id_tablas($idElemento);
        }
        $_mtablas->set_valor_estado(1);
        $_mtablas->setOrdenar(array("valor_nombre ASC"));
        $this->_ok = 1;
        $this->_mensaje = 'ok';
        $arrDatos = array();
        $datos = array();
        if ($arrDatos = $_mtablas->consultar()) {
            //print_r($arrDatos);
            
            foreach ($arrDatos as $key => $_obj) {
                $datos[] = array('nombre' => $_obj->get_valor_nombre(), 'valor' => $_obj->get_id_tablas());
            }
        }else{
            $this->_ok = 0;
            $this->_mensaje = 'NO se pudo encontrar el dato';
        }
        return $datos;
    }

    /**
     * 
     * @param type $valor_nombre
     * @param type $valor_valor
     * @param type $id_tabla
     * @return integer nuevo id 
     */
    public function crearMoodificarElemento($id_tabla, $valor_nombre, $valor_valor = NULL) {
        $_mtabla = new DAO_MTablas();
        $_mtabla->set_id_maestro($id_tabla);
        $_mtabla->set_valor_valor($valor_valor);
        $this->_ok = 1;
        $this->_mensaje = 'Datos almacenados correctamente ';
        $_mtabla->consultar();
        $id = $_mtabla->get_valor_valor(); //id_tablas();
        if (empty($id)) { // generar autoincrementable para guardar nuevo elemento
            $_mtabla->getSiguienteValorValor();
        }
        $_mtabla->set_valor_nombre($valor_nombre);
        $_mtabla->set_valor_estado(1);
        if(!$_mtabla->guardar()){
            $this->_ok = 0;
            $this->_mensaje = 'No se pudo almacenar dato en maestro de tablas central';
            return false;
        }
        return $_mtabla->get_valor_valor();
    }

}

class MaestroTablasException extends Exception {
    
}

MaestroTablas::run();

