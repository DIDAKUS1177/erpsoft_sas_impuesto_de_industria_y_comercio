/*
 * ============================================================================
 * ROLES Y PERMISOS (dist/rol.php) — reescrito el 2026-09-29
 * ============================================================================
 *
 * Pedido del cliente: que el administrador arme cada rol con interruptores,
 * uno por acción ("ver declaraciones", "firmar", "presentar"...), agrupados
 * como el menú, y que el sistema los haga cumplir.
 *
 *   Lista      class.rol.php funcion 3 (roles con tipo, cuentas y permisos)
 *   Permisos   funcion 5 (catálogo por grupos con lo prendido del rol)
 *   Guardar    funcion 6 (la lista de claves prendidas)
 *   Crear/editar/estado: funciones 1, 2 y 4
 *
 * Aquí solo se muestra y se ayuda; las reglas las exige el servidor
 * (PermisosRol): el Administrador no se toca, las secciones de la Alcaldía
 * solo cuentan en roles de la Alcaldía, cada permiso arrastra lo que necesita
 * y nadie cambia su propio rol ni reparte lo que no tiene.
 *
 * Antes esta pantalla escribía con class.permisos.php (borrar todo e
 * insertar), sin sesión ni permiso, y marcaba "módulos" y "submódulos" de
 * menú en vez de acciones.
 * ============================================================================
 */
