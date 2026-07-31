// Declaración de variables
'use strict';

var idRol = sessionStorage.getItem('id_Rol');
console.log('idRol ', sessionStorage)
class Proyectos {

    constructor() {}

    /**
     * crearClientes: Método para abrir modal de creación
     */
    async crearEgreso() {
        var permiso = await _permisos.getPermisos(idRol, 2493);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {

            $("#selec_IdEvento").val('');
            $("#selec_IdActividad").val('');
            $("#txtDescripcionEgreso").val('');
            $("#txtValorPagado").val('');
            $("#txtFecha").val('');
            $("#cuentaContable").val('');
                        
            document.getElementById('txtValorPagado').style.display = ""; 
            document.getElementById('cuentasTeso').style.display = ""; 
            document.getElementById('cuValor').style.display = ""; 
            document.getElementById('chec').style.display = ""; 
            
            $("#formEgresos").attr('action', 'javascript:bod.postEgresos();');
            $("#modal_footerEgresos").empty();
            $("#modal_footerEgresos").append(
                '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                '<span class="ti-close"></span> Cancelar' +
                '</button>' +
                '<button type="submit" class="btn btn-success btn-pill">' +
                '<span class="ti-plus"></span> Crear' +
                '</button>'
            );

            $("#modal-Egresos").modal('show');
        }
    }


