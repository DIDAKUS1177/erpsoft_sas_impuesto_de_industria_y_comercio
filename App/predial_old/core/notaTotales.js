/* var Token = localStorage.getItem('sessionToken');
var nitAdquiriente = localStorage.getItem('sessionNIT');
var _postlogin= JSON.parse(sessionStorage.getItem('postlogin'));
console.log(_postlogin);*/
var enable = true;

var idRol = sessionStorage.getItem('id_Rol');
var detallesNota = [];
class Nota {

    constructor() {}

    /**
     * crearNota: Método para abrir modal de creación
     */
    async crearNota() {
        var permiso = await _permisos.getPermisos(idRol, 519);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $("#clave").removeAttr('style');
            $("#usu_Clave").attr('required', true);
            $("#formCrearNota").trigger("reset");
            $("#formCrearNota").attr('action', 'javascript:nota.postNota()');

            await nota.getProducto();
            await nota.getBodega();
            $("#modal_footer").empty();
            $("#modal_footer").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal"><span class="ti-close"></span>' +
                ' Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                ' Crear' +
                '</button>'
            );
            $("#modal-Nota").modal('show');
        }
    }

    /**
     * pintarTable: Método para pintar la tabla inicial
     * de los detalles de la nota
     */
    pintarTable() {
        $("#detalleNotas").DataTable().destroy();
        $("#bodyDetallesNotas").empty();
        var cont = 0;
        if (detallesNota.length > 0) {
            console.log('detallesNota2 ', detallesNota);
            for (let d of detallesNota) {
                $("#bodyDetallesNotas").prepend(
                    '<tr>' +
                    '<td>' + d.nomProducto + '</td>' +
                    '<td align="right">' + d.detkar_Cantidad + '</td>' +
                    '<td align="right">' + d.detkar_CostoUnitario + '</td>' +
                    '<td>' + d.nomBodega + '</td>' +
                    '<td align="center">' +
                    '<button type="button" class="btn btn-social-icon btn-danger " data-toggle="tooltip" title="Eliminar detalle"  onclick="nota.eliminarDetalle(' + cont + ')">' +
                    '<i class="dw dw-ban"></i>' +
                    '</button>' +
                    '</td>' +
                    '</tr>'
                );
                cont++;
            }
        }

        $('#detalleNotas').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [{
                targets: "datatable-nosort",
                orderable: false,
            }],
            aaSorting: [
                [1, "desc"]
            ],
            //Se ordena por la fila 4 
            "order": [[ 4, "desc" ]],
            "lengthMenu": [
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'No existen registros',
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
     * draw_table_documents: Método para pintar la tabla 
     * de notas de entrada y salida 
     * @param type $arrFilter: Listado de objetos de tipo nota
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#notasRegistradas").DataTable().destroy();
        $("#bodyNotasRegistradas").empty();
        for (let not of arrFilter) {
            var icono = "dw dw-ban";
            var clase = "btn-danger";

            if (not.Kar_Estado == 1) {
                var disabled = "";
                var titulo = "Anular Nota";
            } else {
                var disabled = "disabled";
                var titulo = "Nota Anulada";
            }

            if (not.kar_Tipo == 1) {
                var NomTipo = "NE";
            } else {
                var NomTipo = "NS";
            }

            $('#bodyNotasRegistradas').append(
                '<tr>' +
                '<td>' +
                not.kar_Fecha +
                '</td>' +
                '<td>' +
                NomTipo +
                '</td>' +
                '<td>' +
                not.kar_Observaciones +
                '</td>' +

                '<td align="center">' +
                /*  '<button type="button" class="btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar nota" style="margin-right:5px" onclick="javascript:nota.getNotaById('+not.kar_Id+')">'+
                     '<i class="mdi mdi-border-color"></i>'+
                 '</button>'+ */

                /*  '<button type="button" class="btn btn-social-icon ' + clase + ' " data-toggle="tooltip" title="' + titulo + '"  onclick="javascript:nota.cambiarEstado(' + not.kar_Id + ',' + not.Kar_Estado + ')" ' + disabled + ' style="margin-right: 5px">' +
                 '<i class="' + icono + '"></i>' +
                 '</button>' + */
                '<button type="button" class="btn btn-social-icon btn-success " data-toggle="tooltip" title="Ver detalles"  onclick="javascript:nota.verDetalle(' + not.kar_Id + ')" >' +
                '<span class="ti-eye"></span>' +
                '</button>' +
                '</td>' +

                '</tr>'
            );

        }
        nota.init_table();
    }

    /**
     * init_table: Método para asignar la
     * propiedad DataTable() a la tabla de productos
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
            aaSorting: [
                [0, "desc"]
            ],
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'No existen registros',
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
     * getNotas: Método para consultar las notas 
     */
    getNotas() {

        $.ajax({
            url: '../business/controller/class.nota.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('not ', arr);

                if (arr.ok == 1) {
                    nota.draw_table_documents(arr.datos);
                } else {
                    $("#notasRegistradas").DataTable().destroy();
                    nota.init_table();
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getProducto: Método para consultar los productos
     */
    getProducto() {

        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#detkar_IdProducto").empty();
                $("#detkar_IdProducto").append('<option class"ulCombo" value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#detkar_IdProducto").append('<option  class"ulCombo" value="' + v['pro_Id'] + '">' + v['pro_Nombre'] + '</option>');
                    });
                }

                $("#detkar_IdProducto").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getBodega: Método para consultar las bodegas
     */
    getBodega() {

        $.ajax({
            url: '../business/controller/class.bodega.php',
            data: { funcion: 3, estado: 1 , tipo: 1},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr ', arr);
                $("#detkar_IdBodega").empty();
                $("#detkar_IdBodega").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#detkar_IdBodega").append('<option value="' + v['bod_Id'] + '">' + v['bod_Nombre'] + '</option>');
                    });
                }
                $("#detkar_IdBodega").addClass('custom-select2');
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * verDetalle: Método para consultar el 
     * detalle de las notas
     * @param type $idNota: Listado de objetos de tipo nota
     */
    verDetalle(idNota) {
        $.ajax({
            url: '../business/controller/class.nota.php',
            data: { funcion: 5, idNota: idNota },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('det ', arr)
                $("#ltsDetallesNota").DataTable().destroy();
                $("#bodyDetallesNota").empty();

                if (arr.ok == 1) {

                    for (let d of arr.datos) {
                        $("#bodyDetallesNota").append(
                            '<tr>' +
                            '<td>' + d.pro_Nombre + '</td>' +
                            '<td>' + d.bod_Nombre + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_CantidadEntrada + '</td>' +
                            //'<td class="numero" align="rigth">' + d.detkar_ValorEntrada + '</td>' +
                            '<td class="numero" align="rigth">' + d.detkar_CantidadSalida + '</td>' +
                            //'<td class="numero" align="rigth">' + d.detkar_ValorSalida + '</td>' +
                            //'<td class="numero" align="rigth">' + d.detkar_CantidadSaldo + '</td>' +
                            //'<td class="numero" align="rigth">' + d.detkar_ValorSaldo + '</td>' +
                            '</tr>'
                        );
                    }

                    $('.numero').number(true, 2);
                    nota.init_table_detalle();
                    $("#modal-Detalles").modal('show');
                } else {
                    swal({
                        type: 'error',
                        title: 'Ocurrio un error al consultar la información',
                        text: 'Por favor intente nuevamente, si el problema persiste consulte con el adimistrador del sistema',
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
     * init_table_detalle: Método para asignar la
     * propiedad DataTable() a la tabla de productos
     */
    init_table_detalle() {
        $('#ltsDetallesNota').DataTable({
            scrollCollapse: true,
            autoWidth: false,
            responsive: true,
            columnDefs: [{
                targets: "datatable-nosort",
                orderable: false,
            }],
            aaSorting: [
                [1, "desc"]
            ],
            "lengthMenu": [
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'No existen registros',
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

    agregarDetalle() {
        var pro = $("#detkar_IdProducto").val();
        var cant = $("#detkar_Cantidad").val();
        var cost = $("#detkar_Costo").val();
        var bod = $("#detkar_IdBodega").val();
        console.log('pro ', pro, 'cant ', cant, 'cost ', cost, 'bod ', bod);
        if (pro != "" && cant != "" && cost != "" && bod != "") {
            var namePro = $('#detkar_IdProducto option:selected').text();
            var nameBod = $('#detkar_IdBodega option:selected').text();
            detallesNota.push({ "detkar_IdProducto": pro, "detkar_Cantidad": cant, "detkar_CostoUnitario": cost, "detkar_IdBodega": bod, "nomProducto": namePro, "nomBodega": nameBod });
        }
        nota.pintarTable();

        $('#detkar_IdProducto').val($('#detkar_IdProducto > option:first').val());


        $('#detkar_IdBodega').get(0).selectedIndex = 1;
        $('#detkar_Cantidad').val('');
        $('#detkar_Costo').val('');

        console.log('detallesNota ', detallesNota);
    }

    eliminarDetalle(posicion) {
        detallesNota.splice(posicion, 1);
        nota.pintarTable();
    }

    /**
     * postNota: Método para crear notas
     */
    postNota() {

        /* $('#loading').show();
        $('#wrapper').addClass('body-load'); */

        if (detallesNota.length < 1) {
            swal({
                type: 'warning',
                title: 'No existen productos asignados en el detalle de la  nota',
                text: 'Debe ingresar al menos un producto en el detalle de la nota',
            });
        } else {
            var tipo = $("#kar_Tipo").val()
            var Observaciones = $("#Kar_Observaciones").val();
            var det = JSON.stringify(detallesNota);
            $.ajax({
                url: '../business/controller/class.nota.php',
                data: { funcion: 1, kar_Tipo: tipo, Observaciones: Observaciones, detallesNota: det },
                type: 'POST',
                dataType: 'json',
                success: function(arr) {

                    if (arr.ok == 1) {
                        $("#formCrearNota").trigger("reset");
                        $("#modal-Nota").modal('hide');
                        $("#detalleNotas").DataTable().destroy();
                        $("#bodyDetallesNotas").empty();
                        detallesNota = [];
                        nota.getNotas();
                        swal({
                            type: 'success',
                            title: 'Nota creada',
                            text: 'Nota creada correctamente',
                        });
                    } else if (arr.ok == 2) {
                        $("#formCrearNota").trigger("reset");
                        $("#modal-Nota").modal('hide');
                        $("#detalleNotas").DataTable().destroy();
                        $("#bodyDetallesNotas").empty();
                        detallesNota = [];
                        nota.getNotas();
                        swal({
                            type: 'warning',
                            title: 'Sin existencias',
                            text: "Uno o más productos relacionados en la nota no tienen existencias",
                        });
                    } else if (arr.ok == 3) {
                        $("#formCrearNota").trigger("reset");
                        $("#modal-Nota").modal('hide');
                        $("#detalleNotas").DataTable().destroy();
                        $("#bodyDetallesNotas").empty();
                        detallesNota = [];
                        nota.getNotas();
                        swal({
                            type: 'warning',
                            title: 'Sin existencias',
                            text: arr.mensaje,
                        });
                    } else {
                        swal({
                            type: 'error',
                            title: 'Ocurrió un error al intentar crear la nota',
                            text: arr.mensaje,
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

    async cambiarEstado(id_nota) {
        var permiso = await _permisos.getPermisos(idRol, 521);
        var estado = 1;
        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            if (estado == 1) {
                var title = "¿Está seguro de anular la nota?";
                var subtitle = "Una vez anulada, no podrá ser recuperada. Esta acción es irreversible";
                var button = "Sí, inactivar";
                var est = 0;
            } else {
                var title = "¿Está seguro de activar el producto?";
                var subtitle = "Una vez activado, podrá ser utilizado";
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
                        url: '../business/controller/class.nota.php',
                        data: { funcion: 4, id: id_nota, estado: est },
                        dataType: "json",
                        type: "POST",
                        success: function(arr) {
                            console.log('roles', arr);

                            if (arr.ok == 1) {
                                nota.getNotas();
                                swal({
                                    type: 'success',
                                    title: 'Nota anulada',
                                    text: 'Nota anulada exitosamente',
                                });
                            } else {
                                swal({
                                    type: 'error',
                                    title: 'Error',
                                    text: 'No se pudo anular la nota',
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

    editProducto(id) {
        /*   $('#loading').show();
          $('#wrapper').addClass('body-load'); */

        var nombre = $("#pro_Nombre").val();
        var cod = $("#pro_Codigo").val();
        var codBarras = $("#pro_CodBarras").val();
        var tipo = $("#pro_Tipo").val();
        var unidad = $("#pro_UnidadMed").val();

        $.ajax({
            url: '../business/controller/class.producto.php',
            data: { funcion: 2, id: id, nombre: nombre, codigo: cod, tipo: tipo, unidad: unidad },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('roles', arr);

                if (arr.ok == 1) {
                    $("#formCrearProducto").trigger("reset");
                    $("#modal-Producto").modal('hide');
                    prod.getProductos();
                    swal({
                        type: 'success',
                        title: 'Producto actualizado',
                        text: 'Producto actualizado exitosamente',
                    });
                } else if (arr.ok == 2) {
                    swal({
                        type: 'warning',
                        title: 'Código duplicado',
                        text: arr.mensaje,
                    });
                } else if (arr.ok == 3) {
                    swal({
                        type: 'warning',
                        title: 'Identificación duplicada',
                        text: arr.mensaje,
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo actualizar el producto',
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


    NotaActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DInventario").addClass('expand');
        $("#DInventario").addClass('active');
        $("#SInventario").addClass('show');
        $("#SubMenuNotaTotales").addClass('active');
    }

}

const nota = new Nota();


nota.NotaActivo();
nota.getNotas();


$(document).ready(function() {
    $("#detkar_Cantidad").number(true, 2);
    $("#detkar_Costo").number(true, 2);
});