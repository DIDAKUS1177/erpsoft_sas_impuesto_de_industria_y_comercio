/*    METDOS DEL MODULO DE DEPENDENCIA    */

var enable = true;
var idRol = localStorage.getItem('id_Rol');

class Contribuyentes {

    constructor() {}

    /**
     * crearUsuario: Método para abrir modal de creación de Contribuyentes.
     */
    async crearContribuyentes() {

        // Resetear estado visual
        $("#ind_IdTipoDocumento").prop("disabled", false);
        $("#ind_DV").closest(".col-md-2").hide();
        $("#ind_DV").prop("required", false);
        $("#ind_PrimerApellido").closest(".col-md-6").show();
        $("#ind_SegundoApellido").closest(".col-md-6").show();

        $("#ind_PrimerNombre").closest(".form-group")
            .find("label")
            .text("* Primer Nombre");

        //Parametro: 27 (2= Modulo Usuario, 7:Permiso Crear Usuario)
        var permiso = await _permisos.getPermisos(idRol, 1639);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#formCrearContribuyentes").trigger("reset");
            $("#btnCrearContribuyentes").empty();
            $("#btnCrearContribuyentes").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearContribuyentes").attr('action', 'javascript:contribuyentes.postContribuyentes()');
            $('#modal-Contribuyentes').modal({backdrop: 'static', keyboard: false})
            $("#modal-Contribuyentes").modal('show');

            

            setTimeout(function(){

                if ($.fn.select2 && $('#ind_IdCiudad').hasClass("select2-hidden-accessible")) {
                    $('#ind_IdCiudad').select2('destroy');
                }

                $('#ind_IdCiudad').select2({
                    dropdownParent: $('#modal-Contribuyentes'),
                    placeholder: "Buscar ciudad...",
                    width: '100%'
                });

            }, 200);

        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de Contribuyentes 
     * @param type $arrFilter: Listado de objetos Contribuyentes
     */
    draw_table_documents(arrFilter) {
        
        $("#contribuyentesRegistrados").DataTable().destroy();
        $("#bodyContribuyentesRegistrados").empty();
        for (let dep of arrFilter) {
            if (dep.ind_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Inactivar Contribuyente";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Activar Contribuyente";
            }

                $('#bodyContribuyentesRegistrados').append(
                    '<tr>' +
                    '<td>' +
                    dep.ind_NumeroIdentificacion + 
                    '</td>' +
                    '<td>' +
                    dep.ind_PrimerNombre + 
                    '</td>' +
                    '<td>' +
                    dep.ind_PrimerApellido +
                    '</td>' +
                    '<td>' +
                    dep.ind_Direccion +
                    '</td>' +
                    
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Contribuyentes" style="margin-right:5px" onclick="javascript:contribuyentes.getContribuyentesById(' + dep.ind_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</button>' +

                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:contribuyentes.cambiarEstado(' + dep.ind_Id + ',' + dep.ind_Estado + ')">' +
                    '<i class="' + icono + '"></i>' +
                    '</button>' +
                    '</td>' +
                    '</tr>'
                );
            
        }
        contribuyentes.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de Dependencia
     */
    init_table() {
        $('.data-table').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [
                { targets: "datatable-nosort", orderable: false,},
                { "width": "10%", "targets": 0 },
                { "width": "20%", "targets": 1 },
                { "width": "20%", "targets": 2 }
            ],
            "lengthMenu": [
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Contribuyentes registrados',
                "info": 'Mostrando _START_ a _END_ de _TOTAL_ Entradas',
                'infoEmpty': 'Mostrando 0 to 0 of 0 Entradas',
                'infoFiltered': '(Filtrado de _MAX_ total entradas)',
                'infoPostFix': '',
                'thousands': ',',
                'lengthMenu': 'Mostrar _MENU_ Entradas',
                'loadingRecords': 'Cargando...',
                'processing': 'Procesando...',
                'search': 'Buscar:',
                'searchPlaceholder': 'Buscar....',
                'zeroRecords': 'Sin resultados encontrados',
                'paginate': {
                    'first': 'Primero',
                    'last': 'Último',
                    'next': 'Siguiente',
                    'previous': 'Anterior',
                },
                paginate: {
                    next: '<i class="ion-chevron-right"></i>',
                    previous: '<i class="ion-chevron-left"></i>'
                }
            },

        });
    }

    /**
     * getDependencia: Método para consultar Contribuyentes
     */
    getContribuyentes() {
        
        $.ajax({
            url: '../business/controller/class.contribuyentes.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                if (arr.ok == 1) {
                    $("#bodyContribuyentesRegistrados").empty();
                    contribuyentes.draw_table_documents(arr.datos);
                } else {
                    $("#contribuyentesRegistrados").DataTable().destroy();
                    contribuyentes.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * getUsuarioById: Método para consultar la
     * información de un Dependencia
     * @param type $id: llave primaria de la tabla Dependencia
     */
    async getContribuyentesById(id) {
        
        $('#loading').show();
        $('#wrapper').addClass('body-load');
        var permiso = await _permisos.getPermisos(idRol, 312);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.contribuyentes.php',
                data: { funcion: 3, ind_Id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        console.log('arr editar ', arr);
                        for (let datos of arr.datos) {
                            $("#ind_NumeroIdentificacion").val(datos.ind_NumeroIdentificacion);
                            $("#ind_DV").val(datos.ind_DV);
                            $("#ind_IdTipoDocumento").val(datos.ind_IdTipoDocumento);
                            $("#ind_PrimerNombre").val(datos.ind_PrimerNombre);
                            $("#ind_SegundoNombre").val(datos.ind_SegundoNombre);
                            $("#ind_PrimerApellido").val(datos.ind_PrimerApellido);
                            $("#ind_SegundoApellido").val(datos.ind_SegundoApellido);
                            $("#ind_Direccion").val(datos.ind_Direccion);
                            $("#ind_IdCiudad").val(datos.ind_IdCiudad);
                            $("#ind_Persona").val(datos.ind_Persona);
                            $("#ind_IdRegimen").val(datos.ind_IdRegimen);
                            $("#ind_Telefono").val(datos.ind_Telefono);
                            $("#ind_Email").val(datos.ind_Email);
                        }
                        contribuyentes.aplicarModoPersona($("#ind_Persona").val());
                        $("#formCrearContribuyentes").attr('action', 'javascript:contribuyentes.editContribuyentes(' + id + ')');
                        $("#btnCrearContribuyentes").empty();
                        $("#btnCrearContribuyentes").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $('#modal-Contribuyentes').modal({backdrop: 'static', keyboard: false})
                        $("#modal-Contribuyentes").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información delos Contribuyentes',
                        });
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                }
            });
        }
    }

    /**
     * postUsuario: Método para crear Contribuyentes
     */
    postContribuyentes() {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        // Tomamos los valores del formulario
        var ind_NumeroIdentificacion = $("#ind_NumeroIdentificacion").val();
        var ind_DV = $("#ind_DV").val();
        var ind_IdTipoDocumento = $("#ind_IdTipoDocumento").val();
        var ind_PrimerNombre = $("#ind_PrimerNombre").val();
        var ind_SegundoNombre = $("#ind_SegundoNombre").val();
        var ind_PrimerApellido = $("#ind_PrimerApellido").val();
        var ind_SegundoApellido = $("#ind_SegundoApellido").val();
        var ind_Direccion = $("#ind_Direccion").val();
        var ind_IdCiudad = $("#ind_IdCiudad").val();
        var ind_Persona = $("#ind_Persona").val();
        var ind_IdRegimen = $("#ind_IdRegimen").val();
        var ind_Telefono = $("#ind_Telefono").val();
        var ind_Email = $("#ind_Email").val();

        // Luego podrías construir un objeto formData o un data: {} en tu ajax con estas variables
        var formData = {
        funcion: 1,
        ind_NumeroIdentificacion: ind_NumeroIdentificacion,
        ind_DV: ind_DV,
        ind_IdTipoDocumento: ind_IdTipoDocumento,
        ind_PrimerNombre: ind_PrimerNombre,
        ind_SegundoNombre: ind_SegundoNombre,
        ind_PrimerApellido: ind_PrimerApellido,
        ind_SegundoApellido: ind_SegundoApellido,
        ind_Direccion: ind_Direccion,
        ind_IdCiudad: ind_IdCiudad,
        ind_Persona: ind_Persona,
        ind_IdRegimen: ind_IdRegimen,
        ind_Telefono: ind_Telefono,
        ind_Email: ind_Email
        };

        $.ajax({
            url: '../business/controller/class.contribuyentes.php',
            data: formData,
            dataType: "json",
            type: "POST",
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearContribuyentes").trigger("reset");
                    $("#modal-Contribuyentes").modal('hide');
                    contribuyentes.getContribuyentes();
                    swal({
                        type: 'success',
                        title: 'Contribuyentes creada',
                        text: 'Contribuyentes creada exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la Contribuyentes',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * cambiarEstado: Método para cambiar el estado de los Contribuyentes
     * @param type $id_usuario:  llave primaria de la tabla Contribuyentes
     * @param type $estado: estado actual del Contribuyentes
     */
    async cambiarEstado(id_contribuyentes, estado) {

        var permiso = await _permisos.getPermisos(idRol, 313);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar el contribuyente?";
                var subtitle = "Una vez inactivado el contribuyente no podra usarse";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el contribuyente?";
                var subtitle = "Una vez activado, el contribuyente podrá usarse";
                var button = "Sí, activar";
                var est = 1;
            }
            swal({
                title: title,
                text: subtitle,
                type: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: button,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.value) {
                    $('#loading').show();
                    $('#wrapper').addClass('body-load');
                    $.ajax({
                        url: '../business/controller/class.contribuyentes.php',
                        data: { funcion: 4, ind_Id: id_contribuyentes, ind_Estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {

                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');
                            if (arr.ok == 1) {
                                contribuyentes.getContribuyentes();
                                swal({
                                    type: 'success',
                                    title: 'Contribuyente actualizado',
                                    text: 'Contribuyente actualizado exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el Contribuyente',
                                });
                            }
                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) {
                            console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                        }
                    });
                }
            })
        }
    }

    /**
     * editUsuario: Método para actualizar un Contribuyentes
     * @param type $id: llave primaria de la tabla Contribuyentes
     */
    editContribuyentes(id) {

        $('#loading').show();
        $('#wrapper').addClass('body-load');

        // Tomamos los valores del formulario
        var ind_NumeroIdentificacion = $("#ind_NumeroIdentificacion").val();
        var ind_DV = $("#ind_DV").val();
        var ind_IdTipoDocumento = $("#ind_IdTipoDocumento").val();
        var ind_PrimerNombre = $("#ind_PrimerNombre").val();
        var ind_SegundoNombre = $("#ind_SegundoNombre").val();
        var ind_PrimerApellido = $("#ind_PrimerApellido").val();
        var ind_SegundoApellido = $("#ind_SegundoApellido").val();
        var ind_Direccion = $("#ind_Direccion").val();
        var ind_IdCiudad = $("#ind_IdCiudad").val();
        var ind_Persona = $("#ind_Persona").val();
        var ind_IdRegimen = $("#ind_IdRegimen").val();
        var ind_Telefono = $("#ind_Telefono").val();
        var ind_Email = $("#ind_Email").val();

        // Luego podrías construir un objeto formData o un data: {} en tu ajax con estas variables
        var formData = {
        funcion: 2,
        ind_Id: id,
        ind_NumeroIdentificacion: ind_NumeroIdentificacion,
        ind_DV: ind_DV,
        ind_IdTipoDocumento: ind_IdTipoDocumento,
        ind_PrimerNombre: ind_PrimerNombre,
        ind_SegundoNombre: ind_SegundoNombre,
        ind_PrimerApellido: ind_PrimerApellido,
        ind_SegundoApellido: ind_SegundoApellido,
        ind_Direccion: ind_Direccion,
        ind_IdCiudad: ind_IdCiudad,
        ind_Persona: ind_Persona,
        ind_IdRegimen: ind_IdRegimen,
        ind_Telefono: ind_Telefono,
        ind_Email: ind_Email
        };

        $.ajax({
            url: '../business/controller/class.contribuyentes.php',
            data: formData,
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {
                    $("#formCrearContribuyentes").trigger("reset");
                    $("#modal-Contribuyentes").modal('hide');
                    contribuyentes.getContribuyentes();
                    swal({
                        type: 'success',
                        title: 'Contribuyente actualizado',
                        text: 'Contribuyente actualizado exitosamente',
                    });
                }else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el contribuyentes',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * getResponables: Método para consultar los Responsables
     */
    getResponsables() {

        $.ajax({
            url: '../business/controller/class.usuarios.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#dep_IdResponsable").empty();
                $("#dep_IdResponsable").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#dep_IdResponsable").append('<option value="' + v['usu_Id'] + '">' + v['usu_Nombre'] + '</option>');
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    
    }

    /**
     * UsuarioActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    UsuarioActivo() {
        $("#accordion-menu li").removeClass("active show");
        $("#accordion-menu .submenu").css("display", "none");

        $("#MICAAlcaldia").addClass("active show");
        $("#SubICAAlcaldia").css("display", "block");

        $("#MICA_DatosBasicos").addClass("active show");
        $("#SubICA_DatosBasicos").css("display", "block");

        $("#ICA_Contribuyentes").addClass("active");
    }

    aplicarModoPersona(tipoPersona) {

        let selectDoc = $("#ind_IdTipoDocumento");

        if (tipoPersona == "2") { // Jurídica

            // Forzar NIT pero NO bloquear
            selectDoc.val("5");

            // Mostrar DV
            $("#ind_DV").closest(".col-md-2").show();
            $("#ind_DV").prop("required", true);

            // Cambiar label
            $("#ind_PrimerNombre").closest(".form-group")
                .find("label")
                .text("* Razón Social")
                .find("title").text("Razón Social");
            $
            $("#ind_SegundoNombre").closest(".col-md-6").hide();

            // Ocultar apellidos
            $("#ind_PrimerApellido").closest(".col-md-6").hide();
            $("#ind_PrimerApellido").prop("required", false).val("");
            $("#ind_SegundoApellido").closest(".col-md-6").hide();

        } else { // Natural

            // Ocultar DV
            $("#ind_DV").closest(".col-md-2").hide();
            $("#ind_DV").prop("required", false).val("0");

            // Restaurar label
            $("#ind_PrimerNombre").closest(".form-group")
                .find("label")
                .text("* Primer Nombre")
                .find("title").text("Primer Nombre");
            $("#ind_SegundoNombre").closest(".col-md-6").show();
            // Mostrar apellidos
            $("#ind_PrimerApellido").closest(".col-md-6").show();
            $("#ind_PrimerApellido").prop("required", true);
            $("#ind_SegundoApellido").closest(".col-md-6").show();
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


    cargarCiudades() {

        $.ajax({
            url: '../business/controller/class.ciudades.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 1 },
            success: function (arr) {

                if (arr.ok == 1) {

                    $('#ind_IdCiudad').empty();
                    $('#ind_IdCiudad').append('<option value=""></option>');

                    $.each(arr.datos, function (i, ciudad) {
                        $('#ind_IdCiudad').append(
                            `<option value="${ciudad.ciu_Id}">
                                ${ciudad.ciu_Nombre} - ${ciudad.ciu_Departamento}
                            </option>`
                        );
                    });
                }
            }
        });
    }

}

const contribuyentes = new Contribuyentes();

contribuyentes.getContribuyentes();
contribuyentes.UsuarioActivo();
//contribuyentes.getResponsables();


$(document).on('change', '#ind_Persona', function () {
    contribuyentes.aplicarModoPersona($(this).val());
});

$(document).on('change', '#ind_IdTipoDocumento', function () {

    if ($(this).val() == "5") {

        $("#ind_Persona").val("2");
        contribuyentes.aplicarModoPersona("2");

    } else {

        
        $("#ind_Persona").val("1");
        contribuyentes.aplicarModoPersona("1");
    
    }
});


$(document).on('keyup', '#ind_NumeroIdentificacion', function () {

    if ($("#ind_IdTipoDocumento").val() == "5") {

        let nit = $(this).val();

        if (nit.length > 0) {
            let dv = contribuyentes.calcularDigitoVerificacion(nit);
            $("#ind_DV").val(dv);
        } else {
            $("#ind_DV").val("0");
        }
    }
});


$('#modal-Contribuyentes').on('shown.bs.modal', function () {

    $('#ind_IdCiudad').select2({
        dropdownParent: $('#modal-Contribuyentes'),
        placeholder: "Buscar ciudad...",
        width: '100%'
    });

});

$(document).ready(function () {
    $('#ind_IdCiudad').select2({
        placeholder: "Buscar ciudad...",
        allowClear: true,
        width: '100%'
    });

    contribuyentes.cargarCiudades();
});