    /**
     * draw_table_documents: Método para pintar la tabla 
     * de clientess 
     * @param type $arrFilter: Listado de obejtos de tipo clientes
     */
    draw_table_documents(arrFilter) {
        console.log('arr', arrFilter);

        $("#tblEgresos").DataTable().destroy();
        $("#tbodyEgresos").empty();
        for (let bod of arrFilter) {
         
            if (bod.egre_Pagado == 1) {
                var pagado = "Pago";
            } else if (bod.egre_Pagado == 1){
                var pagado = "Por Pagar";
            }else{
                var pagado = "No asignado";
            }

            if (bod.egre_Descripcion == null) {
                var descripcionEgre = "Sin Datos";
            }else{
                var descripcionEgre = bod.egre_Descripcion;
            }

            $('#tbodyEgresos').append(
                '<tr>' +
                '<td>' +
                bod.egre_Fecha +
                '</td>' +
                '<td>' +
                bod.strNombreEvento +
                '</td>' +
                '<td>' +
                bod.strNombreActividad +
                '</td>' +
                '<td>' +
                descripcionEgre +
                '</td>' +
                '<td>' +
                '$ ' + Number(parseInt(bod.egre_Valor).toFixed(0)).toLocaleString('es-CO') +
                //bod.egre_Valor +
                '</td>' +
                '<td align="center">' +
                '<button type="button" class="mb-1 btn btn-social-icon btn-warning " data-toggle="tooltip" title="Editar Egreso" style="margin-right:5px" onclick="javascript:bod.getEgresosById(' + bod.egre_Id + ')">' +
                '<i class="dw dw-edit2"></i>' +
                '</button>' +
                '</td>' +        
                '</tr>'
            );
        }
        bod.init_table();
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
                [10, 25, 50, -1],
                [10, 25, 50, "All"]
            ],
            "language": {
                'decimal': '',
                'emptyTable': 'Egresos registrados',
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
     * getEgresos: Método para consultar las
     * Proyectos
     */
    getEgresos() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $.ajax({
            url: '../business/controller/class.egresosEventos.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                /*$('#loading').hide();
                $('#wrapper').removeClass('body-load');*/
                if (arr.ok == 1) {
                    bod.draw_table_documents(arr.datos);
                } else {
                    bod.init_table();   
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getClientesById: Método para consultar la 
     * información de una clientes
     * @param type $id: llave primaria de la tabla clientes
     */
    async getEgresosById(id) {
        var permiso = await _permisos.getPermisos(idRol, 2493);

        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
            $.ajax({
                url: '../business/controller/class.egresosEventos.php',
                data: { funcion: 3, id: id },
                dataType: "json",
                type: "POST",
                success: function(arr) {

                    $('#loading').hide();
                    $('#wrapper').removeClass('body-load');
                    if (arr.ok == 1) {
                        $("#selec_IdEvento").val('');
                        $("#selec_IdActividad").val('');
                        for (let datos of arr.datos) {

                            $("#selec_IdEventos").val(datos.egre_IdEvento);
                            $("#selec_IdActividad").val(datos.egre_IdActividad);
                            $("#txtDescripcionEgreso").val(datos.egre_Descripcion);
                            $("#txtValorPagado").val(datos.egre_Valor);
                            $("#txtFecha").val(datos.egre_Fecha);
                            if(datos.egre_IdCuentaContable == 0){
                                $("#cuentaContable").val('No se realizo movimiento Contable');
                            }else{
                                $("#cuentaContable").val('Ingreso a la Cuenta: '+datos.strNombreCuenta+' - $ '+Number(parseInt(datos.egre_Valor).toFixed(0)).toLocaleString('es-CO'));
                            }

                        }
                        document.getElementById('txtValorPagado').style.display = "none"; 
                        document.getElementById('select_Cuentas').style.display = "none";
                        
                        document.getElementById('cuentasTeso').style.display = "none"; 
                        document.getElementById('cuValor').style.display = "none"; 
                        document.getElementById('chec').style.display = "none"; 


                        $("#formEgresos").attr('action', 'javascript:bod.putEgresos(' + id + ');');
                        $("#modal_footerEgresos").empty();
                        $("#modal_footerEgresos").append(
                            '<button type="button" class="btn btn-danger btn-pill" data-dismiss="modal">' +
                            '<span class="ti-close"></span> Cancelar' +
                            '</button>' +
                            '<button type="submit" class="btn btn-success btn-pill">' +
                            '<span class="ti-reload"></span> Actualizar' +
                            '</button>'
                        );

                        $("#modal-Egresos").modal('show');
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo consultar la información de los Proyectos.',
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
     * putClientes: Método para actualizar la 
     * información de una clientes
     * @param type $id: llave primaria de la tabla clientes
     */
    putEgresos(id) {
        
        var selec_IdEventos = $("#selec_IdEventos").val();
        var selec_IdActividad = $("#selec_IdActividad").val();
        var txtDescripcionEgreso = $("#txtDescripcionEgreso").val();
        var txtFecha = $("#txtFecha").val();
        var txtValorPagado = $("#txtValorPagado").val().replace(/\./g, '');

           // Ajax para almacenar el EGRESO / GASTO
           $.ajax({
            url: '../business/controller/class.egresosEventos.php',
            data: { funcion: 2, id: id, txtDescripcionEgreso: txtDescripcionEgreso,
                selec_IdActividad: selec_IdActividad, txtFecha: txtFecha, txtValorPagado: txtValorPagado, 
                selec_IdEventos: selec_IdEventos},
            dataType: "json",
            type: "POST",
            success: function(arr) {
                if (arr.ok == 1) {   
                    $("#modal-Egresos").modal('hide');
                    swal({
                        type: 'success',
                        title: 'Egreso actualizado',
                        text: 'Egreso actualizado Exitosamente',
                    });
                    bod.getEgresos();
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear el proyecto',
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
     * postEgresos: Método para crear Egresos
     */
    postEgresos() {
        
         var selec_IdEventos = $("#selec_IdEventos").val();
         var selec_IdActividad = $("#selec_IdActividad").val();
         var txtDescripcionEgreso = $("#txtDescripcionEgreso").val();
         var txtFecha = $("#txtFecha").val();
         var txtValorPagado = $("#txtValorPagado").val().replace(/\./g, '');

         if(document.getElementById('chec').checked) {
            var check = 1;
            var select_Cuentas = $("#select_Cuentas").val();
          } else {
            var check = 0;
            var select_Cuentas = 0;
          }

            // Ajax para almacenar el EGRESO / GASTO
            $.ajax({
                url: '../business/controller/class.egresosEventos.php',
                data: { funcion: 1,txtDescripcionEgreso: txtDescripcionEgreso,
                    selec_IdActividad: selec_IdActividad, txtFecha: txtFecha, txtValorPagado: txtValorPagado, 
                    selec_IdEventos: selec_IdEventos, select_Cuentas: select_Cuentas, check: check},
                dataType: "json",
                type: "POST",
                success: function(arr) {
                    if (arr.ok == 1) {   
                        $("#modal-Egresos").modal('hide');
                        swal({
                            type: 'success',
                            title: 'Egreso Creado',
                            text: 'Egreso Creado Exitosamente',
                        });
                        bod.getEgresos();
                    } else {
                        swal({
                            type: 'error',
                            title: 'Error',
                            text: 'No se pudo crear el proyecto',
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
     * getProveedores: Método para consutlar los 
     * Proyectos activos
     */
     getActividades() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */

         var IdEvento = $("#selec_IdEventos").val();

        $("#selec_IdActividad").empty();
        $("#selec_IdActividad").append('<option value="">Seleccione un Proveedor</option>');
        $.ajax({
            url: '../business/controller/class.actividades.php',
            data: { funcion: 3, IdEvento : IdEvento },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#selec_IdActividad").append('<option value="' + v['eva_Id'] + '">' + v['eva_Descripcion'] + '</option>');
                    });

                } else { }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }

    /**
     * getProyectos: Método para consutlar los 
     * Proyectos activos
     */
     getEventos() {
        /* $('#loading').show();
         $('#wrapper').addClass('body-load');  */
        $("#selec_IdEventos").empty();
        $("#selec_IdEventos").append('<option value="">Seleccione un Evento</option>');
        $.ajax({
            url: '../business/controller/class.eventos.php',
            data: { funcion: 3 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        $("#selec_IdEventos").append('<option value="' + v['eve_Id'] + '">' + v['eve_Nombre'] + '</option>');
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
     * getProyectos: Método para consutlar los 
     * Proyectos activos
     */
    getCuentas() {
        /* $('#loading').show();
            $('#wrapper').addClass('body-load');  */
        $("#select_Cuentas").empty();
        $("#select_Cuentas").append('<option value="">Seleccione un Cuenta</option>');
        $.ajax({
            url: '../business/controller/class.formasPago.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                if (arr.ok == 1) {

                    $.each(arr.datos, function(k, v) {
                        if(v['forpa_Id'] != 1){
                            $("#select_Cuentas").append('<option value="' + v['forpa_Id'] + '">' + v['forpa_Descripcion'] + '</option>');    
                        }
                        
                    });

                } else {  }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
                //location.href = "../../login.html";
            }
        });
    }
    

    /**
    * activarSupervisor: Metodo para activar campo de ingresar supervisor
    */
    activarSupervisor(obj){   
        if (obj.checked){
            document.getElementById('select_Cuentas').style.display = "";
        }else{   
            document.getElementById('select_Cuentas').style.display = "none";
        }     
    }
    
    



    /**
     * ClientesActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    ClientesActivo() {
        $(".Menuactivo").removeClass('expand');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#SubEventos").addClass('expand');
        $("#SubEventos").addClass('active');
       //$("#SubEventos").addClass('show');
        $("#SubMenuEgresosEventos").addClass('active');
    }

}
const bod = new Proyectos();

bod.getEgresos();
bod.getEventos();
bod.getActividades();
bod.ClientesActivo();
bod.getCuentas();

// Jquery Dependency

$("input[data-type='currency']").on({
    keyup: function() {
      formatCurrency($(this));
    },
    blur: function() { 
      formatCurrency($(this), "blur");
    }
});


function formatNumber(n) {
  // format number 1000000 to 1,234,567
  return n.replace(/\D/g, "").replace(/\B(?=(\d{3})+(?!\d))/g, ".")
}


function formatCurrency(input, blur) {
  // appends $ to value, validates decimal side
  // and puts cursor back in right position.
  
  // get input value
  var input_val = input.val();
  
  // don't validate empty input
  if (input_val === "") { return; }
  
  // original length
  var original_len = input_val.length;

  // initial caret position 
  var caret_pos = input.prop("selectionStart");
    
  // check for decimal
  if (input_val.indexOf(",") >= 0) {

    // get position of first decimal
    // this prevents multiple decimals from
    // being entered
    var decimal_pos = input_val.indexOf(",");

    // split number by decimal point
    var left_side = input_val.substring(0, decimal_pos);
    var right_side = input_val.substring(decimal_pos);

    // add commas to left side of number
    left_side = formatNumber(left_side);

    // validate right side
    right_side = formatNumber(right_side);
    
    // On blur make sure 2 numbers after decimal
    if (blur === "blur") {
      right_side += "";
    }
    
    // Limit decimal to only 2 digits
    right_side = right_side.substring(0, 0);

    // join number by .
    input_val = left_side + "," + right_side;

  } else {
    // no decimal entered
    // add commas to number
    // remove all non-digits
    input_val = formatNumber(input_val);
    input_val = input_val;
    
    // final formatting
    if (blur === "blur") {
      input_val += "";
    }
  }
  
  // send updated string to input
  input.val(input_val);

  // put caret back in the right position
  var updated_len = input_val.length;
  caret_pos = updated_len - original_len + caret_pos;
  input[0].setSelectionRange(caret_pos, caret_pos);
}