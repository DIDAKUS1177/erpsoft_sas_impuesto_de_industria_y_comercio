<?php
namespace erpsoftsas;
include_once $_SERVER['DOCUMENT_ROOT'] . '/erpsoftsas/business/globals.php';
include_once SERVER . '/business/DAO/DAO_Peticiones.php';
include_once SERVER . '/business/DAO/DAO_TrazabilidadRadicado.php';
include_once SERVER . '/business/class.sessions.php';
include_once SERVER.'/business/controller/class.logs.php';

class ControladorPeticiones extends \erpsoftsas\Cabecera {
    private $_funcion;
    private $_ok;
    private $_mensaje;
    private $_pdf;
    private $_id;

    public static function run() {
        $_obj = new self();
        $_obj->_funcion = $_POST['funcion'];

        try {
            $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();
            $con->begin();
            $respuesta = null;
            switch ($_obj->_funcion) {
                case 1:
                    $respuesta = $_obj->_agregarPeticiones();
                    break;
                case 2:
                    $respuesta = $_obj->_editarPeticiones();
                    break;
                case 3:
                    $respuesta = $_obj->_consultarPeticiones();
                    break; 
                case 4:
                    $respuesta = $_obj->_inactivarPeticiones();
                    break; 
                case 5:
                    $respuesta = $_obj->_consultarPeticionesRangoFechas();
                    break; 
                case 6:
                    $respuesta = $_obj->_trazabilidadRadicadoEstados();
                    break; 
                case 7:
                    $respuesta = $_obj->_consultarTrazabilidadPeticiones();
                    break; 
            }
            $con->commit();
            header('Content-type: application/json');  
            echo json_encode(array("ok" => $_obj->_ok, "mensaje" => $_obj->_mensaje, "datos" => $respuesta, "pdf" => $_obj->_pdf, "id" => $_obj->_id));
        } catch (\erpsoftsas\PeticionesException $e) {
            $con->rollback();
            $arrRespu = array("ok" => $e->getCode(), "mensaje" => "Error: " . $e->getMessage(), "datos" => "","pdf" => "0");
            header('Content-type: application/json');  
            echo json_encode($arrRespu);
        }
    }

