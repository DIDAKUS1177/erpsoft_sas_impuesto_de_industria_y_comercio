/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
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
       
       
}
const _permisos = new Permisos();




