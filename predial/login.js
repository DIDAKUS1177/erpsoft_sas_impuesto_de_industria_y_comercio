'use strict';
var ConfigPermisosRol = new Map();
class Login {    

    constructor() { }    

    /**
     * crearUsuario: Método para abrir modal de creación de Usuario.
     */
    async crearUsuario() {

        $("#clave").removeAttr('style');
        $("#usu_Clave").attr('required', true);
        $("#formCrearUsuario").trigger("reset");
        $("#btnCrearUsuario").empty();
        $("#btnCrearUsuario").append(
            '<span class="ti-plus"></span>' +
            ' Crear'
        );
        $("#modal-Usuario").modal('show');
        
    }


    async RecuperarUsuario() {
        $("#modal-RecuperarUsuario").modal('show');
    }


    /**
     * postRecuperarUsuario: Método para crear postRecuperarUsuario
     */
    postRecuperarUsuario() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var mail = $("#usu_CorreoRecuperar").val();

           swal({
                type: 'success',
                title: 'Usuario Recuperado',
                text: 'Se envio un Email con los datos respectivos.',
            });

            $("#modal-RecuperarUsuario").modal('hide');
 /*                   
        $.ajax({
            url: 'business/controller/class.usuarios.php',
            data: { funcion: 1, nombre: nombre, numeroDocumento: documento, 
                    email: mail, id_rol: rol, clave: clave, usuario: usu, sede: 1, caja: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearUsuario").trigger("reset");
                    $("#modal-Usuario").modal('hide');
                 
                    swal({
                        type: 'success',
                        title: 'Usuario Recuperado',
                        text: 'Se envio un Email con los datos respectivos.',
                    });
                    
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo Recuperar el usuario',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });

*/

    }


    /**
     * postUsuario: Método para crear usuarios
     */
    postUsuario() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombre = $("#usu_Nombre").val();
        var documento = $("#usu_Documento").val();
        var mail = $("#usu_Correo").val();
        var clave = $("#usu_Clave").val();
        var rol = 2 ;
        var usu = $("#usu_Usuario").val();

        console.log('rol ', rol);

        $.ajax({
            url: 'business/controller/class.usuarios.php',
            data: { funcion: 1, nombre: nombre, numeroDocumento: documento, 
                    email: mail, id_rol: rol, clave: clave, usuario: usu, sede: 1, caja: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearUsuario").trigger("reset");
                    $("#modal-Usuario").modal('hide');
                 
                    swal({
                        type: 'success',
                        title: 'Usuario creado',
                        text: 'Usuario creado exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Email duplicado',
                        text: arr.mensaje,
                    });
                } else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Identificación duplicada',
                        text: arr.mensaje,
                    });
                }else if (arr.ok == 4) {
                    swal({
                        type: 'warning',
                        title: 'Uuario duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el usuario',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }


    postLogin() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */
        console.log('usuario ',$("#email").val(),' clave ',$("#password").val())
        return $.ajax({
            url : 'business/controller/class.login.php',
            data : {u_correo_inst : $("#email").val(), u_id_genesis: $("#password").val()},
            type : 'POST',
            dataType : 'json'
           
        });
    }    

    getPermisos(rol) {
       
        return $.ajax({
             url: 'business/controller/class.permisos.php',
             data: {funcion : 3, id_rol :  rol},
             dataType: "json",
             type: "POST"
             
         });
    }

    async init(){
        try{
            var postL = await login.postLogin();
           
            if(postL.ok == 0){
                console.log('Error');
                toastr.error('Verifique su conexión','Error',{
                    'progressBar':true,
                    'positionClass': 'toast-top-right'
                });
            }else if(postL.ok == 1){ 

                var n =  new Date();
                var y = n.getFullYear();
                var m = n.getMonth()+1;        
                var d = n.getDate();
                if(d<10){ d='0'+d; }
                if(m<10){ m='0'+m; }
                var fechafull = y + "-" + m + "-" + d;
                
                // VALIDA LICENCIA LOCAL POR FECHA
                if(fechafull <= '2100-01-01'){

                    console.log('->', postL.tipo_usuario);
                    //console.log(json.url);
                    //CD.traerMenu();
                    sessionStorage.setItem('Tipo_Usuario', postL.tipo_usuario);
                    sessionStorage.setItem('id_Usuario', postL.datos_usuario.usu_Id);
                    sessionStorage.setItem('id_Rol', postL.datos_usuario.usu_Rol);
                    sessionStorage.setItem('NomUsu',postL.datos_usuario.usu_Nombre);
                    sessionStorage.setItem('mailUsu',postL.datos_usuario.usu_Correo);
                   
    
                    //var resp = await Permisos(json.usu_Rol);
                    console.log('postt ', postL)
                    var permisos = await login.getPermisos(postL.datos_usuario.usu_Rol);
                    console.log('permisos ', permisos)
                    if(permisos.ok == 1){
                        ConfigPermisosRol.set('permisos',permisos.datos);
                       
                        console.log('perm ', permisos);
                        if(postL.tipo_usuario == 1){
                            window.location = 'pages/cliente.php';
                        }else if(postL.tipo_usuario == 0){
                            window.location = 'pages/proyecto.php';
                        }else{
							
							var idRol = sessionStorage.getItem('id_Rol');
							
							if(idRol != 1){
								window.location = 'dist/exogenas.php';
							}else{
                                window.location = 'dist/exogenas.php';
							}
             
                        }
                    }else{
                        swal({
                            title:"Error",
                            text:"El sistema no pudo cargar los privilegios, intente nuevamente",
                            type:"warning"
                        })
                    }
                    //window.location = 'dist/dashboard.php';

                    
                }else{
                    swal({
                        title:"Error",
                        text:"Su licencia esta Bloqueada por Pago. Contactese con el Administrador",
                        type:"warning"
                    })
                }
               
                
            }else  if(postL.ok == 2){
                console.log('Error');
                swal({
                    title:"Error de credenciales",
                    text:"Verifique sus datos e intente nuevamente",
                    type:"warning"
                })

            }
           
        }
        catch (error){

        }
    }
}

  
const login = new Login();