var Roles = (function () {

    var URL = '../business/controller/class.rol.php';

    var estado = {
        roles: [],
        rol: null,          // el que se está viendo
        grupos: [],
        requisitos: {},     // clave -> claves que necesita
        dependientes: {},   // clave -> claves que la necesitan
        nombres: {},        // clave -> nombre visible
        propias: null,      // si no es admin y el rol es de la Alcaldía: lo que puede dar
        editable: false,
        original: {},       // clave -> true, como está guardado
        actual: {}          // clave -> true, como está en pantalla
    };

    /* ------------------------------------------------------------ utilidades */

    function esc(t) {
        return String(t == null ? '' : t)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function pedir(datos) {
        return $.ajax({ url: URL, type: 'POST', dataType: 'json', data: datos });
    }

    function errorDeRed() {
        swal({ type: 'error', title: 'No se pudo completar',
               text: 'No hubo respuesta del servidor. Revise la conexión e intente de nuevo.' });
    }

    // Mientras se piden los permisos de un rol: puntero de espera y sin doble
    // clic en las tarjetas (la capa #loading de la plantilla queda oculta a
    // proposito en todo el sistema).
    function cargando(si) {
        $('body').css('cursor', si ? 'progress' : '');
        $('#tbodyRoles .acc-card').prop('disabled', !!si);
    }

    function plural(n, uno, varios) {
        return n + ' ' + (n === 1 ? uno : varios);
    }

    /* ================================================================ LISTA */

    function listar() {
        return pedir({ funcion: 3 }).done(function (r) {
            if (r.ok != 1) {
                estado.roles = [];
                $('#tbodyRoles').html('<tr><td colspan="6"><div class="estado-vacio es-error">'
                    + '<div class="ev-icono"><i class="fa fa-exclamation-circle"></i></div>'
                    + '<div class="ev-titulo">No se pudieron cargar los roles</div>'
                    + '<div class="ev-texto">' + esc(r.mensaje || '') + '</div></div></td></tr>');
                return;
            }
            estado.roles = r.datos || [];
            pintarLista();
        }).fail(function () {
            $('#tbodyRoles').html('<tr><td colspan="6" class="text-center text-muted py-4">'
                + 'No hubo respuesta del servidor. Recargue la página.</td></tr>');
        });
    }

    function tarjeta(o) {
        return '<button type="button" class="acc-card acc-' + o.tipo + ' ' + o.clase + '" data-id="' + o.id + '"'
             + ' title="' + esc(o.title) + '"><i class="fa ' + o.icono + '"></i>'
             + '<span class="acc-lbl">' + esc(o.texto) + '</span></button>';
    }

    function pintarLista() {
        if (!estado.roles.length) {
            $('#tbodyRoles').html('<tr><td colspan="6"><div class="estado-vacio">'
                + '<div class="ev-icono"><i class="fa fa-users"></i></div>'
                + '<div class="ev-titulo">No hay roles</div></div></td></tr>');
            return;
        }
        var filas = estado.roles.map(function (r) {
            var admin = Number(r.rol_Admin) === 1;
            var activo = Number(r.rol_Estado) === 1;
            var nPermisos = Number(r.rol_Permisos) || 0;
            var total = Number(r.rol_TotalPermisos) || 0;
            var modificable = Number(r.rol_Modificable) === 1;

            var permisos = admin
                ? '<b>Todos</b>'
                : '<span style="font-variant-numeric:tabular-nums;">' + nPermisos + (total ? ' de ' + total : '') + '</span>'
                  + (nPermisos === 0 ? '<span class="sin-permisos">Sin permisos: sus cuentas no pueden entrar</span>' : '');

            var acciones = tarjeta({ tipo: 'primary', clase: 'js-permisos', id: r.rol_Id, icono: 'fa-toggle-on',
                                     texto: 'Permisos', title: admin ? 'Ver sus permisos' : 'Ver y cambiar sus permisos' });
            if (modificable) {
                acciones += tarjeta({ tipo: 'warning', clase: 'js-editar', id: r.rol_Id, icono: 'fa-pencil',
                                      texto: 'Editar', title: 'Nombre, descripción y tipo' });
                if (Number(r.rol_Propio) !== 1) {
                    acciones += activo
                        ? tarjeta({ tipo: 'danger', clase: 'js-estado', id: r.rol_Id, icono: 'fa-ban',
                                    texto: 'Inactivar', title: 'Inactivar el rol' })
                        : tarjeta({ tipo: 'success', clase: 'js-estado', id: r.rol_Id, icono: 'fa-check',
                                    texto: 'Activar', title: 'Activar el rol' });
                }
            }

            return '<tr>'
                + '<td><span class="rol-nombre">' + esc(r.rol_Nombre) + '</span>'
                +   (Number(r.rol_Propio) === 1 ? '<span class="marca-propio">Su rol</span>' : '')
                +   (r.rol_Descripcion ? '<span class="rol-desc">' + esc(r.rol_Descripcion) + '</span>' : '')
                + '</td>'
                + '<td><span class="chip-tipo tipo-' + esc(r.rol_Tipo) + '">' + esc(r.rol_TipoNombre || r.rol_Tipo) + '</span></td>'
                + '<td class="num">' + (Number(r.rol_Cuentas) || 0) + '</td>'
                + '<td class="num">' + permisos + '</td>'
                + '<td><span class="chip-estado ' + (activo ? 'est-activo' : 'est-inactivo') + '">'
                +   (activo ? 'Activo' : 'Inactivo') + '</span></td>'
                + '<td class="text-center"><div class="acc-cards">' + acciones + '</div></td>'
                + '</tr>';
        });
        $('#tbodyRoles').html(filas.join(''));
    }

    function rolDeLista(id) {
        id = Number(id);
        return estado.roles.filter(function (r) { return Number(r.rol_Id) === id; })[0] || null;
    }

    /* ======================================================= CREAR / EDITAR */

    function abrirFormulario(id) {
        var r = id ? rolDeLista(id) : null;
        $('#formRol')[0].reset();
        $('#rolId').val(r ? r.rol_Id : '');
        $('#tituloModalRol').text(r ? 'Editar rol' : 'Crear rol');
        $('#rolNombre').val(r ? r.rol_Nombre : '');
        $('#rolDescripcion').val(r ? (r.rol_Descripcion || '') : '');

        var tipo = r ? r.rol_Tipo : 'ALCALDIA';
        $('input[name="rolTipo"]').prop('disabled', false).prop('checked', false);
        $('input[name="rolTipo"][value="' + tipo + '"]').prop('checked', true);

        // Con cuentas no se cambia el tipo (el servidor tampoco lo deja): les
        // daría o quitaría de golpe el acceso de funcionarios.
        var cuentas = r ? (Number(r.rol_Cuentas) || 0) : 0;
        $('input[name="rolTipo"]').prop('disabled', cuentas > 0);
        $('#rolTipoAviso').prop('hidden', cuentas === 0)
            .text(cuentas > 0 ? 'Tiene ' + plural(cuentas, 'cuenta', 'cuentas')
                + ': para cambiarle el tipo, páselas antes a otro rol en Usuarios.' : '');

        $('#modalRol').modal({ backdrop: 'static', keyboard: true }).modal('show');
    }

    var guardandoRol = false;

    function guardarRol(e) {
        e.preventDefault();
        if (guardandoRol) { return; }
        var id = $('#rolId').val();
        var nombre = $.trim($('#rolNombre').val());
        var tipo = $('input[name="rolTipo"]:checked').val() || '';
        if (!nombre) {
            swal({ type: 'warning', title: 'Falta el nombre', text: 'Escriba el nombre del rol.' });
            return;
        }
        if (!tipo) {
            swal({ type: 'warning', title: 'Falta el tipo', text: 'Escoja si el rol es de la Alcaldía, de contribuyentes o de consulta externa.' });
            return;
        }
        guardandoRol = true;
        $('#btnGuardarRol').prop('disabled', true).text('Guardando…');
        pedir({ funcion: id ? 2 : 1, id: id, nombre: nombre, descripcion: $('#rolDescripcion').val(), tipo: tipo })
            .done(function (r) {
                if (r.ok != 1) {
                    swal({ type: 'warning', title: 'No se guardó', text: r.mensaje || 'Revise los datos.' });
                    return;
                }
                $('#modalRol').modal('hide');
                var nuevo = !id && r.datos && r.datos.rol_Id;
                listar().done(function () {
                    if (nuevo) {
                        // Recién creado no tiene permisos: se abre para prenderlos.
                        swal({ type: 'success', title: 'Rol creado',
                               text: 'Ahora prenda sus permisos: sin ninguno, sus cuentas no pueden entrar.' })
                            .then(function () { abrirPermisos(r.datos.rol_Id); });
                    } else {
                        swal({ type: 'success', title: 'Rol actualizado', text: r.mensaje, timer: 1800 });
                    }
                });
            })
            .fail(errorDeRed)
            .always(function () {
                guardandoRol = false;
                $('#btnGuardarRol').prop('disabled', false).text('Guardar');
            });
    }

    function cambiarEstado(id) {
        var r = rolDeLista(id);
        if (!r) { return; }
        var activar = Number(r.rol_Estado) !== 1;
        swal({
            type: 'question',
            // El titulo de SweetAlert2 7 se pinta como HTML: el nombre, escapado.
            title: (activar ? '¿Activar' : '¿Inactivar') + ' el rol «' + esc(r.rol_Nombre) + '»?',
            text: activar ? 'Sus cuentas podrán volver a entrar con sus permisos.'
                          : 'Un rol inactivo no deja entrar a nadie. Solo se puede inactivar sin cuentas activas.',
            showCancelButton: true,
            confirmButtonText: activar ? 'Sí, activar' : 'Sí, inactivar',
            cancelButtonText: 'Cancelar'
        }).then(function (res) {
            if (!res.value) { return; }
            pedir({ funcion: 4, id: r.rol_Id, estado: activar ? 1 : 0 }).done(function (x) {
                if (x.ok != 1) {
                    swal({ type: 'warning', title: 'No se cambió', text: x.mensaje || '' });
                    return;
                }
                listar();
                swal({ type: 'success', title: x.mensaje, timer: 1600 });
            }).fail(errorDeRed);
        });
    }

    /* ============================================================ PERMISOS */

    function abrirPermisos(id) {
        cargando(true);
        pedir({ funcion: 5, id_rol: id }).done(function (r) {
            if (r.ok != 1) {
                swal({ type: 'warning', title: 'No se pudieron abrir los permisos', text: r.mensaje || '' });
                return;
            }
            prepararPermisos(r.datos);
            $('#vistaRoles').prop('hidden', true);
            $('#vistaPermisos').prop('hidden', false);
            window.scrollTo(0, 0);
            try { history.replaceState(null, '', '#rol-' + r.datos.rol.id); } catch (e) { /* sin historia */ }
        }).fail(errorDeRed).always(function () { cargando(false); });
    }

    function prepararPermisos(d) {
        estado.rol = d.rol;
        estado.grupos = d.grupos || [];
        estado.requisitos = d.requisitos || {};
        estado.propias = Array.isArray(d.propias) ? d.propias : null;
        estado.editable = Number(d.puedeEditar) === 1;
        estado.dependientes = {};
        estado.nombres = {};
        estado.original = {};

        Object.keys(estado.requisitos).forEach(function (c) {
            (estado.requisitos[c] || []).forEach(function (r) {
                (estado.dependientes[r] = estado.dependientes[r] || []).push(c);
            });
        });
        estado.grupos.forEach(function (g) {
            g.permisos.forEach(function (p) {
                estado.nombres[p.clave] = p.nombre;
                if (Number(p.activo) === 1) { estado.original[p.clave] = true; }
            });
        });
        estado.actual = $.extend({}, estado.original);

        pintarCabecera(d);
        pintarGrupos();
        refrescar();
    }

    function pintarCabecera(d) {
        var rol = d.rol;
        $('#permRolNombre').text(rol.nombre);
        $('#permRolMeta').html(
            '<span class="chip-tipo tipo-' + esc(rol.tipo) + '">' + esc(rol.tipoNombre) + '</span>'
            + '<span>' + plural(Number(rol.cuentas) || 0, 'cuenta', 'cuentas') + '</span>'
            + (Number(rol.estado) === 1 ? '' : '<span class="chip-estado est-inactivo">Inactivo</span>')
            + (rol.descripcion ? '<span>· ' + esc(rol.descripcion) + '</span>' : '')
        );

        var aviso = '';
        var clase = 'alert-info';
        if (Number(rol.admin) === 1) {
            aviso = '<b>El Administrador tiene todos los permisos, siempre.</b> No se pueden quitar: así nadie deja el sistema sin quien lo administre.';
        } else if (!estado.editable) {
            aviso = esc(d.motivo || 'Puede ver los permisos de este rol, pero no cambiarlos.');
            clase = 'alert-warning';
        } else if (Number(rol.alcaldia) !== 1) {
            aviso = 'Es un rol de <b>' + esc(rol.tipoNombre.toLowerCase()) + '</b>: cada cuenta trabaja solo sobre su propio '
                  + 'contribuyente. Las secciones de la Alcaldía no aplican.';
        } else {
            aviso = 'Es un rol de la <b>Alcaldía</b>. RIT, establecimientos y declaraciones se usan sobre el contribuyente que '
                  + 'se gestione, así que necesitan «Gestionar a un contribuyente».';
        }
        if (estado.editable && estado.propias) {
            aviso += '<br><span style="font-size:12.5px;">Solo puede prender permisos que su propio rol tiene; los demás salen bloqueados.</span>';
        }
        $('#permAviso').removeClass('alert-info alert-warning').addClass(clase).html(aviso).prop('hidden', !aviso);

        $('#permAcciones').toggle(estado.editable);
        $('#btnDescartar, #btnGuardarPermisos').toggle(estado.editable);
    }

    /** ¿Este interruptor se puede mover? */
    function bloqueado(clave, grupo) {
        if (!estado.editable) { return true; }
        if (Number(grupo.soloAlcaldia) === 1 && Number(estado.rol.alcaldia) !== 1) { return true; }
        if (estado.propias && estado.propias.indexOf(clave) === -1) { return true; }
        return false;
    }

    function idDe(clave) { return 'perm-' + clave.replace(/[^a-z0-9]/gi, '-'); }

    function pintarGrupos() {
        var html = estado.grupos.map(function (g) {
            var noAplica = Number(g.soloAlcaldia) === 1 && Number(estado.rol.alcaldia) !== 1;
            var nota = '';
            if (noAplica) {
                nota = '<p class="perm-grupo-nota">Solo para roles de la Alcaldía: en un rol de '
                     + esc(estado.rol.tipoNombre.toLowerCase()) + ' no se usan.</p>';
            } else if (g.clave === 'alcaldia' && Number(estado.rol.admin) !== 1) {
                nota = '<p class="perm-grupo-nota es-info">«Gestionar a un contribuyente» es lo que deja trabajar sobre '
                     + 'cualquiera: con él aparece «Gestionando a» en el menú.</p>';
            }

            var items = g.permisos.map(function (p) {
                var id = idDe(p.clave);
                var req = (estado.requisitos[p.clave] || []).map(function (c) {
                    return '«' + esc(estado.nombres[c] || c) + '»';
                });
                return '<li>'
                    + '<div class="custom-control custom-switch">'
                    +   '<input type="checkbox" class="custom-control-input js-permiso" id="' + id + '"'
                    +     ' data-clave="' + esc(p.clave) + '" data-grupo="' + esc(g.clave) + '"'
                    +     (bloqueado(p.clave, g) ? ' disabled' : '') + '>'
                    +   '<label class="custom-control-label" for="' + id + '">'
                    +     '<span class="perm-nombre">' + esc(p.nombre) + '</span>'
                    +     (p.descripcion ? '<span class="perm-desc">' + esc(p.descripcion) + '</span>' : '')
                    +     (req.length && Number(estado.rol.admin) !== 1
                              ? '<span class="perm-requiere"><i class="fa fa-link"></i>Prende también ' + req.join(' y ') + '</span>' : '')
                    +   '</label>'
                    + '</div>'
                    + '</li>';
            }).join('');

            var idGrupo = 'grupo-' + g.clave.replace(/[^a-z0-9]/gi, '-');
            return '<div class="card-box perm-grupo' + (noAplica ? ' apagado' : '') + '" data-grupo="' + esc(g.clave) + '">'
                + '<div class="perm-grupo-cab">'
                +   '<div><h5>' + esc(g.nombre) + '</h5><span class="perm-cuenta" data-cuenta="' + esc(g.clave) + '"></span></div>'
                +   '<div class="custom-control custom-switch interruptor-grupo">'
                +     '<input type="checkbox" class="custom-control-input js-grupo" id="' + idGrupo + '" data-grupo="' + esc(g.clave) + '"'
                +       ' aria-label="Todo ' + esc(g.nombre) + '">'
                +     '<label class="custom-control-label" for="' + idGrupo + '">Todo</label>'
                +   '</div>'
                + '</div>'
                + nota
                + '<ul class="perm-lista">' + items + '</ul>'
                + '</div>';
        }).join('');
        $('#permGrupos').html(html);
    }

    /** Pone los interruptores, conteos y la barra según estado.actual. */
    function refrescar() {
        $('#permGrupos .js-permiso').each(function () {
            this.checked = !!estado.actual[this.getAttribute('data-clave')];
        });

        var total = 0, prendidos = 0;
        estado.grupos.forEach(function (g) {
            var n = 0, movibles = 0, movPrendidos = 0;
            g.permisos.forEach(function (p) {
                total++;
                var on = !!estado.actual[p.clave];
                if (on) { n++; prendidos++; }
                if (!bloqueado(p.clave, g)) { movibles++; if (on) { movPrendidos++; } }
            });
            $('[data-cuenta="' + g.clave + '"]').text(n + ' de ' + g.permisos.length);
            var sw = document.getElementById('grupo-' + g.clave.replace(/[^a-z0-9]/gi, '-'));
            if (sw) {
                // Con permisos bloqueados (los que el editor no tiene), "Todo" se
                // refiere a los que SI puede mover; si no, se quedaba a medias y
                // los clics siguientes no hacian nada.
                var base = movibles > 0 ? movibles : g.permisos.length;
                var prendidosBase = movibles > 0 ? movPrendidos : n;
                sw.disabled = movibles === 0 || guardandoPermisos;
                sw.checked = prendidosBase === base && base > 0;
                sw.indeterminate = prendidosBase > 0 && prendidosBase < base;
            }
        });

        var admin = Number(estado.rol && estado.rol.admin) === 1;
        $('#permConteo').text(admin ? 'Todos los permisos (' + total + ')'
                                    : prendidos + ' de ' + total + ' permisos prendidos');
        var cambios = hayCambios();
        $('#permCambios').prop('hidden', !cambios);
        $('#btnDescartar, #btnGuardarPermisos').prop('disabled', !cambios);
    }

    function hayCambios() {
        var a = Object.keys(estado.actual).filter(function (c) { return estado.actual[c]; }).sort().join(',');
        var b = Object.keys(estado.original).filter(function (c) { return estado.original[c]; }).sort().join(',');
        return a !== b;
    }

    function grupoDe(clave) {
        for (var i = 0; i < estado.grupos.length; i++) {
            if (estado.grupos[i].permisos.some(function (p) { return p.clave === clave; })) { return estado.grupos[i]; }
        }
        return null;
    }

    /**
     * Prende o apaga una clave con lo que arrastra: prender una prende lo que
     * necesita ("Firmar" prende "Ver"); apagar una apaga lo que depende de
     * ella. Devuelve las OTRAS claves que cambiaron, para contarlo.
     */
    function mover(clave, prender, arrastradas) {
        arrastradas = arrastradas || [];
        if (!!estado.actual[clave] === prender) { return arrastradas; }
        var g = grupoDe(clave);
        if (!g || bloqueado(clave, g)) { return arrastradas; }
        if (prender) { estado.actual[clave] = true; } else { delete estado.actual[clave]; }
        var siguientes = prender ? (estado.requisitos[clave] || []) : (estado.dependientes[clave] || []);
        siguientes.forEach(function (c) {
            if (!!estado.actual[c] !== prender) {
                var g2 = grupoDe(c);
                if (g2 && !bloqueado(c, g2)) {
                    arrastradas.push(c);
                    mover(c, prender, arrastradas);
                }
            }
        });
        return arrastradas;
    }

    function eco(texto) {
        $('#permEco').text(texto || '');
        clearTimeout(eco._t);
        if (texto) { eco._t = setTimeout(function () { $('#permEco').text(''); }, 6000); }
    }

    function alMoverPermiso() {
        var clave = this.getAttribute('data-clave');
        var arr = mover(clave, this.checked);
        refrescar();
        if (arr.length) {
            var nombres = arr.map(function (c) { return '«' + (estado.nombres[c] || c) + '»'; }).join(', ');
            eco((this.checked ? 'También se prendió ' : 'También se apagó ') + nombres
                + (this.checked ? ': lo necesita.' : ': depende de este.'));
        } else {
            eco('');
        }
    }

    function alMoverGrupo() {
        var g = estado.grupos.filter(function (x) { return x.clave === this.getAttribute('data-grupo'); }, this)[0];
        if (!g) { return; }
        var prender = this.checked;
        var arrastradas = [];
        g.permisos.forEach(function (p) { mover(p.clave, prender, arrastradas); });
        refrescar();
        var fuera = arrastradas.filter(function (c) { return grupoDe(c) !== g; });
        eco(fuera.length ? (prender ? 'También se prendió ' : 'También se apagó ')
            + fuera.map(function (c) { return '«' + (estado.nombres[c] || c) + '»'; }).join(', ') + '.' : '');
    }

    function todo(prender) {
        estado.grupos.forEach(function (g) {
            g.permisos.forEach(function (p) { mover(p.clave, prender); });
        });
        refrescar();
        eco('');
    }

    var guardandoPermisos = false;

    function guardarPermisos() {
        if (guardandoPermisos || !estado.editable || !hayCambios()) { return; }
        var claves = Object.keys(estado.actual).filter(function (c) { return estado.actual[c]; });

        var seguir = claves.length ? Promise.resolve({ value: true })
            : swal({
                type: 'warning',
                title: '¿Dejar el rol sin permisos?',
                text: 'Sus cuentas no podrán entrar al sistema hasta que se le prenda alguno.',
                showCancelButton: true, confirmButtonText: 'Sí, guardar así', cancelButtonText: 'Cancelar'
            });

        seguir.then(function (res) {
            if (!res || !res.value) { return; }
            guardandoPermisos = true;
            // Lo que se moviera mientras guarda se perderia al llegar la
            // respuesta: se bloquea hasta entonces.
            $('#permGrupos input, #btnPrenderTodo, #btnApagarTodo, #btnDescartar').prop('disabled', true);
            $('#btnGuardarPermisos').prop('disabled', true).text('Guardando…');
            pedir({ funcion: 6, id_rol: estado.rol.id, claves: JSON.stringify(claves) })
                .done(function (r) {
                    if (r.ok != 1) {
                        swal({ type: 'warning', title: 'No se guardaron los permisos', text: r.mensaje || '' });
                        return;
                    }
                    // Lo que quedó de verdad (el servidor completa requisitos).
                    estado.original = {};
                    ((r.datos && r.datos.claves) || claves).forEach(function (c) { estado.original[c] = true; });
                    estado.actual = $.extend({}, estado.original);
                    refrescar();
                    eco('');
                    var n = Number(r.datos && r.datos.activos) || 0;
                    swal({ type: 'success', title: 'Permisos guardados',
                           text: (n ? 'Quedó con ' + plural(n, 'permiso prendido', 'permisos prendidos') + '. '
                                    : 'Quedó sin permisos: sus cuentas no podrán entrar. ')
                               + 'Las cuentas de este rol lo verán al cambiar de pantalla.' });
                    listar();
                })
                .fail(errorDeRed)
                .always(function () {
                    guardandoPermisos = false;
                    $('#btnGuardarPermisos').text('Guardar permisos');
                    $('#btnPrenderTodo, #btnApagarTodo').prop('disabled', false);
                    pintarGrupos();   // devuelve a cada interruptor su bloqueo
                    refrescar();
                });
        });
    }

    function descartar() {
        estado.actual = $.extend({}, estado.original);
        refrescar();
        eco('');
    }

    function volver(e) {
        if (e) { e.preventDefault(); }
        var salir = function () {
            $('#vistaPermisos').prop('hidden', true);
            $('#vistaRoles').prop('hidden', false);
            estado.rol = null;
            try { history.replaceState(null, '', location.pathname); } catch (x) { /* sin historia */ }
            listar();
        };
        if (!hayCambios()) { salir(); return; }
        swal({
            type: 'warning', title: 'Hay cambios sin guardar',
            text: 'Si vuelve a la lista, los interruptores que movió no se guardan.',
            showCancelButton: true, confirmButtonText: 'Volver sin guardar', cancelButtonText: 'Seguir aquí'
        }).then(function (res) { if (res.value) { salir(); } });
    }

    /* ============================================================== INICIO */

    function iniciar() {
        $('#btnNuevoRol').on('click', function () { abrirFormulario(null); });
        $('#formRol').on('submit', guardarRol);
        $('#tbodyRoles')
            .on('click', '.js-permisos', function () { abrirPermisos($(this).data('id')); })
            .on('click', '.js-editar', function () { abrirFormulario($(this).data('id')); })
            .on('click', '.js-estado', function () { cambiarEstado($(this).data('id')); });

        $('#permGrupos')
            .on('change', '.js-permiso', alMoverPermiso)
            .on('change', '.js-grupo', alMoverGrupo);
        $('#btnPrenderTodo').on('click', function () { todo(true); });
        $('#btnApagarTodo').on('click', function () { todo(false); });
        $('#btnGuardarPermisos').on('click', guardarPermisos);
        $('#btnDescartar').on('click', descartar);
        $('#btnVolverRoles').on('click', volver);

        window.addEventListener('beforeunload', function (e) {
            if (estado.rol && hayCambios()) { e.preventDefault(); e.returnValue = ''; }
        });

        listar().done(function () {
            // #rol-N abre directo los permisos de ese rol (recargar no saca de ahí).
            var m = /^#rol-(\d+)$/.exec(location.hash || '');
            if (m && rolDeLista(m[1])) { abrirPermisos(m[1]); }
        });
    }

    return { iniciar: iniciar, abrirPermisos: abrirPermisos };
})();

$(function () { Roles.iniciar(); });
