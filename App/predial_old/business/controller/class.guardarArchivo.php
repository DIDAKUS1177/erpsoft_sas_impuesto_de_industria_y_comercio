<?php

//Si se quiere subir una imagen

   //Recogemos el archivo enviado por el formulario
   $archivo = $_FILES['imageSoporte']['name'];
   //Si el archivo contiene algo y es diferente de vacio
   if (isset($archivo) && $archivo != "") {
      //Obtenemos algunos datos necesarios sobre el archivo
      $tipo = $_FILES['imageSoporte']['type'];
      $tamano = $_FILES['imageSoporte']['size'];
      $temp = $_FILES['imageSoporte']['tmp_name'];
      //Se comprueba si el archivo a cargar es correcto observando su extensión y tamaño
     if (!((strpos($tipo, "jpeg") || strpos($tipo, "jpg") ) && ($tamano < 2000000))) {
        echo 'error';
     }
     else {
        //Si la imagen es correcta en tamaño y tipo
        //Se intenta subir al servidor
        //$nom = random_int(1, 50000);
        $nomm = 'logo.jpeg';
        
        if (move_uploaded_file($temp, '../../extensiones/tcpdf/pdf/images/'.$nomm)) {
            
            echo ($nomm);
            //Cambiamos los permisos del archivo a 777 para poder modificarlo posteriormente
            chmod('../../extensiones/tcpdf/pdf/images/'.$nomm, 0777);
            //Mostramos el mensaje de que se ha subido co éxito
            //echo '<div><b>Se ha subido correctamente la imagen.</b></div>';
            //Mostramos la imagen subida
            //echo '<p><img src="images/'.$archivo.'"></p>';
        }else{
           //Si no se ha podido subir la imagen, mostramos un mensaje de error
           echo 'error';
        }
      }
   }
   ?>