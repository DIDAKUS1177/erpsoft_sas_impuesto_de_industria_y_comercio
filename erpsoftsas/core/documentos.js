var enable = true;

var idUsuario = localStorage.getItem('id_Usuario');

class Documentos {

    constructor() {}

    /**
     * crearEmpresa: Método para abrir modal de creación
     */
    async crearDocumentos() {
        
        var permiso = await _permisos.getPermisos(idRol, 728);
        
        if (permiso.ok != 1) {
            swal({
                type: 'warning',
                title: 'Error de privilegios',
                text: 'Usted no tiene los privilegios para realizar esta acción,' +
                    'para obtenerlos comuniquese con el admininstrador del sistema',
            });
        } else {
       
            $("#formCrearDocumentos").trigger("reset");
            $("#formCrearDocumentos").attr('action', 'javascript:documentos.postDocumentos()');
            $("#modal_footer_Documentos").empty();
            $("#modal_footer_Documentos").append(
                '<button type="submit" class="btn btn-success btn-pill"><span class="ti-plus"></span>' +
                ' Crear' +
                '</button>'
            );
        }
    }

    /**
     * postDocumentos: Método para crear peticiones
     */
    postDocumentos() {
        debugger;
        $('#loading').show();
        $('#wrapper').addClass('body-load');

        var idTipoPeticion = $("#pe_IdTipoPeticion").val();
        var nombreCompleto = $("#pe_NombreCompleto").val();
        var numeroIdentificacion = $("#pe_NumeroIdentificacion").val();
        var direccion = $("#pe_Direccion").val();
        var telefono = $("#pe_Telefono").val();
        var correoElectronico = $("#pe_CorreoElectronico").val();
        var idDependencia = $("#pe_IdDependencia").val();
        var idCategoria = $("#pe_IdCategoria").val();
        var idSubCategoria = $("#pe_IdSubCategoria").val();
        var numeroFolios = $("#pe_NumeroFolios").val();
        var prioridad = $("#pe_Prioridad").val();
        var formaRecepcion = $("#pe_FormaRecepcion").val();
        // var idDependenciaResponsable = $("#pe_IdDependenciaResponsable").val();
        var idDependenciaResponsable = $("#pe_IdDependencia option:selected").attr('data-extra');
        var idEstadoTiposPeticion = $("#pe_IdTipoPeticion option:selected").attr('data-extra');
        var descripcion = $("#pe_Descripcion").val();
        var observaciones = $("#pe_Observaciones").val();
        var estado = 1;

        // Crear FormData y agregar los campos
        var formData = new FormData();
        formData.append('funcion', 1);
        formData.append('pe_IdTipoPeticion', idTipoPeticion);
        formData.append('pe_NombreCompleto', nombreCompleto);
        formData.append('pe_NumeroIdentificacion', numeroIdentificacion);
        formData.append('pe_Direccion', direccion);
        formData.append('pe_Telefono', telefono);
        formData.append('pe_CorreoElectronico', correoElectronico);
        formData.append('pe_NumeroFolios', numeroFolios);
        formData.append('pe_IdDependencia', idDependencia);
        formData.append('pe_IdCategoria', idCategoria);
        formData.append('pe_IdSubCategoria', idSubCategoria);
        formData.append('pe_Prioridad', prioridad);
        formData.append('pe_FormaRecepcion', formaRecepcion);
        formData.append('pe_IdDependenciaResponsable', idDependenciaResponsable);
        formData.append('pe_IdEstadoTiposPeticion', idEstadoTiposPeticion);
        formData.append('pe_Descripcion', descripcion);
        formData.append('pe_Observaciones', observaciones);
        formData.append('pe_Estado', estado);
        formData.append('idUsuario', idUsuario);
        
        
        // Agregar archivos PDF
        var pdfFiles = $('#doc_Anexos')[0].files;
        for (var i = 0; i < pdfFiles.length; i++) {
            formData.append('doc_Anexos[]', pdfFiles[i]);
        }
        
        $.ajax({
            url: '../business/controller/class.peticiones.php',
            /*
            data: { funcion: 1, pe_IdTipoPeticion: idTipoPeticion, pe_NombreCompleto: nombreCompleto, 
                pe_NumeroIdentificacion: numeroIdentificacion, pe_Direccion: direccion, pe_Telefono: telefono, 
                pe_CorreoElectronico: correoElectronico, pe_NumeroFolios: numeroFolios, pe_IdDependencia: idDependencia,
                pe_IdCategoria: idCategoria, pe_IdSubCategoria: idSubCategoria, pe_Prioridad: prioridad, pe_FormaRecepcion: formaRecepcion,
                pe_IdDependenciaResponsable: idDependenciaResponsable,pe_IdEstadoTiposPeticion: idEstadoTiposPeticion,
                pe_Descripcion: descripcion, pe_Observaciones: observaciones, pe_Estado: estado},
            */
            data: formData,
            dataType: "json",
            type: "POST",
            contentType: false,
            processData: false,
            success: function(arr) {
                $('#loading').hide();
                $('#wrapper').removeClass('body-load');
                console.log(arr.id);
                window.open('../extensiones/radicado.php?codigo='+arr.id+'', '_blank');

                if (arr.ok == 1) {
                    $("#formCrearDocumentos").trigger("reset");
                    
                    swal({
                        type: 'success',
                        title: 'Petición Creada',
                        text: 'Petición Creado Exitosamente',
                    });
                } else {
                    swal({
                        type: 'error',
                        title: 'Error',
                        text: 'No se pudo crear la petición'+arr.mensaje,
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    
    /**
     * getIdTipoPeticion: Método para consultar las getIdTipoPeticion
     */
    getIdTipoPeticion() {

        $.ajax({
            url: '../business/controller/class.tiposPeticiones.php',
            data: { funcion: 3, estado:1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#pe_IdTipoPeticion").empty();
                $("#pe_IdTipoPeticion").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pe_IdTipoPeticion").append('<option  data-extra="' + v['strIdEstadoInicial'] + '" value="' + v['tipe_Id'] + '">' + v['tipe_Nombre'] + '</option>');
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }


    /**
     * getIdDependencia: Método para consultar las getIdDependencia
     */
    getIdDependencia() {

        $.ajax({
            url: '../business/controller/class.dependencia.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr);
                $("#pe_IdDependencia").empty();
                $("#pe_IdDependencia").append('<option value="">Seleccion una opción</option>');
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pe_IdDependencia").append('<option data-extra="' + v['dep_IdResponsable'] + '" value="' + v['dep_Id'] + '">' + v['dep_Nombre'] + '</option>');
                    });
                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    
    }

    /**
     * getIdSerieDocumental: Método para consutlar los series
     */
    getIdSerieDocumental() {
        $("#pe_IdCategoria").empty();        
        $("#pe_IdCategoria").append('<option value="">Seleccione un Departamento</option>');

        $.ajax({
            url: '../business/controller/class.categoriasDocumental.php',
            data: { funcion: 3, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pe_IdCategoria").append('<option value="' + v['cat_Id'] + '">' + v['cat_Nombre'] + '</option>');
                    });

                }
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

     /**
     * getIdSubSeriesDoc: Método para consutlar los  Sub series
     */
     getIdSubSeriesDoc() {

         var pe_IdCategoria = $("#pe_IdCategoria").val();

        $("#pe_IdSubCategoria").empty();
        $("#pe_IdSubCategoria").append('<option value="">Seleccione un Sub Serie</option>');
        $.ajax({
            url: '../business/controller/class.subCategoriasDocumental.php',
            data: { funcion: 3, idCategoria: pe_IdCategoria, estado: 1 },
            dataType: "json",
            type: "POST",
            success: function(arr) {
                console.log('arr', arr)
                if (arr.ok == 1) {
                    $.each(arr.datos, function(k, v) {
                        $("#pe_IdSubCategoria").append('<option value="' + v['subc_Id'] + '">' + v['subc_Nombre'] + '</option>');
                    });
                }                
            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log('Este es el error', XMLHttpRequest, textStatus, errorThrown);
            }
        });
    }

    /**
     * DocuemntosActivo: Método para activar el menú y facilitar
     * la navegación al usuario permitendole saber en
     * que lugar esta
     */
    DocuemntosActivo() {
        $(".Menuactivo").removeClass('expand active');
        $(".showMenu").removeClass('show');
        $(".subMenuactivo").removeClass('active');

        $("#DGestorDocumental").addClass('expand');
        $("#DGestorDocumental").addClass('active');
        $("#SubGestorDocumental").addClass('show');
        $("#SubDocumentos").addClass('active');
    }

}

const documentos = new Documentos();

documentos.crearDocumentos();
documentos.getIdDependencia();
documentos.getIdTipoPeticion();
documentos.DocuemntosActivo();

documentos.getIdSerieDocumental();
documentos.getIdSubSeriesDoc();