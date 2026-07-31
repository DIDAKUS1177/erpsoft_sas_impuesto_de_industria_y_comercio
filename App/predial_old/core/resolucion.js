/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
class Resolucion {

    constructor() {}

    /**
     * crearResolucion: Método para abrir modal de creación
     */
    async crearResolucion() {
        var permiso = await _permisos.getPermisos(idRol, 2183);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            resolucion.activarCampos(1);
            $("#formCrearResolucion").trigger("reset");
            $("#btnCrearResolucion").empty();
            $("#btnCrearResolucion").append(
                '<span class="ti-plus"></span>' +
                ' Crear'
            );
            $("#formCrearResolucion").attr('action', 'javascript:resolucion.postResolucion()');
            $("#modal-Resolucion").modal('show');
        }
    }

    /**
     * draw_table_documents: Método para pintar la tabla 
     * de resoluciones 
     * @param type $arrFilter: Listado de obejtos de tipo resoluciones
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#resolucionesRegistrados").DataTable().destroy();
        $("#bodyResolucionesRegistrados").empty();

        for (let reso of arrFilter) {
            if (reso.reso_Estado == 1) {
                var icono = "dw dw-checked";
                var clase = "btn-success";
                var titulo = "Activo";
            } else {
                var icono = "dw dw-ban";
                var clase = "btn-danger";
                var titulo = "Inactivo";
            }

            if (reso.reso_IdTipoDocumento == 1) {
                var tipoFac = "Remisión";
            } else {
                var tipoFac = "POS";
            }

            var preNum = reso.reso_Prefijo+' / Desde: '+reso.reso_NumeroInicial+'- Hasta: '+reso.reso_NumeroFinal;

            if (reso.reso_Id) {

                $('#bodyResolucionesRegistrados').append(
                    '<tr>' +
                    '<td>' +
                        tipoFac +
                    '</td>' +      
                    '<td>' +
                        preNum +
                    '</td>' +   
                    '<td>' +
                        reso.reso_FechaAutorizacion +
                    '</td>' +   
                    '<td>' +
                        reso.reso_FechaVencimiento +
                    '</td>' +   
                    
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '">' +
                    '<i>'+titulo+'</i>' +
                    '</button>' +
                    '</td>' +

                    '<td align="center">' +
                    '<button type="button" class="mb-1 btn  btn-warning " data-toggle="tooltip" title="Editar Resolucion" style="margin-right:5px" onclick="javascript:resolucion.getResolucionById(' + reso.reso_Id + ')">' +
                    '<i class="dw dw-edit2"></i>' +
                    '</td>' +
                    
                    '</tr>'
                );
            }
        }
        resolucion.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de roles
     */
    init_table() {
        $('.data-table').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [{
                targets: "datatable-nosort",
                orderable: false,
            }],
            "lengthMenu": [
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Resoluciones registradas',
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
     * getResoluciones: Método para consultar 
     * resoluciones
     */
    getResoluciones() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');*/

        $.ajax({
            url: '../business/controller/class.resolucion.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                /*$('#loading').hide();
                $('#wrapper').removeClass('body-load');*/

                if (arr.ok == 1) {
                    $("#bodyResolucionesRegistrados").empty();
                    resolucion.draw_table_documents(arr.datos);
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo consultar los roles',
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getResolucionById: Método para consultar la
     * información de un Resolucion
     * @param type $id: llave primaria de la tabla resoluciones
     */
    async getResolucionById(id) {
        $('#loading').show();
        $('#wrapper').addClass('body-load');  

        var permiso = await _permisos.getPermisos(idRol, 2184);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.resolucion.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    

                    if (arr.ok == 1) {
                        for (let datos of arr.datos) {
                            $("#reso_IdTipoDocumento").val(datos.reso_IdTipoDocumento);
                            $("#reso_Prefijo").val(datos.reso_Prefijo);
                            $("#reso_NumeroInicial").val(datos.reso_NumeroInicial);
                            $("#reso_NumeroFinal").val(datos.reso_NumeroFinal);
                            $("#reso_Numero").val(datos.reso_Numero);
                            $("#reso_FechaAutorizacion").val(datos.reso_FechaAutorizacion);
                            $("#reso_FechaVencimiento").val(datos.reso_FechaVencimiento);
                        }

                        resolucion.activarCamposEditar($("#reso_IdTipoDocumento").val());
                        
                        $("#formCrearResolucion").attr('action', 'javascript:resolucion.editResolucion(' + id + ')');
                        $("#btnCrearResolucion").empty();
                        $("#btnCrearResolucion").append(
                            '<span class="ti-reload"></span>' +
                            ' Actualizar'
                        );
                        $("#modal-Resolucion").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información del rol',
                        });
                    }
                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                    //location.href = "../../login.html";
                }
            });
        }
    }

    /**
     * postResolucion: Método para crear 
     * resoluciones
     */
    postResolucion() {
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var IdTipoDocumento = $("#reso_IdTipoDocumento").val();
        var Prefijo = $("#reso_Prefijo").val();
        var NumeroInicial = $("#reso_NumeroInicial").val();
        var NumeroFinal = $("#reso_NumeroFinal").val();
        var Numero = $("#reso_Numero").val();
        var FechaAutorizacion = $("#reso_FechaAutorizacion").val();
        var FechaVencimiento = $("#reso_FechaVencimiento").val();

        $.ajax({
            url: '../business/controller/class.resolucion.php',
            data: { funcion: 1, IdTipoDocumento: IdTipoDocumento, Prefijo: Prefijo,
                    NumeroInicial: NumeroInicial, NumeroFinal: NumeroFinal, Numero: Numero, 
                    FechaAutorizacion: FechaAutorizacion, FechaVencimiento: FechaVencimiento },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearResolucion").trigger("reset");
                    $("#modal-Resolucion").modal('hide');
                    resolucion.getResoluciones();
                    swal({
                        type: 'success',
                        title: 'Resolucion creada',
                        text: 'Resolucion creada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Resolucion Duplicada',
                        text: arr.mensaje,
                    });
                }else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Prefijo Duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la resolucion',
                    });
                }
                /*  $("#estado").append('<option value="">Selecione una opción</option>');
                 arrayDocs = arr;
                 $.each(arr, function (k, v){
                     $("#estado").append('<option value="'+v['ESTDOC_Id']+'">'+v['ESTDOC_Nombre']+'</option>');
                 });  */

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * cambiarEstado: Método para cambiar el estado
     * de los resoluciones
     * @param type $id_Resolucion:  llave primaria de la tabla resoluciones
     * @param type $estado: estado actual del Resolucion
     */
    async cambiarEstado(id_resolucion, estado) {
        var permiso = await _permisos.getPermisos(idRol, 2185);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de inactivar la resolucion?";
                var subtitle = "Una vez inactivada la Resolucion no podrá utilizala";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar la resolucion?";
                var subtitle = "Una vez activada la resolucion podrá utilizala";
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
                        url: '../business/controller/class.resolucion.php',
                        data: { funcion: 4, id: id_resolucion, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);
                            $('#loading').hide();
                            $('#wrapper').removeClass('body-load');

                            if (arr.ok == 1) {
                                resolucion.getResoluciones();
                                swal({
                                    type: 'success',
                                    title: 'Resolucion actualizada',
                                    text: 'Resolucion actualizada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo actualizar el resolucion',
                                });
                            }
                            /*  $("#estado").append('<option value="">Selecione una opción</option>');
                                arrayDocs = arr;
                                $.each(arr, function (k, v){
                                    $("#estado").append('<option value="'+v['ESTDOC_Id']+'">'+v['ESTDOC_Nombre']+'</option>');
                                });  */

                        },
                        error: function(XMLHttpRequest, textStatus, errorThrown) {
                            console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                            //location.href = "../../login.html";
                        }
                    });
                }
            })
        }
    }

    /**
     * getTipoDocumento: Método para consultar 
     * Tipos de Documento
     */
    getTipoDocumento() {
        /* $('#loading').show();
        $('#wrapper').addClass('body-load');  */
        $("#reso_IdTipoDocumento").empty();
        $("#reso_IdTipoDocumento").append('<option value="">Seleccione un Tipo Documento</option>');

        $.ajax({
            url: '../business/controller/class.resolucion.php',
            data: { funcion: 5 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                /*$('#loading').hide();
                $('#wrapper').removeClass('body-load');*/

                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#reso_IdTipoDocumento").append('<option value="' + v['tip_Id'] + '">' + v['tip_Descripcion'] + '</option>');
                    });

                } else {

                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * editResolucion: Método para actualizar un 
     * Resolucion
     * @param type $id: llave primaria de la tabla resoluciones
     */
    editResolucion(id) {
          $('#loading').show();
          $('#wrapper').addClass('body-load');

        var IdTipoDocumento = $("#reso_IdTipoDocumento").val();
        var Prefijo = $("#reso_Prefijo").val();
        var NumeroInicial = $("#reso_NumeroInicial").val();
        var NumeroFinal = $("#reso_NumeroFinal").val();
        var Numero = $("#reso_Numero").val();
        var FechaAutorizacion = $("#reso_FechaAutorizacion").val();
        var FechaVencimiento = $("#reso_FechaVencimiento").val();

        $.ajax({
            url: '../business/controller/class.resolucion.php',
            data: { funcion: 2, id: id, IdTipoDocumento: IdTipoDocumento, Prefijo: Prefijo,
                NumeroInicial: NumeroInicial, NumeroFinal: NumeroFinal, Numero: Numero, 
                FechaAutorizacion: FechaAutorizacion, FechaVencimiento: FechaVencimiento },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);
                
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');

                if (arr.ok == 1) {
                    $("#formCrearResolucion").trigger("reset");
                    $("#modal-Resolucion").modal('hide');
                    resolucion.getResoluciones();
                    swal({
                        type: 'success',
                        title: 'Resolucion actualizada',
                        text: 'Resolucion actualizada exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Resolucion duplicada',
                        text: arr.mensaje,
                    });
                } else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Prefijo Duplicado',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar la Resolucion',
                    });
                }
                /*  $("#estado").append('<option value="">Selecione una opción</option>');
                 arrayDocs = arr;
                 $.each(arr, function (k, v){
                     $("#estado").append('<option value="'+v['ESTDOC_Id']+'">'+v['ESTDOC_Nombre']+'</option>');
                 });  */

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }


    /**
    * activarCampos: Metodo para activar campo de ingresar supervisor
    */
    activarCampos(obj){   
        console.log(obj);
        if(obj == 1){
            document.getElementById('reso_FechaAutorizacion').value = "2000-01-01";
            document.getElementById('reso_FechaVencimiento').value = "3000-01-01";
            document.getElementById('reso_Numero').value = "123456789";
            document.getElementById('reso_NumeroInicial').value = "1";
            document.getElementById('reso_NumeroFinal').value = "10000000";
    
            document.getElementById('reso_FechaAutorizacion').style.display = "none";   
            document.getElementById('reso_FechaVencimiento').style.display = "none";   
            document.getElementById('reso_Numero').style.display = "none";   
            document.getElementById('reso_NumeroInicial').style.display = "none";   
            document.getElementById('reso_NumeroFinal').style.display = "none";   

            document.getElementById("numAur").style.display = "none";
            document.getElementById("fechAut").style.display = "none";
            document.getElementById("fechVenci").style.display = "none";
            document.getElementById("desde").style.display = "none";
            document.getElementById("hasta").style.display = "none";
            
            
        }else{
            document.getElementById('reso_FechaAutorizacion').value = "";
            document.getElementById('reso_FechaVencimiento').value= "";
            document.getElementById('reso_Numero').value = "";
            document.getElementById('reso_NumeroInicial').value = "";
            document.getElementById('reso_NumeroFinal').value = "";

            document.getElementById('reso_FechaAutorizacion').style.display = "";
            document.getElementById('reso_FechaVencimiento').style.display = "";   
            document.getElementById('reso_Numero').style.display = "";   
            document.getElementById('reso_NumeroInicial').style.display = "";   
            document.getElementById('reso_NumeroFinal').style.display = "";   

            document.getElementById("numAur").style.display = "";
            document.getElementById("fechAut").style.display = "";
            document.getElementById("fechVenci").style.display = "";
            document.getElementById("desde").style.display = "";
            document.getElementById("hasta").style.display = "";
        } 
    }

    /**
    * activarCamposEditar: Metodo para activar campos al editar.
    */
    activarCamposEditar(obj){      
        
        if(obj == 1){
    
            document.getElementById('reso_FechaAutorizacion').style.display = "none";   
            document.getElementById('reso_FechaVencimiento').style.display = "none";   
            document.getElementById('reso_Numero').style.display = "none";   
            document.getElementById('reso_NumeroInicial').style.display = "none";   
            document.getElementById('reso_NumeroFinal').style.display = "none";   

            document.getElementById("numAur").style.display = "none";
            document.getElementById("fechAut").style.display = "none";
            document.getElementById("fechVenci").style.display = "none";
            document.getElementById("desde").style.display = "none";
            document.getElementById("hasta").style.display = "none";
            
        }else{

            document.getElementById('reso_FechaAutorizacion').style.display = "";
            document.getElementById('reso_FechaVencimiento').style.display = "";   
            document.getElementById('reso_Numero').style.display = "";   
            document.getElementById('reso_NumeroInicial').style.display = "";   
            document.getElementById('reso_NumeroFinal').style.display = "";   

            document.getElementById("numAur").style.display = "";
            document.getElementById("fechAut").style.display = "";
            document.getElementById("fechVenci").style.display = "";
            document.getElementById("desde").style.display = "";
            document.getElementById("hasta").style.display = "";
        } 
    }


    /**
     * ResolucionActivo: Método para activar el menú y facilitar
     * la navegación al Resolucion permitendole saber en
     * que lugar esta
     */
    ResolucionActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DInventario").addClass('expand');
        $("#DInventario").addClass('active');
        $("#SInventario").addClass('show');
        $("#SubMenuResolucion").addClass('active');
    }

}

const resolucion = new Resolucion();

resolucion.getResoluciones();
resolucion.getTipoDocumento();
resolucion.ResolucionActivo();