    protected function _agregarPeticiones() {
        $_obj = new \erpsoftsas\DAO_Peticiones();
        $_obj->set_pe_IdTipoPeticion($_POST['pe_IdTipoPeticion']);
        $_obj->set_pe_NombreCompleto($_POST['pe_NombreCompleto']);
        $_obj->set_pe_NumeroIdentificacion($_POST['pe_NumeroIdentificacion']);
        $_obj->set_pe_Direccion($_POST['pe_Direccion']);
        $_obj->set_pe_Telefono($_POST['pe_Telefono']);        

        if(isset($_POST['pe_CorreoElectronico'])){
            if (!empty($_POST['pe_CorreoElectronico']) || $_POST['pe_CorreoElectronico'] != NULL ) {
                $_obj->set_pe_CorreoElectronico($_POST['pe_CorreoElectronico']);
            }    
        }

        $_obj->set_pe_IdDependencia($_POST['pe_IdDependencia']);

        if(isset($_POST['pe_IdCategoria'])){
            if (!empty($_POST['pe_IdCategoria']) || $_POST['pe_IdCategoria'] != NULL ) {
                $_obj->set_pe_IdCategoria($_POST['pe_IdCategoria']);
            }    
        }

        if(isset($_POST['pe_IdSubCategoria'])){
            if (!empty($_POST['pe_IdSubCategoria']) || $_POST['pe_IdSubCategoria'] != NULL ) {
                $_obj->set_pe_IdSubCategoria($_POST['pe_IdSubCategoria']);
            }    
        }

        $_obj->set_pe_NumeroFolios($_POST['pe_NumeroFolios']);
        $_obj->set_pe_Prioridad($_POST['pe_Prioridad']);
        $_obj->set_pe_FormaRecepcion($_POST['pe_FormaRecepcion']);
        
        if(isset($_POST['pe_IdDependenciaResponsable'])){
            if (!empty($_POST['pe_IdDependenciaResponsable']) || $_POST['pe_IdDependenciaResponsable'] != NULL ) {
                $_obj->set_pe_IdDependenciaResponsable($_POST['pe_IdDependenciaResponsable']);                
            }    
        }

        if(isset($_POST['pe_IdEstadoTiposPeticion'])){
            if (!empty($_POST['pe_IdEstadoTiposPeticion']) || $_POST['pe_IdEstadoTiposPeticion'] != NULL ) {
                $_obj->set_pe_IdEstadoTiposPeticion($_POST['pe_IdEstadoTiposPeticion']);
            }    
        }
        
        $_obj->set_pe_Descripcion($_POST['pe_Descripcion']);

        if(isset($_POST['pe_Observaciones'])){
            if (!empty($_POST['pe_Observaciones']) || $_POST['pe_Observaciones'] != NULL ) {
                $_obj->set_pe_Observaciones($_POST['pe_Observaciones']);
            }    
        }
        
        $_obj->set_pe_Estado($_POST['pe_Estado']);

        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_pe_Id();
        
            $nomRol= $this->_agregarTrazabilidadInicial($id, $_POST['pe_IdTipoPeticion'], 
                $_POST['pe_IdEstadoTiposPeticion'], $_POST['pe_IdDependencia'], $_POST['pe_IdCategoria'],
                $_POST['pe_IdSubCategoria'], $_POST['pe_IdDependenciaResponsable'], $_POST['idUsuario']);

            // Definir ruta para guardar archivos
            $directory = '../../soportesRadicados/' . $id;
            // Verificar si la carpeta no existe y crearla
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0777, true)) {
                    $this->_ok = 0;
                    $this->_mensaje = "Error al crear directorio para soportes.";
                    return false;
                }
            }

            // Definir ruta para guardar archivos
            $directory = '../../soportesRadicados/' . $id.'/Radicacion';
            // Verificar si la carpeta no existe y crearla
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0777, true)) {
                    $this->_ok = 0;
                    $this->_mensaje = "Error al crear directorio para soportes.";
                    return false;
                }
            }

            // Procesar archivos subidos
            if (isset($_FILES['doc_Anexos']) && !empty($_FILES['doc_Anexos']['tmp_name'])) {
                foreach ($_FILES['doc_Anexos']['tmp_name'] as $index => $tmpName) {
                    if ($_FILES['doc_Anexos']['error'][$index] === UPLOAD_ERR_OK) {
                        $fileName = basename($_FILES['doc_Anexos']['name'][$index]);
                        $fileName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $fileName);
                        $destination = $directory . '/' . $fileName;

                        if (!move_uploaded_file($tmpName, $destination)) {
                            $this->_ok = 0;
                            $this->_id = 0;
                            $this->_mensaje = "Error al mover archivo: $fileName";
                            return false;
                        }
                    } else {
                        $this->_ok = 0;
                        $this->_id = 0;
                        $this->_mensaje = "Error al procesar archivo: " . $_FILES['doc_Anexos']['name'][$index];
                        return false;
                    }
                }
            }else{
                $this->_id = 0;
                $this->_ok = 0;
                $this->_mensaje = "errr  ingresados correctamente";
                return false;
            }

            $this->_id = $id;
            $this->_ok = 1;
            $this->_mensaje = "Datos ingresados correctamente";
        }

        return $_obj->guardar();
    }

    
    protected function _agregarTrazabilidadInicial($tra_IdPeticion, $tra_IdTipoPeticion, 
                                            $tra_IdEstadoTipoPeticion, $tra_IdDependencia, $tra_IdCategoria,
                                            $tra_IdSubCategoria, $pe_IdDependenciaResponsable, $tra_IdUsuario  ) {

        $_objTrasabilidad = new \erpsoftsas\DAO_TrazabilidadRadicado();
        $_objTrasabilidad->set_tra_IdPeticion($tra_IdPeticion);
        $_objTrasabilidad->set_tra_IdTipoPeticion($tra_IdTipoPeticion);
        $_objTrasabilidad->set_tra_IdEstadoTipoPeticion($tra_IdEstadoTipoPeticion);
        $_objTrasabilidad->set_tra_IdDependencia($tra_IdDependencia);
        $_objTrasabilidad->set_tra_IdCategoria($tra_IdCategoria);
        $_objTrasabilidad->set_tra_IdSubCategoria($tra_IdSubCategoria);
        $_objTrasabilidad->set_tra_Cambios('Registro de Radicado');

        $_objTrasabilidad->set_tra_IdDependenciaResponsable($pe_IdDependenciaResponsable);
        $_objTrasabilidad->set_tra_IdUsuario($tra_IdUsuario);


        if(!$_objTrasabilidad->guardar()) {
            return false;
        } else {
            return true;
        }
    }

    protected function _editarPeticiones() {
        $_obj = new \erpsoftsas\DAO_Peticiones();
        $_obj->set_pe_Id($_POST['id']);

        $_obj->set_pe_IdTipoPeticion($_POST['pe_IdTipoPeticion']);
        $_obj->set_pe_NombreCompleto($_POST['pe_NombreCompleto']);
        $_obj->set_pe_NumeroIdentificacion($_POST['pe_NumeroIdentificacion']);
        $_obj->set_pe_Direccion($_POST['pe_Direccion']);
        $_obj->set_pe_Telefono($_POST['pe_Telefono']);        

        if(isset($_POST['pe_CorreoElectronico'])){
            if (!empty($_POST['pe_CorreoElectronico']) || $_POST['pe_CorreoElectronico'] != NULL ) {
                $_obj->set_pe_CorreoElectronico($_POST['pe_CorreoElectronico']);
            }    
        }
        $_obj->set_pe_NumeroFolios($_POST['pe_NumeroFolios']);
        $_obj->set_pe_IdDependencia($_POST['pe_IdDependencia']);

        if(isset($_POST['pe_IdCategoria'])){
            if (!empty($_POST['pe_IdCategoria']) || $_POST['pe_IdCategoria'] != NULL ) {
                $_obj->set_pe_IdCategoria($_POST['pe_IdCategoria']);
            }    
        }

        if(isset($_POST['pe_IdSubCategoria'])){
            if (!empty($_POST['pe_IdSubCategoria']) || $_POST['pe_IdSubCategoria'] != NULL ) {
                $_obj->set_pe_IdSubCategoria($_POST['pe_IdSubCategoria']);
            }    
        }


        $_obj->set_pe_Prioridad($_POST['pe_Prioridad']);
        $_obj->set_pe_FormaRecepcion($_POST['pe_FormaRecepcion']);
        
        if(isset($_POST['pe_IdDependenciaResponsable'])){
            if (!empty($_POST['pe_IdDependenciaResponsable']) || $_POST['pe_IdDependenciaResponsable'] != NULL ) {
                $_obj->set_pe_IdDependenciaResponsable($_POST['pe_IdDependenciaResponsable']);                
            }    
        }

        if(isset($_POST['pe_IdEstadoTiposPeticion'])){
            if (!empty($_POST['pe_IdEstadoTiposPeticion']) || $_POST['pe_IdEstadoTiposPeticion'] != NULL ) {
                $_obj->set_pe_IdEstadoTiposPeticion($_POST['pe_IdEstadoTiposPeticion']);
            }    
        }
        
        $_obj->set_pe_Descripcion($_POST['pe_Descripcion']);

        if(isset($_POST['pe_Observaciones'])){
            if (!empty($_POST['pe_Observaciones']) || $_POST['pe_Observaciones'] != NULL ) {
                $_obj->set_pe_Observaciones($_POST['pe_Observaciones']);
            }    
        }

        
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $idRadicado = $_obj->get_pe_Id();
            
            $nomRol= $this->_trazabilidadRadicado($idRadicado,$_POST['pe_IdTipoPeticion'],$_POST['pe_IdEstadoTiposPeticion'],
                        $_POST['pe_IdDependencia'], $_POST['pe_IdCategoria'], $_POST['pe_IdSubCategoria'], $_POST['cambios'], $_POST['idDependenciaResponsable'], $_POST['usuarioLogueado']  );

            $this->_ok = 1;
            $this->_id = $idRadicado;
            $this->_mensaje = "Datos actualizados correctamente";
        }
        return $_obj->guardar();
    }

    protected function _trazabilidadRadicado($idRadicado, $pe_IdTipoPeticion, $pe_IdEstadoTiposPeticion,
                                        $pe_IdDependencia, $pe_IdCategoria, $pe_IdSubCategoria, $cambios,
                                        $idDependenciaResponsable, $usuarioLogueado) {

        $_objTrasabilidad = new \erpsoftsas\DAO_TrazabilidadRadicado();
        $_objTrasabilidad->set_tra_IdPeticion($idRadicado);
        $_objTrasabilidad->set_tra_IdTipoPeticion($pe_IdTipoPeticion);
        $_objTrasabilidad->set_tra_IdEstadoTipoPeticion($pe_IdEstadoTiposPeticion);
        $_objTrasabilidad->set_tra_IdDependencia($pe_IdDependencia);
        $_objTrasabilidad->set_tra_IdCategoria($pe_IdCategoria);
        $_objTrasabilidad->set_tra_IdSubCategoria($pe_IdSubCategoria);
        $_objTrasabilidad->set_tra_Cambios($cambios);

        $_objTrasabilidad->set_tra_IdDependenciaResponsable($idDependenciaResponsable);
        $_objTrasabilidad->set_tra_IdUsuario($usuarioLogueado);

        if(!$_objTrasabilidad->guardar()){
            $this->_ok = 0;
            $this->_mensaje = $_objTrasabilidad->getMysqlError();
            return false;
        }

    }
    

    private function _consultarTrazabilidadPeticiones() {
        $_obj = new \erpsoftsas\DAO_TrazabilidadRadicado();

        if(isset($_POST['idRadicado'])){
            if (!empty($_POST['idRadicado']) || $_POST['idRadicado'] != NULL ) {
                $_obj->set_tra_IdPeticion($_POST['idRadicado']);
            }    
        }

        $_obj->habilita1ResultadoEnArray();
        $arr = $_obj->consultar();
        if(is_array($arr) && count($arr)) {
            $R = [];
            foreach($arr as $obj) {
                $R[] = $obj->getArray();
            }

            $idRadicado = $_POST['idRadicado'];
            $directory = '../../soportesRadicados/' . $idRadicado;
            $result = [];
            $folders = scandir($directory);
            
            foreach ($folders as $folder) {
                if ($folder !== '.' && $folder !== '..' && is_dir($directory . '/' . $folder)) {
                    $pdfFiles = glob($directory . '/' . $folder . '/*.pdf');
                    
                    $pdfList = array_map(function($filePath) use ($directory, $idRadicado) {
                        $fileWithoutDirectory = str_replace($directory . '/', '', $filePath);
                        return 'soportesRadicados/' . $idRadicado . '/' . $fileWithoutDirectory;
                    }, $pdfFiles);
            
                    $result[] = [
                        'folder' => $folder,
                        'files' => $pdfList,
                    ];
                }
            }
            
            $this->_pdf = $result;
            
            $this->_pdf = $result;
            $this->_ok = 1;
            $this->_id = $_obj->get_tra_Id();
            $this->_mensaje = "Trazabilidad listados con éxito"; 
        } else {
            $R = $_obj;
            $this->_ok = 0;
            $this->_id = 0;
            $this->_pdf = 0;
            $this->_mensaje = "No existen Trazabilidad";            
        }
        return $R;
    }



    private function _consultarPeticiones() {
        $_obj = new \erpsoftsas\DAO_Peticiones();

                
        if(isset($_POST['id'])){
            if (!empty($_POST['id']) || $_POST['id'] != NULL ) {
                $_obj->set_pe_Id($_POST['id']);
            }    
        }

        if(isset($_POST['pe_IdTipoPeticion'])){
            if (!empty($_POST['pe_IdTipoPeticion']) || $_POST['pe_IdTipoPeticion'] != NULL ) {
                $_obj->set_pe_IdTipoPeticion($_POST['pe_IdTipoPeticion']);
            }    
        }

        if(isset($_POST['pe_IdDependencia'])){
            if (!empty($_POST['pe_IdDependencia']) || $_POST['pe_IdDependencia'] != NULL ) {
                $_obj->set_pe_IdDependencia($_POST['pe_IdDependencia']);
            }    
        }
        
        if(isset($_POST['pe_IdCategoria'])){
            if (!empty($_POST['pe_IdCategoria']) || $_POST['pe_IdCategoria'] != NULL ) {
                $_obj->set_pe_IdCategoria($_POST['pe_IdCategoria']);
            }    
        }

        if(isset($_POST['pe_IdSubCategoria'])){
            if (!empty($_POST['pe_IdSubCategoria']) || $_POST['pe_IdSubCategoria'] != NULL ) {
                $_obj->set_pe_IdSubCategoria($_POST['pe_IdSubCategoria']);
            }    
        }
        
        if(isset($_POST['pe_Prioridad'])){
            if (!empty($_POST['pe_Prioridad']) || $_POST['pe_Prioridad'] != NULL ) {
                $_obj->set_pe_Prioridad($_POST['pe_Prioridad']);
            }    
        }

        if(isset($_POST['pe_IdDependenciaResponsable'])){
            if (!empty($_POST['pe_IdDependenciaResponsable']) || $_POST['pe_IdDependenciaResponsable'] != NULL ) {
                $_obj->set_pe_IdDependenciaResponsable($_POST['pe_IdDependenciaResponsable']);
            }    
        }

        if(isset($_POST['pe_IdEstadoTiposPeticion'])){
            if (!empty($_POST['pe_IdEstadoTiposPeticion']) || $_POST['pe_IdEstadoTiposPeticion'] != NULL ) {
                $_obj->set_pe_IdEstadoTiposPeticion($_POST['pe_IdEstadoTiposPeticion']);
            }    
        }

        $_obj->habilita1ResultadoEnArray();
        $arr = $_obj->consultar();
        if(is_array($arr) && count($arr)) {
            $R = [];
            foreach($arr as $obj) {
                $R[] = $obj->getArray();
            }

                // Obtener el host y el esquema de la URL actual de forma dinámica
                $host = $_SERVER['HTTP_HOST'];
                $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

                $directory = '../../soportesRadicados/' . $_POST['id'] .'/Radicacion';
                $baseUrl = "$scheme://$host/erpsoftsas/soportesRadicados/". $_POST['id'].'/Radicacion';

                // Obtener todos los archivos PDF en el directorio y sus subdirectorios
                $pdfFiles = glob($directory . '/*.pdf');

                if (count($pdfFiles) === 0) {
                    $this->_pdf = 0;
                }

                // Generar el HTML para los enlaces
                $links = '';
                foreach ($pdfFiles as $pdf) {
                    $fileName = basename($pdf);

                    $fileUrl = $baseUrl . '/' . $fileName;
                    $links .= "<a href='$fileUrl' target='_blank'> Abrir $fileName</a><br>";
                }

                $this->_pdf = $links;

            $this->_ok = 1;
            $this->_id = $_obj->get_pe_Id();
            $this->_mensaje = "Peticioness listados con éxito"; 
        } else {
            $R = $_obj;
            $this->_ok = 0;
            $this->_id = 0;
            $this->_pdf = 0;
            $this->_mensaje = "No existen Peticioness";            
        }
        return $R;
    }

    protected function _inactivarPeticiones() {
        $_obj = new \erpsoftsas\DAO_Peticiones();
        $_obj->set_pe_Id($_POST['id']);
        $_obj->set_pe_Estado($_POST['estado']);
        if(!$_obj->guardar()) {
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = $_obj->getMysqlError();
        } else {
            $id = $_obj->get_pe_Id();
            //$_objlogs = new logs();
            //$_objlogs->_insertLogs($id,1,2,9);
            $this->_ok = 1;
            $this->_id = $id;
            $this->_mensaje = "Peticiones inactivado correctamente";
        }
        return $_obj->getArray();
    }


       /**
    *** Realiza el proceso de consultar los detalles 
    *** de las notas por rango de fechas
    **/
    private function _consultarPeticionesRangoFechas(){
        $con = \ConexionMysqlUsuariosCentral\ConexionSQL::getInstance();

        $fechaInicial =  $_POST['fechaInicial']; 
        $fechaFinal =  $_POST['fechaFinal'];    

        $idRol =  $_POST['idRol'];    
        $idResponsable =  $_POST['idResponsable'];    
      
        if($fechaInicial == $fechaFinal){
            if($idRol == 1){
                $query = "SELECT *, (select tipe.tipe_Nombre from  tipos_peticiones as tipe where tipe.tipe_Id = proo.pe_IdTipoPeticion) as strTipoDocumento,
                        (select dep.dep_Nombre from  dependencia as dep where dep.dep_Id = proo.pe_IdDependencia) as strIdDependencia,
                        (select esta.est_Nombre from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = proo.pe_IdEstadoTiposPeticion) as strEstadoActual, (select esta.est_Color from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = proo.pe_IdEstadoTiposPeticion) as strEstadoActualColor
                                        FROM peticiones as proo 
                        WHERE proo.created_at like '%$fechaInicial%'";
            }else{
                $query = "SELECT *, (select tipe.tipe_Nombre from  tipos_peticiones as tipe where tipe.tipe_Id = proo.pe_IdTipoPeticion) as strTipoDocumento,
                            (select dep.dep_Nombre from  dependencia as dep where dep.dep_Id = proo.pe_IdDependencia) as strIdDependencia,
                            (select esta.est_Nombre from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = proo.pe_IdEstadoTiposPeticion) as strEstadoActual, (select esta.est_Color from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = proo.pe_IdEstadoTiposPeticion) as strEstadoActualColor
                                            FROM peticiones as proo 
                        WHERE proo.created_at  like '%$fechaInicial%'
                    and pe_IdDependenciaResponsable = $idResponsable";
            }
		}else{
            if($idRol == 1){
                $query = "SELECT *, (select tipe.tipe_Nombre from  tipos_peticiones as tipe where tipe.tipe_Id = proo.pe_IdTipoPeticion) as strTipoDocumento,
                            (select dep.dep_Nombre from  dependencia as dep where dep.dep_Id = proo.pe_IdDependencia) as strIdDependencia,
                            (select esta.est_Nombre from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = proo.pe_IdEstadoTiposPeticion) as strEstadoActual, (select esta.est_Color from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = proo.pe_IdEstadoTiposPeticion) as strEstadoActualColor
                        FROM peticiones as proo 
                        WHERE ((proo.created_at  BETWEEN '$fechaInicial' AND '$fechaFinal') 
                        or proo.created_at  like '%$fechaFinal%')";
            }else{
                $query = "SELECT *, (select tipe.tipe_Nombre from  tipos_peticiones as tipe where tipe.tipe_Id = proo.pe_IdTipoPeticion) as strTipoDocumento,
                            (select dep.dep_Nombre from  dependencia as dep where dep.dep_Id = proo.pe_IdDependencia) as strIdDependencia,
                            (select esta.est_Nombre from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = proo.pe_IdEstadoTiposPeticion) as strEstadoActual, (select esta.est_Color from estados as esta INNER JOIN estados_tipos_peticiones as estati on estati.estipe_IdEstado = esta.est_Id where estati.estipe_Id  = proo.pe_IdEstadoTiposPeticion) as strEstadoActualColor
                        FROM peticiones as proo  
                    WHERE ((proo.created_at  BETWEEN '$fechaInicial' AND '$fechaFinal') 
                        or proo.created_at  like '%$fechaFinal%')
                        and pe_IdDependenciaResponsable = $idResponsable";
            }
        }

        $data = $con->consultar($query);

        if( $con->getNumeroFilasConsultadas($data) >0 ){ 
            while($res = $con->obnerFila($data)){
                $row[] = $res;
            }
            $this->_ok = 1;
            $this->_mensaje = "detalles listados";
        }else{
            $this->_ok = 0;
            $this->_mensaje = "No existen detalles";
            $row = NULL;
        }
        return $row;  
    }

    /**
    *** Realiza el proceso de Insertar la trazabilidad por cambio de estado.
    **/
    protected function _trazabilidadRadicadoEstados() {

        $_objTrasabilidad = new \erpsoftsas\DAO_TrazabilidadRadicado();
        $_objTrasabilidad->set_tra_IdPeticion($_POST['tra_IdPeticion']);
        $_objTrasabilidad->set_tra_IdTipoPeticion($_POST['tra_IdTipoPeticion']);
        $_objTrasabilidad->set_tra_IdEstadoTipoPeticion($_POST['tra_IdEstadoTipoPeticion']);
        $_objTrasabilidad->set_tra_IdDependencia($_POST['tra_IdDependencia']);
        $_objTrasabilidad->set_tra_IdCategoria($_POST['tra_IdCategoria']);
        $_objTrasabilidad->set_tra_IdSubCategoria($_POST['tra_IdSubCategoria']);
        $_objTrasabilidad->set_tra_Cambios($_POST['tra_Cambios']);

        $_objTrasabilidad->set_tra_Observaciones($_POST['tra_Observaciones']);

        $_objTrasabilidad->set_tra_IdDependenciaResponsable($_POST['pe_IdDependenciaResponsable']);
        $_objTrasabilidad->set_tra_IdUsuario($_POST['tra_IdUsuario']);


        if(!$_objTrasabilidad->guardar()) {
            $this->_ok = 0;
            $this->_id = 0;
            $this->_mensaje = $_objTrasabilidad->getMysqlError();
        } else {
            $id = $_objTrasabilidad->get_tra_Id();
        
            // Actualiza Estado .
            $nomRol= $this->_ActualizarEstadoRadicado($_POST['tra_IdPeticion'],$_POST['tra_IdEstadoTipoPeticion']);

            // Definir ruta para guardar archivos
            $directory = '../../soportesRadicados/' . $_POST['tra_IdPeticion']. '/'.$_POST['nomEstadoNew'];
            // Verificar si la carpeta no existe y crearla
            if (!is_dir($directory)) {
                if (!mkdir($directory, 0777, true)) {
                    $this->_ok = 0;
                    $this->_mensaje = "Error al crear directorio para soportes.";
                    return false;
                }
            }

            // Procesar archivos subidos
            if (isset($_FILES['doc_Anexos']) && !empty($_FILES['doc_Anexos']['tmp_name'])) {
                foreach ($_FILES['doc_Anexos']['tmp_name'] as $index => $tmpName) {
                    if ($_FILES['doc_Anexos']['error'][$index] === UPLOAD_ERR_OK) {
                        $fileName = basename($_FILES['doc_Anexos']['name'][$index]);
                        $fileName = preg_replace("/[^a-zA-Z0-9._-]/", "_", $fileName);
                        $destination = $directory . '/' . $fileName;

                        if (!move_uploaded_file($tmpName, $destination)) {
                            $this->_ok = 0;
                            $this->_id = 0;
                            $this->_mensaje = "Error al mover archivo: $fileName";
                            return false;
                        }
                    } else {
                        $this->_ok = 0;
                        $this->_id = 0;
                        $this->_mensaje = "Error al procesar archivo: " . $_FILES['doc_Anexos']['name'][$index];
                        return false;
                    }
                }
            }else{
                $this->_id = 0;
                $this->_ok = 0;
                $this->_mensaje = "errr  ingresados correctamente";
                return false;
            }

            $this->_id = $id;
            $this->_ok = 1;
            $this->_mensaje = "Datos ingresados correctamente";
        }

        return $_objTrasabilidad->guardar();

    }


    protected function _ActualizarEstadoRadicado($idPeticion, $IdEstadoPeticion) {

        $_obj = new \erpsoftsas\DAO_Peticiones();
        $_obj->set_pe_Id($idPeticion);
        $_obj->set_pe_IdEstadoTiposPeticion($IdEstadoPeticion);
              
        if(!$_obj->guardar()) {
            return false;
        }else{  
            return true;
        }

    }
    

}

class PeticionesException extends \Exception { }
\erpsoftsas\ControladorPeticiones::run();
