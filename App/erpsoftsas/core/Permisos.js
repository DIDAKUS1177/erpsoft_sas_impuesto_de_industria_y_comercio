/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(localStorage.getItem('postlogin'));
console.log(_postlogin);
var enable = true; */

// Declaración de variables
//'use strict';

class Permisos {

    constructor() { }

    getPermisos(idRol, idBoton) {
        return $.ajax({
            url: '../business/controller/class.permisos.php',
            data: {funcion : 3, id_rol: idRol, id_boton : idBoton},
            dataType: "json",
            type: "POST"
        });
    }

    getvalidarSesison() {

        if(localStorage.getItem('id_Usuario') == null ){
            window.location = '../index.php';
        }

    }
       
       
}
const _permisos = new Permisos();

_permisos.getvalidarSesison();




