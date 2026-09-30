'use strict';
var ConfigPermisosRol = new Map();

class Login {  

    constructor() { 

        this.enviandoUsuario = false;
    }    


    /**
     * crearUsuario: Método para abrir modal de creación de Usuario.
     */
    async crearUsuario() {

        $("#clave").removeAttr('style');
        $("#usu_Clave").attr('required', true);
        $("#usu_Clave").removeClass("is-valid is-invalid");
        $("#passwordHelp div").removeClass("text-success").addClass("text-danger");
        $("#formCrearUsuario").trigger("reset");

        // Ocultar DV por defecto
        $("#usu_DV").closest('.form-group').hide();
        $("#usu_DV").prop("disabled", true).val("");

        $("#usu_IdTipoDocumento").prop("disabled", false);

        // Restaurar NIT si fue eliminado antes
        if ($("#usu_IdTipoDocumento option[value='5']").length === 0) {
            $("#usu_IdTipoDocumento").append('<option value="5">NIT</option>');
        }

        $("#btnCrearUsuario").empty();
        $("#btnCrearUsuario").append(
            '<span class="ti-plus"></span>' +
            ' Crear'
        );

        login.cargarCiudadesUsuario();

        //$("#formCrearUsuario").attr('action', 'javascript:login.postUsuario()');
        $('#modal-Usuario').modal({backdrop: 'static', keyboard: false})
        $("#modal-Usuario").modal('show');

    }

    /**
     * cargarCiudadesUsuario: llena el select2 de municipio de residencia con
     * el catalogo completo de conf_ciudades (mismo endpoint y mismo patron
     * que ya usa el modulo interno de contribuyentes -core/contribuyentes.js-).
     * Antes este formulario de inscripcion publica no pedia ciudad, y
     * class.usuarios.php grababa ind_IdCiudad=1 (Tunja) a ciegas para
     * cualquier contribuyente, sin importar donde estuviera realmente.
     */
    cargarCiudadesUsuario() {

        if ($.fn.select2 && $('#usu_IdCiudad').hasClass("select2-hidden-accessible")) {
            $('#usu_IdCiudad').select2('destroy');
        }

        $.ajax({
            url: 'business/controller/class.ciudades.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 1 },
            success: function (arr) {

                if (arr.ok != 1) { return; }

                $('#usu_IdCiudad').empty();
                $('#usu_IdCiudad').append('<option value=""></option>');

                $.each(arr.datos, function (i, ciudad) {
                    $('#usu_IdCiudad').append(
                        `<option value="${ciudad.ciu_Id}">${ciudad.ciu_Nombre} - ${ciudad.ciu_Departamento}</option>`
                    );
                });

                $('#usu_IdCiudad').select2({
                    dropdownParent: $('#modal-Usuario'),
                    width: '100%'
                });
            }
        });
    }


    async RecuperarUsuario() {
        $('#modal-RecuperarUsuario').modal({backdrop: 'static', keyboard: false})
        $("#modal-RecuperarUsuario").modal('show');
    }


     /**
     * postRecuperarUsuario: Método para crear postRecuperarUsuario
     */
    postRecuperarUsuario() {

        // Antes se pedia el correo. Ahora se identifica al usuario por su
        // NIT o cedula y el sistema envia la clave temporal al correo que
        // ya tiene registrado, para no depender de que recuerde cual uso.
        const documento = $("#usu_DocumentoRecuperar").val().trim();

        if (documento === '') {
            swal({
                type: 'warning',
                title: 'Atención',
                text: 'Debe ingresar su NIT o cédula'
            });
            return;
        }

        // 🔹 Mostrar loading moderno
        swal({
            title: 'Enviando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            onOpen: () => {
                swal.showLoading();
            }
        });

