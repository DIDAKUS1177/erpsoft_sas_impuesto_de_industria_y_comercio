<?php
namespace predial;

use Exception;

include_once $_SERVER['DOCUMENT_ROOT'] . '/predial/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Exogenas.php';
include_once SERVER . '/business/class.sessions.php';

require '../../extensiones/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

class ControladorExogenas extends \predial\Cabecera {

    private $_funcion;
    private $_ok;
    private $_id;
    private $_mensaje;   
        
    public static function run() {
        \predial\SesionUsuario::verificarSesion();
        
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'];
        
        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 3:
                    $respuesta = $_obj->_consultarExogenas();
                    break; 
                case 4:
                    $respuesta = $_obj->_eliminarExogenas();
                    break;             
                case 9:
                    $respuesta = $_obj->_agregarNotaEntrada();
                    break;
            }
            $con->commit();
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "id" => $_obj->_id, "mensaje" => $_obj->_mensaje, "datos" => $respuesta));
            
        } catch (\predial\ExogenasException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "id" => $e->getCode(), "mensaje" => "oing! " . $e->getMessage(), "datos" => "");
            //$_obj->cabeceras();
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    
    /**
    *** Realiza el proceso de Consultar Nota.
    **/ 
    private function _consultarExogenas() {
       
        $_objRol = new \predial\DAO_Exogenas();
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_objRol->set_exo_Id($_POST['id']);
            }    
        }
        
        if(isset($_POST['estado'])){
            if (!empty($_POST['estado']) || $_POST['estado'] != NULL ) {
                $_objRol->set_exo_Estado($_POST['estado']);
            }    
        }

        if(isset($_POST['anio'])){
            if (!empty($_POST['anio']) || $_POST['anio'] != NULL ) {
                $_objRol->set_exo_Anio($_POST['anio']);
            }    
        }

        if(isset($_POST['idUsuario'])){
            if (!empty($_POST['idUsuario']) || $_POST['idUsuario'] != NULL ) {
                $_objRol->set_exo_IdUsuario($_POST['idUsuario']);
            }    
        }
        
        $_objRol->habilita1ResultadoEnArray();
        $arrRol = $_objRol->consultar();
       
        if(is_array($arrRol) && count($arrRol)){
            $R = [];
            foreach($arrRol as $obj){
                $R[] = $obj->getArray();
            }
            $this->_ok = 1;
            $this->_id = 0;
            $this->_mensaje = "Exogenas listadas con exito";
        }else{
            $R=$_objRol;
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = "No existen Exogenas";            
        }
        
        return $R;
    } 

    /**
    *** Realiza el proceso de Crear Notas.
    **/ 
    protected function _agregarNotaEntrada() {

        $this->_ok = 1;
        $this->_mensaje = "Archivo procesado correctamente.";
        $msj = '';
        $control = 0;
    
        // Validar que el archivo fue enviado
        if (isset($_FILES['archivo_excel']['tmp_name'])) {

                    // Si no hubo errores en la validacion de los datos, se procede a guardar la nota
                    if($control == 0){
                        
                        $_objNota = new \predial\DAO_Exogenas();
                        $_objNota->set_exo_IdUsuario($_POST['kar_IdUsuario']);
                        $_objNota->set_exo_Anio($_POST['kar_AnioXml']);  
                        
                        if(isset($_POST['Kar_Observaciones'])){
                            if (!empty($_POST['Kar_Observaciones']) || $_POST['Kar_Observaciones'] != NULL ) {
                                $_objNota->set_exo_Observaciones($_POST['Kar_Observaciones']);  
                            }    
                        }

                        $_objNota->set_exo_IdTipoDocumento($_POST['detkar_TipoDocumento']);  
                        $_objNota->set_exo_estado(1);                

                        if(!$_objNota->guardar()){
                            $this->_ok = 0;
                            $this->_id = 0;
                            $this->_mensaje = $_objNota->getMysqlError();
                        }else{
                            //$idExogena = $_objNota->get_exo_Id();  
                            //$this->_insertarDetallesExogena();
                        }
            
                        // Definir ruta para guardar archivos
                        // $directory = '../../exogenas/' . $_POST['kar_IdUsuario'];
                        $anio = $_POST['kar_AnioXml'];
                        $directory = '../../exogenas/' . $_POST['kar_IdUsuario'] . '/' . $anio;

                        // Verificar si la carpeta no existe y crearla
                        if (!is_dir($directory)) {
                            if (!mkdir($directory, 0777, true)) {
                                $this->_ok = 0;
                                $this->_mensaje = "Error al crear directorio para soportes.";
                                return false;
                            }
                        }


                            if (isset($_FILES['archivo_excel']) && $_FILES['archivo_excel']['error'] === UPLOAD_ERR_OK) {
                                // Ruta temporal y nombre original
                                $tmpName      = $_FILES['archivo_excel']['tmp_name'];
                                $originalName = $_FILES['archivo_excel']['name'];

                                // Sanitizar nombre de archivo
                                $fileName = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($originalName));
                                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                                $sufijo = ($ext === 'xml') ? '_xml' : '';

                                if($_POST['detkar_TipoDocumento'] == 1){
                                    $fileName = 'PFE1' . $sufijo . '.' . $ext;
                                }else if($_POST['detkar_TipoDocumento'] == 2){
                                    $fileName = 'PFE2' . $sufijo . '.' . $ext;
                                }else if($_POST['detkar_TipoDocumento'] == 3){
                                    $fileName = 'PFE3' . $sufijo . '.' . $ext;
                                }else if($_POST['detkar_TipoDocumento'] == 4){
                                    $fileName = 'PFE4' . $sufijo . '.' . $ext;
                                }else if($_POST['detkar_TipoDocumento'] == 5){
                                    $fileName = 'PFE5' . $sufijo . '.' . $ext;
                                }

                                
                                $destination = $directory . '/' . $fileName;

                                // Mover archivo
                                if (!move_uploaded_file($tmpName, $destination)) {
                                    $this->_ok      = 0;
                                    $this->_id      = 0;
                                    $this->_mensaje = "Error al mover archivo: $fileName";
                                    return false;
                                }

                                // Éxito
                                $this->_ok      = 1;
                                $this->_mensaje = "Archivo subido correctamente: $fileName";
                                return true;

                            } else {
                                $this->_ok      = 0;
                                $this->_id      = 0;
                                $this->_mensaje = "No se recibió archivo o ocurrió un error al subirlo";
                                return false;
                            }

                    }
        
        } else {
            $msj = "No se encontro Archivo";
            $this->_ok = 0;
            $this->_mensaje = "Error: No se recibió ningún archivo válido.";
        }
    
        return $msj;

    }

    // ELIMINAR EXOGENAS 
    protected function _eliminarExogenas() {
        
        $_objNota = new \predial\DAO_Exogenas();
        $_objNota->set_exo_Id($_POST['id']);

        if(!$_objNota->eliminar()){
            $this->_ok = 0;
            $this->_mensaje = $_objNota->getMysqlError();
        }else{
            $id = $_objNota->get_exo_Id();

            $this->_ok = 1;
            $this->_mensaje = "Exogena Eliminada eliminado correctamente";
        }  
        return $_objNota->eliminar();
    }  

}

class ExogenasException extends \Exception{}

    \predial\ControladorExogenas::run();