        $.ajax({
            url: 'business/controller/class.usuarios.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 5, documento: documento },

            success: function (arr) {

                if (arr.ok === 1) {

                    $("#modal-RecuperarUsuario").modal('hide');
                    $("#usu_DocumentoRecuperar").val('');

                    swal({
                        type: 'success',
                        title: 'Correo enviado',
                        // El backend devuelve el correo enmascarado para que la
                        // persona confirme a donde llego sin exponer la direccion.
                        text: (arr.datos && arr.datos.correo)
                            ? 'Se enviaron las instrucciones a ' + arr.datos.correo
                            : 'Se envió un correo con las instrucciones de recuperación.'
                    });

                } else {

                    swal({
                        type: 'error',
                        title: 'Error',
                        text: arr.mensaje || 'El documento no se encuentra registrado.'
                    });
                }
            },

            error: function () {

                swal({
                    type: 'error',
                    title: 'Error',
                    text: 'Ocurrió un problema al enviar el correo.'
                });
            }
        });
    }


    
    /**
     * postUsuario: Método para crear usuarios
     */
    postUsuario() {

        // 🔒 Si ya se está enviando, no permitir otro envío
        if (this.enviandoUsuario) {
            return false;
        }


        var clave = $("#usu_Clave").val();

        if (!login.validarPassword(clave)) {

            swal({
                type: 'warning',
                title: 'Contraseña inválida',
                text: 'La contraseña debe tener mínimo 8 caracteres, incluir mayúscula, minúscula y número.'
            });

            return false;
        }

        // El teléfono viaja solo con sus dígitos: "310 123 4567" o "310-123-4567"
        // es el mismo número, y así se guarda en el contribuyente (columna
        // numérica). Antes se mandaba tal cual y el registro fallaba a medias.
        var telefonoDigitos = String($("#usu_Telefono").val() || '').replace(/\D/g, '');
        if (telefonoDigitos.length < 7 || telefonoDigitos.length > 15) {
            swal({
                type: 'warning',
                title: 'Teléfono no válido',
                text: 'Escriba el teléfono con números, entre 7 y 15 dígitos (por ejemplo 3101234567).'
            });
            return false;
        }


         // 🔒 Activar bloqueo
        this.enviandoUsuario = true;

        // 🔘 Deshabilitar botón visualmente
        $("#btnCrearUsuario")
            .prop("disabled", true)
            .html('<span class="spinner-border spinner-border-sm"></span> Creando...');

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var nombres = $("#usu_Nombres").val();
        var apellidos = $("#usu_Apellidos").val();
        var telefono = telefonoDigitos;
        var direccion = $("#usu_Direccion").val();
        var idTipoDocumento = $("#usu_IdTipoDocumento").val();
        var idTipoPersona = $("#usu_IdTipoPersona").val();
        var DV = $("#usu_DV").val();    
        var documento = $("#usu_Documento").val();
        var mail = $("#usu_Correo").val();
        var clave = $("#usu_Clave").val();
        var rol = 4; // Usuario ICA WEB CONTRIBUTUYENTE;
        var usu = $("#usu_Usuario").val();
        var idCiudad = $("#usu_IdCiudad").val();

        console.log('rol ', rol);

        $.ajax({
            url: 'business/controller/class.usuarios.php',
            data: { funcion: 1, nombres: nombres, apellidos: apellidos, telefono: telefono, direccion: direccion,
                    idTipoDocumento: idTipoDocumento, numeroDocumento: documento, tipoPersona: idTipoPersona, DV: DV,
                    email: mail, id_rol: rol, clave: clave, usuario: usu, idCiudad: idCiudad},
            dataType: "json",
            type: "POST",
            success: function(arr) {

                login.enviandoUsuario = false;
                $("#btnCrearUsuario")
                    .prop("disabled", false)
                    .html('<span class="ti-plus"></span> Crear');
                
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
                } else if (arr.ok == 5) {
                    swal({
                        type: 'warning',
                        title: 'Usuario Creado - Contribuyente No Creado',
                        text: arr.mensaje,
                    });
                } else {
                    // Desde 2026-09-28 cuenta y contribuyente se crean juntos o no
                    // se crea nada, y el servidor dice por qué: se muestra tal cual.
                    swal({
                        type: 'error',
                        title: 'No se pudo completar el registro',
                        text: arr.mensaje || 'Revise los datos e intente de nuevo.',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                login.enviandoUsuario = false;
                $("#btnCrearUsuario")
                    .prop("disabled", false)
                    .html('<span class="ti-plus"></span> Crear');
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                // Esta pantalla no carga dist/menu.php, así que no tiene el aviso
                // global de errores: sin este swal, un fallo no decía NADA y la
                // persona volvía a intentar sin saber si su cuenta existía.
                swal({
                    type: 'error',
                    title: 'No se pudo completar el registro',
                    text: 'No hubo respuesta del servidor. Intente de nuevo en unos minutos; '
                        + 'si el problema sigue, comuníquese con la Secretaría de Hacienda.'
                });
            }
        });
    }

    
    postLogin() {
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

    /**
     * Los interruptores del panel de Roles que tiene el rol de la sesion
     * (funcion 6), con su tipo. Los usan el menu y cada pantalla (erpPuede).
     */
    getPermisosPantalla() {
        return $.ajax({
            url: 'business/controller/class.permisos.php',
            data: {funcion : 6},
            dataType: "json",
            type: "POST"
        });
    }

    getPermisosBoton(idRol, idBoton) {
        return $.ajax({
            url: 'business/controller/class.permisos.php',
            data: {funcion : 3, id_rol: idRol, id_boton : idBoton},
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

                    localStorage.setItem('Tipo_Usuario', postL.tipo_usuario);
                    localStorage.setItem('id_Usuario', postL.datos_usuario.usu_Id);
                    // Ni el nombre ni el documento de una gestión anterior
                    // sobreviven al login. El contribuyente activo se decide
                    // abajo, con el tipo de rol.
                    localStorage.removeItem('contribActivoNombre');
                    localStorage.removeItem('contribActivoDoc');
                    localStorage.setItem('id_Contribuyente', '');
                    localStorage.setItem('id_Rol', postL.datos_usuario.usu_Rol);
                    localStorage.setItem('documento', postL.datos_usuario.usu_NumeroDocumento);
                    localStorage.setItem('NomUsu',postL.datos_usuario.usu_Nombres + ' ' + postL.datos_usuario.usu_Apellidos);
                    localStorage.setItem('mailUsu',postL.datos_usuario.usu_Correo);

                    // La sesión vale por el día de COLOMBIA. toISOString() da la
                    // fecha UTC, que cambia a las 7 p. m. de aquí: dist/menu.php
                    // (validarSesion) sacaba a todos los que siguieran trabajando.
                    // Colombia no tiene horario de verano: UTC-5 fijo.
                    const fechaHoy = new Date(Date.now() - 5 * 60 * 60 * 1000).toISOString().slice(0, 10);
                    localStorage.setItem('fechaSesion', fechaHoy);

                    // Interruptores del panel de Roles (2026-09-29): los usan
                    // el menú y cada pantalla. Antes eran filas de "botones"
                    // (permisosRol) y las pantallas decidían por el número del rol.
                    var permisos = await login.getPermisosPantalla();
                    var p = (permisos && permisos.ok == 1) ? permisos.datos : null;
                    if (p && Array.isArray(p.claves) && (p.admin || p.claves.length)) {
                        localStorage.removeItem('permisosRol');
                        localStorage.setItem('erpPermisos', JSON.stringify(p));

                        // Un rol de la Alcaldía no es un contribuyente: entra SIN
                        // contribuyente activo y, si gestiona, elige uno en
                        // Contribuyentes > Gestionar. (Si su documento coincidía
                        // con alguien del padrón, quedaba "gestionando" a ese.)
                        localStorage.setItem('id_Contribuyente',
                            p.alcaldia ? '' : (postL.datos_usuario.usu_idContibuyente || ''));

                        // El contribuyente llega a su RIT; los demás, a Inicio.
                        if (!p.alcaldia && (p.admin || p.claves.indexOf('rit.ver') !== -1)) {
                            window.location = 'dist/icaWebRit.php';
                        } else {
                            window.location = 'dist/dashboard.php';
                        }
                    }else{
                        // Un rol sin ningún permiso, o inactivo, no entra: se dice
                        // por qué, en vez de "intente nuevamente".
                        localStorage.clear();
                        var inactivo = p && p.activo === false;
                        swal({
                            title: inactivo ? "Su rol está inactivo" : "Su cuenta no tiene permisos",
                            text: inactivo
                                ? "El rol de su cuenta fue inactivado. Comuníquese con el administrador de la Alcaldía."
                                : "Su rol todavía no tiene permisos asignados en el sistema. "
                                  + "Comuníquese con el administrador de la Alcaldía para que se los asigne.",
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
            // Sin esto, una respuesta caida (500, sin red) dejaba la pantalla sin
            // decir nada y a medio guardar en localStorage.
            console.error('login', error);
            localStorage.clear();
            swal({
                title: "No se pudo iniciar sesión",
                text: "No hubo respuesta del servidor. Intente de nuevo en unos minutos; "
                    + "si el problema sigue, comuníquese con la Secretaría de Hacienda.",
                type: "error"
            });
        }
    }

    // Método para calcular el dígito de verificación del NIT
    calcularDigitoVerificacion(nit) {
        let vpri = new Array(16);
        let x = 0;
        let y = 0;
        let z = nit.length;

        vpri[1] = 3; vpri[2] = 7; vpri[3] = 13; vpri[4] = 17; vpri[5] = 19;
        vpri[6] = 23; vpri[7] = 29; vpri[8] = 37; vpri[9] = 41; vpri[10] = 43;
        vpri[11] = 47; vpri[12] = 53; vpri[13] = 59; vpri[14] = 67; vpri[15] = 71;

        x = 0;
        for (let i = 0; i < z; i++) {
            y = (nit.substr(i, 1));
            x += (y * vpri[z - i]);
        }

        y = x % 11;
        return (y > 1) ? 11 - y : y;
    }
    
    // Método para validar la contraseña con expresión regular
    validarPassword(password) {

        // Expresión regular:
        // (?=.*[a-z])  → al menos una minúscula
        // (?=.*[A-Z])  → al menos una mayúscula
        // (?=.*\d)     → al menos un número
        // {8,}         → mínimo 8 caracteres

        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;

        return regex.test(password);
    }

    
    construirMenu() {
        try {
            console.log("Iniciando construcción de menú...");

            const permisos = ConfigPermisosRol.get('permisos');
            console.log("Permisos cargados:", permisos);

            if (!permisos || permisos.length === 0) {
                console.warn("⚠️ No se encontraron permisos para construir el menú.");
                return;
            }

            // Agrupar permisos por módulo
            const modulos = {};
            permisos.forEach(p => {
                if (!p.per_IdModulo || !p.per_IdSubmodulo) {
                    console.warn("Permiso inválido o incompleto:", p);
                    return;
                }
                if (!modulos[p.per_IdModulo]) {
                    modulos[p.per_IdModulo] = [];
                }
                modulos[p.per_IdModulo].push(p);
            });

            console.log("Módulos agrupados:", modulos);

            let menuHTML = '';

            Object.keys(modulos).forEach(modId => {
                const submodulos = modulos[modId];
                const nombreModulo = submodulos[0].mod_Nombre || `Módulo ${modId}`;
                const icono = submodulos[0].mod_Icono || 'dw dw-folder';

                menuHTML += `
                    <li class="dropdown" id="Modulo_${modId}">
                        <a href="javascript:;" class="dropdown-toggle">
                            <span class="micon ${icono}"></span>
                            <span class="mtext">${nombreModulo.toUpperCase()}</span>
                        </a>
                        <ul class="submenu" id="SubModulo_${modId}">
                `;

                submodulos.forEach(s => {
                    if (s.per_Estado == 1) {
                        menuHTML += `
                            <li>
                                <a href="javascript:void(0)" onclick="menu.validarIngreso(${s.per_IdModulo}, ${s.per_IdSubmodulo})">
                                    ${s.subMod_Nombre || "Sin nombre"}
                                </a>
                            </li>`;
                    } else {
                        console.log(`Submódulo inactivo omitido:`, s);
                    }
                });

                menuHTML += `
                        </ul>
                    </li>
                `;
            });

            $("#menuPrincipal").html(menuHTML);
            console.log("✅ Menú construido correctamente.");

        } catch (error) {
            console.error("❌ Error al construir el menú:", error);
        }
    }

}

  
const login = new Login();

$(document).on('change', '#usu_IdTipoPersona', function () {

    var tipoPersona = $(this).val();
    var selectDoc = $("#usu_IdTipoDocumento");

    if (tipoPersona == "2") { // Jurídica

        // 🔹 Ajustar tamaño cuando es Jurídica (6 columnas)
        $("#grupoNombres")
            .removeClass("col-md-6 col-md-8")
            .addClass("col-md-6");

        // Agregar NIT si no existe
        if (selectDoc.find("option[value='5']").length === 0) {
            selectDoc.append('<option value="5">NIT</option>');
        }

        // Forzar NIT y bloquear
        selectDoc
            .val("5")
            .prop("disabled", true)
            .trigger('change');

        $("#labelDocumento").text("* NIT");
        $("#labelNombres").text("* Razón Social");

        $("#grupoApellidos").hide();
        $("#usu_Apellidos").prop("required", false).val("");

    } else if (tipoPersona == "1") { // Natural

        // 🔹 Ajustar tamaño cuando es Natural (8 columnas)
        $("#grupoNombres")
            .removeClass("col-md-6 col-md-8")
            .addClass("col-md-8");

        // Habilitar select
        selectDoc.prop("disabled", false);

        // Eliminar opción NIT
        selectDoc.find("option[value='5']").remove();

        // Limpiar selección
        selectDoc.val("").trigger('change');

        $("#labelDocumento").text("* Documento");
        $("#labelNombres").text("* Nombres");

        $("#grupoApellidos").show();
        $("#usu_Apellidos").prop("required", true);
    }
});

$(document).on('keyup', '#usu_Documento', function () {

    if ($("#usu_IdTipoDocumento").val() == "5") {

        let nit = $(this).val();

        if (nit.length > 0) {
            let dv = login.calcularDigitoVerificacion(nit);
            $("#usu_DV").val(dv);
        } else {
            $("#usu_DV").val("");
        }
    }
});


$(document).on('change', '#usu_IdTipoDocumento', function () {

    var tipoDoc = $(this).val();

    if (tipoDoc == "5") { // NIT
        $("#usu_DV").closest('.form-group').show();
        $("#usu_DV").prop("disabled", false);
    } else {
        $("#usu_DV").closest('.form-group').hide();
        $("#usu_DV").prop("disabled", true).val("");
    }
});

$(document).on('keyup', '#usu_Clave', function () {

    var password = $(this).val();

    // Si está vacío → todo rojo y salir
    if (password.length === 0) {

        $("#req-length, #req-upper, #req-lower, #req-number")
            .removeClass("text-success")
            .addClass("text-danger");

        $("#usu_Clave")
            .removeClass("is-valid")
            .removeClass("is-invalid");

        return;
    }

    var tieneLongitud = password.length >= 8;
    var tieneMayuscula = /[A-Z]/.test(password);
    var tieneMinuscula = /[a-z]/.test(password);
    var tieneNumero = /\d/.test(password);

    function actualizarEstado(id, cumple) {
        if (cumple) {
            $(id).removeClass("text-danger").addClass("text-success");
        } else {
            $(id).removeClass("text-success").addClass("text-danger");
        }
    }

    actualizarEstado("#req-length", tieneLongitud);
    actualizarEstado("#req-upper", tieneMayuscula);
    actualizarEstado("#req-lower", tieneMinuscula);
    actualizarEstado("#req-number", tieneNumero);

    if (tieneLongitud && tieneMayuscula && tieneMinuscula && tieneNumero) {
        $("#usu_Clave").removeClass("is-invalid").addClass("is-valid");
    } else {
        $("#usu_Clave").removeClass("is-valid").addClass("is-invalid");
    }
});


document.addEventListener("DOMContentLoaded", function(){

    const correo = document.getElementById("usu_Correo");
    const usuario = document.getElementById("usu_Usuario");

    // Bloquear campo usuario
    usuario.readOnly = true;

    // Copiar correo → usuario en tiempo real
    correo.addEventListener("input", function(){
        usuario.value = correo.value;
    });

});
