/**
 * Modulo compartido entre icaWebPresentar.js e icaWebConsultar.js.
 *
 * Antes cada pantalla tenia su propia copia (ligeramente distinta) de la
 * logica que decide que botones mostrar para una declaracion segun su
 * estado de firma. Eso fue lo que causo que "Presentar Declaracion"
 * mostrara botones muertos (href="#") mientras "Consultar Declaraciones"
 * si funcionaba: un cambio en un lado nunca se replicaba al otro.
 *
 * A partir de ahora ambas pantallas llaman a las mismas funciones de aqui,
 * asi que un fix futuro aplica a las dos automaticamente.
 */
var DeclaracionesUI = (function () {

    function nombreMes(mes) {
        var meses = [
            '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];
        return meses[mes] || mes;
    }

    /**
     * Botones de accion para UNA declaracion, segun su estado real.
     *
     * Antes solo se miraba is_signed, asi que una declaracion ya
     * presentada seguia ofreciendo el boton "Presentar" (y una pagada,
     * el de "Pagar"). Ahora cada estado ofrece unicamente lo que todavia
     * tiene sentido hacer:
     *
     *   sin declaracion -> los 3 botones de borrador, deshabilitados
     *   borrador        -> Editar borrador / Firmar / Ver borrador
     *   firmada         -> Descargar PDF / Presentar / Pagar
     *   presentada      -> Descargar PDF / Pagar
     *   pagada          -> Descargar PDF
     *
     * @param {object|null} d Fila devuelta por class.declaracionesIca.php (funcion 8).
     * @param {string} objJs  Nombre de la variable global (en la pantalla que
     *                        llama) que expone editarDeclaracion/abrirFirmaDigital/
     *                        presentarDeclaracion, p.ej. "establecimientos".
     */
    /*
     * Boton de accion en TARJETA (icono + texto). A color cuando la accion
     * esta disponible; en gris (acc-off) cuando no. Pedido del cliente
     * 2026-09-14: los mismos botones y las mismas funciones de siempre, solo
     * cambia la forma. La CSS (.acc-cards/.acc-card) vive en dist/menu.php y la
     * comparten los tres modulos (ICA, Retencion y Autorretencion).
     *
     *   o.tipo    info|warning|primary|success|danger|secondary (color de la accion)
     *   o.icono   clase de Font Awesome
     *   o.texto   etiqueta visible bajo el icono
     *   o.title   tooltip (se conserva el de antes)
     *   o.onclick JS a ejecutar   (excluyente con o.href)
     *   o.href    enlace directo   + o.target opcional
     *   o.off     true = tarjeta gris, sin la accion
     *   o.motivo  (con o.off) POR QUE esta en gris; tambien es su tooltip
     */
    /**
     * ¿El rol puede? (interruptores del panel de Roles, core/Permisos.js). Una
     * clave, o varias separadas por espacio (alguna); un arreglo exige todas.
     * Sin Permisos.js cargado no se esconde nada: decide el servidor.
     */
    function permitido(p) {
        if (typeof erpPuede !== 'function') { return true; }
        return (Array.isArray(p) ? p : [p]).every(function (x) { return erpPuede(x); });
    }

    function accBtn(o) {
        // Lo que el rol no puede hacer no se ofrece (el servidor igual lo
        // rebotaria). Ver permitido().
        if (o.permiso && !permitido(o.permiso)) { return ''; }
        var cls = 'acc-card acc-' + o.tipo + (o.off ? ' acc-off' : '');
        var ini;
        if (o.off && o.motivo) {
            /*
             * GRIS CON MOTIVO (segunda revision 2026-09-28). El motivo iba solo
             * en el title, y la regla .acc-off de dist/menu.php le quita el
             * puntero a la tarjeta (pointer-events:none): el tooltip no salia
             * nunca y el boton no decia por que estaba en gris. Con data-motivo
             * la tarjeta recibe el puntero (ver menu.php) y al pulsarla lo
             * explica; la accion sigue sin ofrecerse. Sin tabindex="-1", para
             * que el motivo tambien llegue con el teclado.
             */
            ini = '<a href="#" role="button" aria-disabled="true" data-motivo="' + atributo(o.motivo) + '"'
                + ' onclick="DeclaracionesUI.mostrarMotivo(this); return false;"';
        } else if (o.off) {
            ini = '<a href="#" aria-disabled="true" tabindex="-1"';
        } else {
            ini = o.href
                ? '<a href="' + o.href + '"' + (o.target ? ' target="' + o.target + '"' : '')
                : '<a href="javascript:void(0);" onclick="' + o.onclick + '"';
        }
        var titulo = (o.off && o.motivo) ? atributo(o.motivo) : o.title;
        return ini + ' class="' + cls + '" title="' + titulo + '">' +
               '<i class="fa ' + o.icono + '"></i>' +
               '<span class="acc-lbl">' + o.texto + '</span></a>';
    }

    /** Texto para ir DENTRO de un atributo HTML ("...") sin romperlo. */
    function atributo(t) {
        return String(t === null || t === undefined ? '' : t)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            .replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /**
     * Dice por que una tarjeta esta en gris (la que trae data-motivo, ver
     * accBtn). getAttribute devuelve el texto ya sin escapar, y swal lo pinta
     * como texto, no como HTML.
     */
    function mostrarMotivo(el) {
        var motivo = (el && el.getAttribute) ? (el.getAttribute('data-motivo') || '') : '';
        var etiqueta = (el && el.querySelector) ? el.querySelector('.acc-lbl') : null;
        var accion = etiqueta ? String(etiqueta.textContent || '').trim() : '';
        if (motivo && typeof swal === 'function') {
            swal({
                type: 'info',
                title: accion ? '«' + accion + '» todavía no está disponible' : 'Todavía no está disponible',
                text: motivo
            });
        }
        return false;
    }

    function envolverAcciones(html) {
        return '<div class="acc-cards">' + html + '</div>';
    }

    function htmlAcciones(d, objJs) {

        objJs = objJs || 'establecimientos';

        if (!d || !d.dec_Id) {
            return envolverAcciones(
                accBtn({ permiso: 'ica.editar', tipo: 'warning',   icono: 'fa-pencil',      texto: 'Editar', title: 'Editar borrador', off: true }) +
                accBtn({ permiso: 'ica.firmar', tipo: 'secondary', icono: 'fa-certificate', texto: 'Firmar', title: 'Firmar',          off: true }) +
                accBtn({ tipo: 'info',      icono: 'fa-eye',         texto: 'Ver',    title: 'Ver borrador',     off: true })
            );
        }

        var clave = estado(d).clave;

        // Descargar el formulario oficial esta disponible en todos los
        // estados: es el mismo documento, con o sin sello segun avance.
        var descargar = accBtn({ permiso: 'ica.ver', tipo: 'primary', icono: 'fa-download', texto: 'Descargar', title: 'Descargar',
                                 href: '../extensiones/declaracion.php?dec_Id=' + d.dec_Id, target: '_blank' });

        /*
         * SIN ACTIVIDADES GUARDADAS NO SE FIRMA NI SE PRESENTA (revision
         * 2026-09-28). Una declaracion recien creada ofrecia "Firmar" antes de
         * haberse guardado nunca, y se podia presentar en $0 sin actividades.
         * El servidor ya lo rechaza (firma y presentacion); aqui el boton sale
         * en gris con el motivo, en vez de dejar que rebote.
         *
         * n_actividades lo trae el listado (funcion 8). Si no viniera -un
         * servidor sin este cambio- no se bloquea nada: decide el servidor.
         */
        var sinGuardar = (d.n_actividades !== undefined && d.n_actividades !== null
                          && Number(d.n_actividades) === 0);
        // "Editar" ya carga las actividades del contribuyente cuando la
        // declaracion no tiene ninguna guardada (ver EditarDeclaracion.abrir):
        // antes la abria con la tabla vacia y este camino no llevaba a nada.
        var MOTIVO_SIN_GUARDAR = 'Todavía no tiene actividades guardadas: ábrala con Editar, '
                               + 'escriba la base de cada actividad y pulse Guardar.';
        var MOTIVO_SIN_GUARDAR_FIRMADA = 'Todavía no tiene actividades guardadas: ábrala con Editar '
                               + '(se le quitan las firmas), escriba la base de cada actividad, '
                               + 'pulse Guardar y vuelva a firmarla.';

        var numeroJs = atributo(JSON.stringify(String(d.dec_NumeroDeclaracion || d.dec_Id)));

        if (clave === 'borrador') {
            return envolverAcciones(
                accBtn({ permiso: 'ica.editar', tipo: 'warning',   icono: 'fa-pencil',          texto: 'Editar', title: 'Editar',
                         onclick: objJs + '.editarDeclaracion(' + d.dec_Id + ')' }) +
                (sinGuardar
                    ? accBtn({ permiso: 'ica.firmar', tipo: 'secondary', icono: 'fa-pencil-square-o', texto: 'Firmar',
                               motivo: MOTIVO_SIN_GUARDAR, off: true })
                    : accBtn({ permiso: 'ica.firmar', tipo: 'secondary', icono: 'fa-pencil-square-o', texto: 'Firmar', title: 'Firmar',
                               onclick: objJs + '.abrirFirmaDigital(' + d.dec_Id + ', ' + d.dec_IdEstablecimiento + ')' })) +
                descargar +
                // Declaración presentada y pagada fuera de la plataforma (migración
                // 044): se llena como cualquier borrador y se registra ya pagada,
                // con el PDF original y el soporte. Solo la Alcaldía con permiso.
                (typeof RegistroManual === 'undefined' ? '' : (sinGuardar || !(Number(d.dec_ValorConcepto20) > 0)
                    ? accBtn({ permiso: 'alcaldia.declaraciones.historicas', tipo: 'success', icono: 'fa-archive', texto: 'Ya pagada',
                               motivo: sinGuardar ? MOTIVO_SIN_GUARDAR
                                       : 'El total a pagar está en $0: ábrala con Editar, revise los valores del papel y pulse Guardar.',
                               off: true })
                    : accBtn({ permiso: 'alcaldia.declaraciones.historicas', tipo: 'success', icono: 'fa-archive', texto: 'Ya pagada',
                               title: 'Registrar como presentada y pagada fuera de la plataforma (con el PDF original y el soporte de pago)',
                               onclick: 'RegistroManual.historica(&quot;ica&quot;, ' + d.dec_Id + ', ' + numeroJs + ')' }))) +
                accBtn({ permiso: 'ica.editar', tipo: 'danger',    icono: 'fa-trash',           texto: 'Borrar', title: 'Borrar borrador',
                         onclick: objJs + '.borrarDeclaracion(' + d.dec_Id + ')' })
            );
        }

        if (clave === 'pendienteCont' || clave === 'firmada') {
            // "Editar borrador" sobre una firmada BORRA las firmas y devuelve
            // la declaracion a borrador (regla del cliente): dejarian de
            // acreditar el contenido si este cambia.
            var acciones = accBtn({ permiso: 'ica.editar', tipo: 'warning', icono: 'fa-pencil', texto: 'Editar',
                                    title: 'Editar borrador (elimina las firmas)',
                                    onclick: objJs + '.editarFirmada(' + d.dec_Id + ')' }) +
                           descargar;

            // Firmada antes de la regla, pero sin actividades guardadas: ni la
            // firma del contador ni la presentacion van a pasar. Se dice por qué.
            if (sinGuardar) {
                if (clave === 'pendienteCont') {
                    acciones += accBtn({ permiso: 'ica.firmar', tipo: 'info', icono: 'fa-pencil-square-o', texto: 'Firmar contador',
                                         motivo: MOTIVO_SIN_GUARDAR_FIRMADA, off: true });
                }
                return envolverAcciones(acciones +
                       accBtn({ permiso: 'ica.presentar', tipo: 'success', icono: 'fa-paper-plane', texto: 'Presentar',
                                motivo: MOTIVO_SIN_GUARDAR_FIRMADA, off: true }));
            }

            if (clave === 'pendienteCont') {
                // Falta la firma del contador/revisor, pero para quien
                // presenta esto ya NO es un paso aparte: el boton dice
                // "Presentar" igual que en el estado siguiente, y es
                // presentarDeclaracion() -no firmaContador()- quien decide
                // que hacer. Al intentar presentar sin esa firma, el
                // backend responde con datos.codigo="FALTA_CONTADOR" y el
                // frontend abre ahi mismo el OTP del contador; en cuanto
                // firma, reintenta presentar solo. Un unico click de
                // principio a fin, en vez de "manda el codigo" y luego
                // "ahora si presenta" como dos acciones separadas.
                if (Number(d.tiene_correo_contador) === 1) {
                    /*
                     * FIRMAR COMO CONTADOR ES UN ACTO PROPIO, CON SU BOTON.
                     *
                     * Pedido del cliente el 2026-09-01: «firma de contador
                     * aparte de presentar». Hasta ahora la unica via era
                     * pulsar "Presentar" y dejar que el sistema pidiera el
                     * codigo por el camino, asi que firmar y presentar eran
                     * el mismo gesto y no habia forma de hacer solo lo
                     * primero. Eso importa cuando el contador y quien
                     * presenta no son la misma persona: el contador tiene
                     * que poder firmar y marcharse.
                     *
                     * "Presentar" NO cambia: sigue encadenando la firma que
                     * falte y presentando de una sola vez, que es como lo
                     * pidieron antes. Se suma una via, no se sustituye.
                     */
                    acciones += accBtn({ permiso: 'ica.firmar', tipo: 'info', icono: 'fa-pencil-square-o', texto: 'Firmar contador',
                                         title: 'Firmar como contador o revisor fiscal (solo firma, no presenta)',
                                         onclick: objJs + '.firmaContador(' + d.dec_Id + ', ' + d.dec_IdEstablecimiento + ')' });

                    acciones += accBtn({ permiso: ['ica.presentar', 'ica.firmar'], tipo: 'success', icono: 'fa-paper-plane', texto: 'Presentar', title: 'Presentar',
                                         onclick: objJs + '.presentarDeclaracion(' + d.dec_Id + ', ' + d.dec_IdEstablecimiento + ')' });
                } else {
                    // Sin correo registrado no hay a donde mandar el codigo:
                    // se dice que falta el dato en vez de fallar al pulsar.
                    acciones += accBtn({ permiso: 'ica.presentar', tipo: 'success', icono: 'fa-paper-plane', texto: 'Presentar',
                                         motivo: 'Registre el correo del contador o revisor fiscal en el RIT antes de presentar',
                                         off: true });
                }
                return envolverAcciones(acciones);
            }

            return envolverAcciones(acciones +
                   accBtn({ permiso: 'ica.presentar', tipo: 'success', icono: 'fa-paper-plane', texto: 'Presentar', title: 'Presentar',
                            onclick: objJs + '.presentarDeclaracion(' + d.dec_Id + ', ' + d.dec_IdEstablecimiento + ')' }));
        }

        // presentada / pagada
        var botones = descargar;

        /*
         * Correccion: genera una declaracion nueva enlazada a esta. Una vez
         * presentada no existe un "crear otra declaracion" -para la ley es
         * UNA declaracion original por contribuyente y periodo; presentar
         * una segunda "original" para el mismo periodo se ve, ante la
         * Alcaldia, como una doble declaracion, no como una correccion
         * legitima-. Este boton ES la forma correcta de "declarar de
         * nuevo": antes era solo un icono de lapiz sin mas contexto, y
         * quien no sabia que "Corregir" cumple ese papel sentia que la
         * pantalla no ofrecia ninguna salida despues de presentar.
         */
        botones += accBtn({ permiso: 'ica.corregir', tipo: 'warning', icono: 'fa-pencil', texto: 'Corregir',
                            title: '¿Necesitas declarar de nuevo este período? Esta es la forma correcta: genera una corrección enlazada a la presentada.',
                            onclick: objJs + '.corregirDeclaracion(' + d.dec_Id + ')' });

        // Aqui vivia un boton de "Código de barras" que abria liquidacion.php.
        // Se quita: el codigo de barras NO es un documento aparte, va impreso
        // dentro de la propia declaracion que ya se descarga con el boton de
        // Descargar. Tener un boton propio para el sugeria que era otra cosa y
        // solo sumaba ruido a la fila.

        // Pago PSE (PlacetoPay/Avalpaycenter): crearSesion.php crea la sesion
        // y redirige directo al banco -por eso target="_blank", en vez de
        // onclick+JS-, igual que "Descargar" y el boton de arriba.
        // Ahora mismo contra el ambiente de PRUEBAS del banco (ver
        // config.municipio.php, PLACETOPAY_BASEURL); cambiar a producción
        // solo cuando el banco entregue las credenciales reales.
        // Solo en PRESENTADAS: pagar un borrador dejaba la declaracion
        // "pagada pero sin presentar", un estado que despues no se puede
        // corregir. crearSesion.php lo valida tambien del lado del servidor;
        // esto solo evita ofrecer un boton que va a rebotar.
        // Y solo si la entidad tiene convenio de recaudo configurado
        // (pago_en_linea, migracion 023). Sin el, el boton solo puede llevar
        // a un mensaje de "no disponible"; se prefiere no ofrecerlo. El
        // servidor lo vuelve a comprobar: esta URL se puede llamar a mano.
        // Y solo con algo que pagar, como el recibo de abajo: con la casilla 38
        // en $0 el boton solo llevaba a "Sin valor a pagar" (revision 2026-09-28;
        // ya era asi en retencion y autorretencion).
        if (clave === 'presentada' && Number(d.pago_en_linea) === 1 && Number(d.dec_ValorConcepto20) > 0) {
            // Va al RESUMEN de pago (pagar.php), no directo a crear la sesion:
            // la certificacion WC exige mostrar el monto y aceptar la politica
            // de datos antes de redirigir al banco (items 4 y 12.1).
            // El texto lo da el servidor según la pasarela de la entidad (042):
            // "Pagar PSE" con AvalPay, "Pagar en línea" con Wompi (no es solo PSE).
            var textoPago = d.pago_en_linea_texto || 'Pagar PSE';
            botones += accBtn({ permiso: 'ica.pagar', tipo: 'danger', icono: 'fa-money', texto: textoPago, title: textoPago,
                                href: '../extensiones/pse/pagar.php?modulo=ica&id=' + d.dec_Id, target: '_blank' });
        }

        // Recibo de pago para el banco (extensiones/reciboPago.php): el mismo de
        // retención y autorretención. Solo presentada, sin pagar y con valor; el
        // total a pagar del ICA (renglón 38) es dec_ValorConcepto20. Vencida,
        // es la única forma de pagar en ventanilla: la declaración ya no trae
        // código de barras.
        if (clave === 'presentada' && Number(d.dec_ValorConcepto20) > 0) {
            botones += accBtn({ permiso: 'ica.pagar', tipo: 'info', icono: 'fa-barcode', texto: 'Recibo de pago',
                                title: 'Descargar el recibo de pago para el banco',
                                href: '../extensiones/reciboPago.php?modulo=ICA&id=' + d.dec_Id, target: '_blank' });
        }

        // Pago hecho fuera de la plataforma (transferencia, consignación...),
        // con su soporte (migración 044). Solo la Alcaldía con permiso.
        if (clave === 'presentada' && typeof RegistroManual !== 'undefined' && Number(d.dec_ValorConcepto20) > 0) {
            botones += accBtn({ permiso: 'alcaldia.pagos.manual', tipo: 'success', icono: 'fa-check-square-o', texto: 'Pago manual',
                                title: 'Registrar un pago hecho por transferencia, consignación u otro medio, con su soporte',
                                onclick: 'RegistroManual.pago(&quot;ica&quot;, ' + d.dec_Id + ', ' + numeroJs + ', '
                                       + Number(d.dec_ValorConcepto20 || 0) + ')' });
        }

        // Deshacer un registro manual hecho por error (solo pagos MANUAL).
        if (typeof RegistroManual !== 'undefined' && d.registro_manual && d.registro_manual.pagoManual
            && d.dec_RutaPago === 'MANUAL') {
            botones += accBtn({ permiso: 'alcaldia.registro.anular', tipo: 'danger', icono: 'fa-undo', texto: 'Anular',
                                title: 'Anular el registro manual (pide un motivo y queda constancia)',
                                onclick: 'RegistroManual.anular(&quot;ica&quot;, ' + d.dec_Id + ', ' + numeroJs + ')' });
        }

        return envolverAcciones(botones + accionesSoportes(d.registro_manual, 'ica.ver'));
    }

    /**
     * Los PDF que registró la Alcaldía con la declaración (migración 044): el
     * original en papel y el soporte de pago. Se entregan por soporte.php, que
     * comprueba sesión, dueño y permiso.
     */
    function accionesSoportes(rm, permisoVer) {
        if (!rm || typeof RegistroManual === 'undefined') { return ''; }
        return RegistroManual.soportes(rm).map(function (s) {
            return accBtn({ permiso: permisoVer, tipo: 'primary', icono: s.original ? 'fa-file-pdf-o' : 'fa-paperclip',
                            texto: s.texto, title: atributo(s.titulo), href: s.url, target: '_blank' });
        }).join('');
    }

    /**
     * Estado real de una declaracion, derivado de los datos que ya trae
     * el listado. Antes el usuario tenia que deducirlo mirando que
     * botones le aparecian.
     *
     * Los 4 estados salen de columnas reales:
     *   borrador   -> no existe registro en firmas_declaraciones
     *   firmada    -> firmada, pero dec_Estado aun no es 2
     *   presentada -> dec_Estado = 2
     *   pagada     -> dec_Pagado = 1
     *
     * No se incluye "vencida" a proposito: haria falta la fecha maxima de
     * presentacion del calendario tributario y hoy el sistema no la guarda
     * en ninguna columna, asi que habria que inventarla.
     */
    var ESTADOS = {
        borrador:      { texto: 'Borrador',          clase: 'est-borrador',   paso: 2 },
        pendienteCont: { texto: 'Falta contador',    clase: 'est-firmada',    paso: 3 },
        firmada:       { texto: 'Firmada',           clase: 'est-firmada',    paso: 4 },
        presentada:    { texto: 'Presentada',        clase: 'est-presentada', paso: 5 },
        pagada:        { texto: 'Pagada',            clase: 'est-pagada',     paso: 6 }
    };

    /*
     * Presentar exige la firma del declarante y, cuando el contribuyente
     * tiene registrado un contador o revisor fiscal, tambien la de esa
     * persona (ver _requiereContador en class.declaracionesICA.php, que es
     * quien calcula d.requiere_contador -la regla vive una sola vez, en el
     * backend, para que este archivo no se desactualice si cambia).
     *
     * Cuando no aplica, "pendienteCont" se salta: firmar el declarante deja
     * la declaracion directo en "firmada".
     */
    function claveEstado(d) {
        if (!d) { return 'borrador'; }

        /*
         * "Pagada" exige ADEMAS estar presentada.
         *
         * dec_Pagado por si solo no alcanza: una declaracion marcada como
         * pagada pero sin presentar es un estado imposible, y pintarla
         * "Pagada" mentia al usuario -le mostraba un tramite cerrado que
         * despues no podia corregir, porque Corregir exige dec_Estado = 2-.
         * Ese es justo el caso que reporto el cliente con las declaraciones
         * 217 y 218.
         *
         * El origen se cerro en class.recaudo.php (esos pagos ya no se
         * aplican, se reportan), pero la pantalla no debe volver a mostrar
         * como pagado algo que el resto del sistema no trata como pagado.
         */
        if (Number(d.dec_Pagado) === 1 && Number(d.dec_Estado) === 2) { return 'pagada'; }
        if (Number(d.dec_Estado) === 2)  { return 'presentada'; }
        if (Number(d.is_signed) === 1) {
            var faltaContador = Number(d.requiere_contador) === 1 && Number(d.is_signed_contador) !== 1;
            return faltaContador ? 'pendienteCont' : 'firmada';
        }
        return 'borrador';
    }

    function estado(d) {
        var clave = claveEstado(d);
        var info  = ESTADOS[clave];
        return { clave: clave, texto: info.texto, clase: info.clase, paso: info.paso };
    }

    /** Distintivo de estado. El color nunca va solo: lleva punto y texto. */
    function chipEstado(d) {
        var e = estado(d);
        return '<span class="chip-estado ' + e.clase + '">' + e.texto + '</span>';
    }

    /**
     * Columna "Tipo de declaración" (cliente, 2026-09-29): Inicial o Corrección
     * (de cuál), y en la que ya fue corregida, por cuál. dec_DeclaracionCorrige
     * guarda el NUMERO de la corregida; "todas" es la lista del contribuyente,
     * para encontrar quién corrige a esta.
     */
    function tipoDeclaracion(d, todas) {
        var numero  = String(d.dec_NumeroDeclaracion || d.dec_Id);
        var corrige = d.dec_DeclaracionCorrige ? String(d.dec_DeclaracionCorrige) : '';
        var html = corrige
            ? '<span class="chip-estado est-firmada">Corrección</span>'
                + '<div style="font-size:11px;color:#6B7280;">de la N° ' + corrige + '</div>'
            : '<span class="chip-estado est-borrador">Inicial</span>';
        var correcciones = (todas || []).filter(function (x) {
            return x.dec_DeclaracionCorrige && String(x.dec_DeclaracionCorrige) === numero;
        });
        if (correcciones.length) {
            var ultima = correcciones[correcciones.length - 1];
            var suNumero = ultima.dec_NumeroDeclaracion || ultima.dec_Id;
            // "Que diga que está siendo corregida": en borrador todavía no la
            // reemplaza; ya presentada, sí.
            html += estado(ultima).paso >= 5
                ? '<div style="font-size:11px;color:#B45309;">Corregida por la N° ' + suNumero + '</div>'
                : '<div style="font-size:11px;color:#B45309;">En corrección: N° ' + suNumero + '</div>';
        }
        html += notaRegistroManual(d.registro_manual);
        return html;
    }

    /** "Presentada en papel N° X" / "Pago manual: medio" (migración 044, core/registroManual.js). */
    function notaRegistroManual(rm) {
        return (rm && typeof RegistroManual !== 'undefined') ? RegistroManual.nota(rm) : '';
    }

    /**
     * Resumen "Declaración No. X — Año gravable YYYY" + chip de estado, para
     * la barra unica de icaWebPresentar (antes solo decia "Declaración de
     * este contribuyente" sin identificar de cual declaracion se trataba).
     * Hay declaraciones antiguas con dec_NumeroDeclaracion en NULL: se cae a
     * dec_Id, igual que ya hace icaWebConsultar.js.
     */
    function resumenDeclaracion(d) {
        var numero = d.dec_NumeroDeclaracion || d.dec_Id;
        return '<span>Declaración No. ' + numero + ' &mdash; Año gravable ' + d.dec_AnioDeclaracion + '</span> ' + chipEstado(d);
    }

    /**
     * Barra de progreso del tramite. Responde "que hice, en que voy y que
     * me falta" sin que la persona tenga que preguntar.
     */
    var PASOS = ['Crear', 'Liquidar', 'Firmar', 'Presentar', 'Pagar'];

    function stepperHtml(d) {
        var actual = estado(d).paso;
        var html = '<div class="stepper-tramite" role="list" aria-label="Progreso de la declaración">';
        for (var i = 0; i < PASOS.length; i++) {
            var n = i + 1;
            var clase = n < actual ? 'done' : (n === actual ? 'now' : 'todo');
            var sr = n < actual ? 'completado' : (n === actual ? 'paso actual' : 'pendiente');
            html += '<div class="paso ' + clase + '" role="listitem">' +
                        '<span class="paso-n">' + n + '</span>' +
                        '<span class="paso-t">' + PASOS[i] + '</span>' +
                        '<span class="sr-only"> (' + sr + ')</span>' +
                    '</div>';
        }
        return html + '</div>';
    }

    /**
     * Fecha de SQL Server -> texto dd/mm/aaaa para mostrar.
     *
     * El driver sqlsrv no devuelve las fechas como cadena sino como objeto
     * ({date, timezone_type, timezone}), asi que concatenarlas directamente
     * imprimia "[object Object]" -es lo que salia en la columna Fecha Pago de
     * "Consultar Declaraciones" en cuanto una declaracion estaba pagada-.
     *
     * 1900-01-01 es el centinela de "nunca se lleno" de esta base y se trata
     * como vacio, igual que en el resto del sistema.
     */
    function fechaTexto(valor, siVacio) {
        var vacio = (siVacio === undefined) ? 'No aplica' : siVacio;
        if (!valor) { return vacio; }

        var texto = (typeof valor === 'string') ? valor : (valor.date || '');
        if (!texto) { return vacio; }

        var soloFecha = texto.substring(0, 10);          // AAAA-MM-DD
        if (soloFecha === '1900-01-01') { return vacio; }

        var p = soloFecha.split('-');
        return (p.length === 3) ? (p[2] + '/' + p[1] + '/' + p[0]) : soloFecha;
    }

    /**
     * Los años del filtro de "Consultar Declaraciones" y el que se elige al
     * abrir.
     *
     * Salian de TODAS las declaraciones, borradores incluidos, aunque esa
     * pantalla solo muestra las presentadas y pagadas (paso >= 5). Con un
     * borrador del año en curso, la pantalla abria filtrada en ese año y decia
     * "Ninguna declaración coincide con el filtro" aunque la presentada del año
     * anterior estuviera ahi -en enero le pasaria a todo el que empieza la
     * nueva- (revision 2026-09-28). Ahora solo cuentan los años con algo que
     * mostrar; se abre en el actual si tiene, y si no en el mas reciente.
     *
     * @param {Array}  lista      filas del listado (funcion 8)
     * @param {number} anioActual el año de hoy
     * @return {{anios: string[], porDefecto: string}}
     */
    function aniosConsulta(lista, anioActual) {
        var anios = [];
        (lista || []).forEach(function (d) {
            if (estado(d).paso < 5) { return; }
            var a = String(d.dec_AnioDeclaracion);
            if (anios.indexOf(a) === -1) { anios.push(a); }
        });
        anios.sort(function (a, b) { return Number(b) - Number(a); });

        var actual = String(anioActual);
        return { anios: anios, porDefecto: anios.indexOf(actual) !== -1 ? actual : (anios[0] || '') };
    }

    return {
        nombreMes: nombreMes,
        htmlAcciones: htmlAcciones,
        permitido: permitido,
        mostrarMotivo: mostrarMotivo,
        estado: estado,
        chipEstado: chipEstado,
        tipoDeclaracion: tipoDeclaracion,
        resumenDeclaracion: resumenDeclaracion,
        stepperHtml: stepperHtml,
        fechaTexto: fechaTexto,
        aniosConsulta: aniosConsulta
    };

})();


/**
 * Flujo de FIRMA DIGITAL (OTP por correo), compartido por
 * icaWebPresentar.js e icaWebConsultar.js.
 *
 * Antes cada pantalla tenia su propia copia del flujo y la experiencia
 * tenia varios huecos: no se decia a que correo llegaba el codigo, no
 * habia forma de reenviarlo, no se veia cuanto faltaba para que
 * venciera, el campo aceptaba letras, y si el codigo se validaba pero
 * la firma fallaba el OTP ya quedaba consumido sin explicarselo al
 * usuario.
 */
var FirmaOTP = (function () {

    var API = '../microservicios/firmas/api.php';
    var VIGENCIA_SEG = 600;   // el backend expira el codigo a los 10 min
    var REENVIO_SEG  = 60;    // espera minima antes de permitir reenviar

    var _timerVigencia = null;
    var _timerReenvio  = null;
    var _onFirmado     = null;
    var _idEstablecimiento = null;
    // Rol de quien esta firmando en este momento: 'declarante' o 'contador'.
    // Decide a que correo viaja el codigo y con que rol queda la firma.
    var _rol           = 'declarante';
    var _modo = 'declaracion';   // 'declaracion' | 'rit'

    function _mostrarError(msg) {
        $('#otpError').text(msg).show();
    }

    function _limpiarError() {
        $('#otpError').hide().text('');
    }

    function _mmss(seg) {
        var m = Math.floor(seg / 60);
        var s = seg % 60;
        return m + ':' + (s < 10 ? '0' + s : s);
    }

    function _pararTimers() {
        if (_timerVigencia) { clearInterval(_timerVigencia); _timerVigencia = null; }
        if (_timerReenvio)  { clearInterval(_timerReenvio);  _timerReenvio  = null; }
    }

    function _arrancarVigencia() {
        var restante = VIGENCIA_SEG;
        $('#otpVigencia').css('color', '#6B7280').text('El código vence en ' + _mmss(restante));

        if (_timerVigencia) { clearInterval(_timerVigencia); }
        _timerVigencia = setInterval(function () {
            restante--;
            if (restante <= 0) {
                clearInterval(_timerVigencia);
                _timerVigencia = null;
                $('#otpVigencia').css('color', '#DC2626').text('El código venció. Solicita uno nuevo.');
                $('#btnValidarOTP').prop('disabled', true);
                return;
            }
            $('#otpVigencia')
                .css('color', restante <= 60 ? '#DC2626' : '#6B7280')
                .text('El código vence en ' + _mmss(restante));
        }, 1000);
    }

    function _arrancarCooldownReenvio() {
        var restante = REENVIO_SEG;
        var $btn = $('#btnReenviarOTP');
        $btn.prop('disabled', true).html('<i class="fa fa-refresh"></i> Reenviar código (' + restante + 's)');

        if (_timerReenvio) { clearInterval(_timerReenvio); }
        _timerReenvio = setInterval(function () {
            restante--;
            if (restante <= 0) {
                clearInterval(_timerReenvio);
                _timerReenvio = null;
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> Reenviar código');
                return;
            }
            $btn.html('<i class="fa fa-refresh"></i> Reenviar código (' + restante + 's)');
        }, 1000);
    }

    /** Pide un codigo nuevo al backend. */
    function _solicitarCodigo(esReenvio) {

        if (!esReenvio) {
            swal({
                title: 'Generando código',
                text: 'Por favor espere...',
                allowOutsideClick: false,
                onOpen: function () { swal.showLoading(); }
            });
        } else {
            $('#btnReenviarOTP').prop('disabled', true);
        }

        $.ajax({
            url: API,
            type: 'POST',
            dataType: 'json',
            data: {
                funcion: 1,
                // Ya NO se manda id_usuario. La constante ID_USUARIO solo la
                // emiten icaWebPresentar e icaWebConsultar; en la pantalla del
                // RIT no existe, y referirse a ella lanzaba ReferenceError al
                // armar este objeto -antes de que saliera la peticion-, dejando
                // el swal "Generando codigo" girando para siempre.
                // El servidor lo toma de la sesion.
                id_establecimiento: 0,
                // El rol decide a que correo viaja el codigo: al del usuario
                // (declarante) o al del contador/revisor del contribuyente.
                rol: _rol,
                numero_declaracion: $('#otpIdDeclaracion').val(),
                // Contribuyente activo, para la firma del RIT cuando la Alcaldía
                // gestiona a otro. En declaraciones el servidor lo ignora (toma
                // el dueño de la declaración). El propio contribuyente manda el
                // suyo y el servidor lo valida.
                id_contribuyente: localStorage.getItem('id_Contribuyente')
            },
            success: function (resp) {

                if (resp.ok != 1) {
                    if (!esReenvio) { swal('Error', resp.mensaje, 'error'); }
                    else { _mostrarError(resp.mensaje || 'No se pudo reenviar el código.'); }
                    $('#btnReenviarOTP').prop('disabled', false);
                    return;
                }

                // El backend responde "Código enviado a <correo>": se extrae
                // el correo para que el usuario sepa donde buscarlo.
                var correo = (resp.mensaje || '').replace(/^.*?enviado a\s*/i, '').trim();
                if (correo) { $('#otpDestino').text(correo); }

                $('#otpCodigo').val('');
                _limpiarError();
                $('#btnValidarOTP').prop('disabled', false);
                _arrancarVigencia();
                _arrancarCooldownReenvio();

                if (!esReenvio) {
                    swal.close();
                    $('#modal-FirmaDigital').modal('show');
                    setTimeout(function () { $('#otpCodigo').focus(); }, 400);
                }
            }
        });
    }

    /**
     * Abre el modal de firma para una declaracion.
     * @param {number}   decId      Declaracion a firmar.
     * @param {number}   idEst      Establecimiento (para refrescar al terminar).
     * @param {function} onFirmado  Callback tras firmar con exito.
     * @param {string}   [rol]      'declarante' (por defecto) o 'contador'.
     */
    function abrir(decId, idEst, onFirmado, rol) {
        _onFirmado = onFirmado || null;
        _idEstablecimiento = idEst || null;
        _modo = 'declaracion';
        _rol = rol === 'contador' ? 'contador' : 'declarante';

        $('#otpIdDeclaracion').val(decId);
        $('#otpDestino').text(_rol === 'contador'
            ? 'el correo del contador / revisor fiscal'
            : 'su correo electrónico');
        _limpiarError();
        _solicitarCodigo(false);
    }

    /**
     * Abre el MISMO modal para firmar el RIT.
     *
     * El cliente pidio expresamente que la firma del RIT se vea igual que la
     * de las declaraciones, no en una ventana distinta. Cambia poco: el
     * codigo se pide con rol 'rit' -que tiene su propio cajon en
     * codigos_verificacion, para que un codigo de declaracion no sirva para
     * firmar el RIT- y se registra con la funcion 9 en vez de la 7.
     *
     * @param {function} onFirmado  Callback tras firmar con exito.
     */
    function abrirRit(onFirmado) {
        _onFirmado = onFirmado || null;
        _idEstablecimiento = null;
        _modo = 'rit';
        _rol = 'rit';

        $('#otpIdDeclaracion').val('');
        $('#otpDestino').text('su correo electrónico');
        _limpiarError();
        _solicitarCodigo(false);
    }

    function _validarYFirmar() {

        var codigo = ($('#otpCodigo').val() || '').trim();
        var decId  = $('#otpIdDeclaracion').val();

        if (!/^\d{6}$/.test(codigo)) {
            _mostrarError('Ingresa los 6 dígitos del código.');
            $('#otpCodigo').focus();
            return;
        }

        _limpiarError();
        $('#btnValidarOTP').prop('disabled', true);

        swal({
            title: 'Validando firma',
            text: 'Por favor espere...',
            allowOutsideClick: false,
            onOpen: function () { swal.showLoading(); }
        });

        /*
         * UNA sola llamada.
         *
         * Antes esto eran dos: la funcion 2 verificaba el codigo y la 7
         * registraba la firma. La 7 no volvia a mirar el codigo, asi que
         * llamarla directamente dejaba una firma registrada sin haber
         * recibido ningun correo. Desde el 2026-08-19 la 7 valida y consume
         * el codigo ella misma, de modo que aqui solo hay que mandarselo.
         *
         * Ya no se manda id_usuario: el firmante lo toma el servidor de la
         * sesion, porque un id en el POST no prueba quien es quien.
         */
        $.ajax({
            url: API,
            type: 'POST',
            dataType: 'json',
            // funcion 9 = firmar el RIT, funcion 7 = firmar una declaracion.
            // id_contribuyente: para el RIT que la Alcaldía firma por otro; el
            // servidor lo valida (rol 1/2 cualquiera, el resto solo el suyo).
            data: (_modo === 'rit')
                ? { funcion: 9, codigo: codigo, id_contribuyente: localStorage.getItem('id_Contribuyente') }
                : { funcion: 7, codigo: codigo, id_declaracion: decId, rol: _rol },
            success: function (respFirma) {

                swal.close();

                if (respFirma.ok != 1) {
                    $('#btnValidarOTP').prop('disabled', false);
                    _mostrarError(respFirma.mensaje || 'Código inválido o expirado.');
                    $('#otpCodigo').val('').focus();
                    return;
                }

                _pararTimers();
                $('#modal-FirmaDigital').modal('hide');

                var titulo = _modo === 'rit' ? 'RIT firmado' : 'Firmada';
                var texto  = _modo === 'rit'
                    ? 'El RIT ha sido firmado digitalmente.'
                    : 'La declaración ha sido firmada digitalmente.';

                swal(titulo, texto, 'success').then(function () {
                    if (typeof _onFirmado === 'function') {
                        _onFirmado(_idEstablecimiento);
                    }
                });
            },
            error: function () {
                swal.close();
                $('#btnValidarOTP').prop('disabled', false);
                _mostrarError('No se pudo conectar para firmar. Intenta de nuevo.');
            }
        });
    }

    if (typeof $ !== 'undefined') {
        $(function () {
            // Solo digitos en el campo del codigo.
            $(document).on('input', '#otpCodigo', function () {
                var limpio = this.value.replace(/\D/g, '').slice(0, 6);
                if (this.value !== limpio) { this.value = limpio; }
                if (limpio.length === 6) { _limpiarError(); }
            });

            // Enter dentro del campo = validar.
            $(document).on('keypress', '#otpCodigo', function (e) {
                if (e.which === 13) { e.preventDefault(); _validarYFirmar(); }
            });

            $(document).on('click', '#btnValidarOTP', _validarYFirmar);

            $(document).on('click', '#btnReenviarOTP', function () {
                _solicitarCodigo(true);
            });

            // Al cerrar el modal se detienen los contadores.
            $(document).on('hidden.bs.modal', '#modal-FirmaDigital', function () {
                _pararTimers();
            });
        });
    }

    return { abrir: abrir, abrirRit: abrirRit };

})();

/**
 * Abre el formulario de liquidacion (el mismo modal #modal-CrearDeclaracion
 * que usa "Crear Declaración") pero PRE-CARGADO con una declaracion ya
 * existente, para editarla.
 *
 * Antes "Editar" (el lapiz sobre una declaracion en borrador) era un stub
 * que solo mostraba "disponible próximamente" -en Presentar Declaración Y
 * en Consultar Declaraciones, las dos pantallas tenian exactamente el
 * mismo aviso-. No existia ninguna forma de modificar una declaracion ya
 * creada. Vive en este modulo compartido (no en cada pantalla por
 * separado) porque ambas paginas tienen el mismo modal y los mismos ids
 * de campo.
 */
/**
 * Deja el formulario de la declaracion en blanco.
 *
 * EL MODAL NO SE LIMPIABA ENTRE UNA DECLARACION Y OTRA
 *
 * Es el mismo <div> reutilizado: se rellena al abrir y se oculta al cerrar,
 * pero nadie lo vaciaba. Medido: el camino de CREAR no escribia NINGUNO de los
 * 29 campos de cifras -solo numero, año, periodo, fecha, hora y opcion de uso-,
 * asi que abrir una declaracion, cerrarla y pulsar "Crear" dejaba en pantalla
 * los ingresos, las retenciones y la sancion de la anterior.
 *
 * Y no era solo visual: "Guardar" manda lo que hay en pantalla, asi que esas
 * cifras se escribian en la declaracion nueva. Un contribuyente podia acabar
 * con los ingresos de otro.
 *
 * El camino de EDITAR pinta 26 de los 29, asi que el arrastre era parcial pero
 * existia igual -capacidad instalada, sobretasa de seguridad y valor del
 * impuesto no los pintaba nadie-.
 *
 * Se llama ANTES de rellenar, en los dos caminos. Poner los valores con .val()
 * no dispara ningun evento, asi que limpiar no guarda nada.
 */
function limpiarFormularioDeclaracion() {
    $('[data-campo]').val('0');

    $('#tbodyActividades').empty();
    $('#totalBaseGravable, #totalImpuesto').val('');

    $('#numDeclaracion, #anioDeclaracion, #periodoDeclaracion').val('');
    $('#fechaDeclaracion, #horaDeclaracion').val('');

    // La sancion vuelve a "Ninguna", que es como nace el formulario.
    $('#chkSinSancion').prop('checked', true);
    $('#txtOtraSancion').val('');
    $('#inputOtraSancion').hide();

    // La opcion de uso vuelve a "inicial": abrir una correccion y despues
    // crear una declaracion nueva dejaba a la vista "Declaración que corrige".
    FormularioDeclaracion.pintarOpcionUso({});

    // Los botones que dependen de tener una declaracion abierta.
    $('#btnDescargarPDF').prop('disabled', true);
}

/**
 * Lo que el formulario de la declaracion lee y pinta igual en las dos
 * pantallas (Presentar y Consultar comparten el modal y sus ids).
 *
 * UNA SOLA LECTURA DEL FORMULARIO. Las actividades y los ingresos se armaban en
 * cada pantalla por su cuenta, y la de Consultar se quedo sin mandarlos al
 * recalcular un renglon (funcion 7): el servidor liquidaba con las actividades
 * GUARDADAS y repintaba los renglones 20 a 38 con cifras viejas encima de lo que
 * acababa de mostrar "Liquidar". Es el "pongo un dato y me cambia toda la
 * declaracion" del 2026-09-01, que se arreglo en Presentar y quedo vivo en
 * Consultar, donde se edita cada correccion recien creada (revision
 * 2026-09-28). Las cifras pasan por core/numeros.js, como en todo el sistema.
 */
var FormularioDeclaracion = {

    /** Las actividades tal como estan en la tabla del formulario. */
    actividades: function () {
        var idDeclaracion = $('#numDeclaracion').val();
        var lista = [];
        $('#tbodyActividades tr').each(function () {
            lista.push({
                dia_IdDeclaracion: idDeclaracion,
                dia_IdActividad:   $(this).find('.actividad-id').val(),
                dia_BaseGravable:  NumerosCOP.aCifra($(this).find('.base-gravable').val()),
                dia_Tarifa:        parseFloat($(this).find('.tarifa').val()) || 0,
                dia_ValorImpuesto: NumerosCOP.aCifra($(this).find('.impuesto').val())
            });
        });
        return lista;
    },

    /**
     * Los renglones de ingresos y de energia tal como estan en el formulario.
     * Un campo que NO esta en la pantalla no viaja (no se escribe un 0 encima
     * del dato guardado); uno que esta vacio si viaja, como 0. Ver la nota de
     * totalesDelFormulario en core/icaWebPresentar.js.
     */
    totales: function () {
        var campo = function (nombre) {
            var $e = $('[data-campo="' + nombre + '"]');
            if ($e.length === 0) {
                console.error('FormularioDeclaracion.totales: no existe data-campo="' + nombre + '"');
                return undefined;
            }
            return NumerosCOP.aCifra($e.val());
        };

        var totales = {
            dec_TotalIngresos:            campo('ingresos_total_pais'),
            dec_IngresosFueraMunicipio:   campo('menos_fuera_municipio'),
            dec_IngresosDevoluciones:     campo('devoluciones'),
            dec_IngresosExportaciones:    campo('exportaciones'),
            dec_IngresosVentas:           campo('venta_activos'),
            dec_IngresosActividades:      campo('actividades_excluidas'),
            dec_IngresosOtrasActividades: campo('otras_exentas'),
            dec_BaseGravable:             campo('ingresos_gravables'),
            dec_CapacidadInstalada:       campo('capacidad_instalada'),
            dec_ValorImpuesto:            campo('valor_impuesto')
        };

        Object.keys(totales).forEach(function (k) {
            if (totales[k] === undefined) { delete totales[k]; }
        });

        // Tipo de sancion del renglon 31 (migracion 038): antes no se guardaba y
        // al reabrir volvia a "Ninguna". Solo si la pantalla tiene las opciones.
        var $tipo = $("input[name='tipoSancion']");
        if ($tipo.length) {
            totales.dec_TipoSancion = String($tipo.filter(':checked').val() || '');
            totales.dec_OtraSancion = totales.dec_TipoSancion === 'otra' ? String($('#txtOtraSancion').val() || '').trim() : '';
        }

        return totales;
    },

    /**
     * Marca el tipo de sancion guardado (migracion 038) al abrir una
     * declaracion. Sin dato -o en una base sin la 038- queda "Ninguna".
     */
    pintarTipoSancion: function (d) {
        var tipo = String((d && d.dec_TipoSancion) || '');
        var $opcion = $("input[name='tipoSancion'][value='" + tipo.replace(/[^a-z]/g, '') + "']");
        if (!tipo || !$opcion.length) { $opcion = $('#chkSinSancion'); }
        $opcion.prop('checked', true);
        if (tipo === 'otra') {
            $('#txtOtraSancion').val(String(d.dec_OtraSancion || ''));
            $('#inputOtraSancion').show();
        } else {
            $('#txtOtraSancion').val('');
            $('#inputOtraSancion').hide();
        }
    },

    /**
     * Fecha y hora de la declaracion en sus casillas (solo lectura).
     *
     * sqlsrv entrega las fechas como objeto ({date: 'AAAA-MM-DD hh:mm:ss…'}),
     * no como texto. Al CREAR se metia ese objeto tal cual en el input y la
     * fecha y la hora salian vacias; al editar ya se hacia bien. Ahora los dos
     * caminos pasan por aqui.
     */
    pintarFechaHora: function (d) {
        var crudo = function (v) { return (v && typeof v === 'object') ? String(v.date || '') : String(v || ''); };
        var fecha = crudo(d.dec_FechaDeclaracion).substring(0, 10);
        var hora  = crudo(d.dec_HoraDeclaracion).match(/\d{2}:\d{2}(:\d{2})?/);
        $('#fechaDeclaracion').val(/^\d{4}-\d{2}-\d{2}$/.test(fecha) && fecha !== '1900-01-01' ? fecha : '');
        $('#horaDeclaracion').val(hora ? hora[0] : '');
    },

    /**
     * Opcion de uso: "Corrección" y el numero que corrige si la declaracion
     * corrige a otra (dec_DeclaracionCorrige); si no, "Declaración Inicial".
     *
     * La correccion copiaba la opcion de la original y se mostraba como
     * "Declaración Inicial", con la casilla "Declaración que corrige" oculta y
     * vacia (revision 2026-09-28). Las dos casillas son de solo lectura: lo que
     * hace correccion a una declaracion es el enlace, que pone "Corregir".
     */
    pintarOpcionUso: function (d) {
        var corrige = (d && d.dec_DeclaracionCorrige) ? String(d.dec_DeclaracionCorrige) : '';

        $('#opcionUso').val(corrige ? '3' : '1');

        var $sel = $('#declaracionCorrige').empty();
        if (corrige) {
            $sel.append($('<option>').val(corrige).text('N° ' + corrige)).val(corrige);
        } else {
            $sel.append($('<option>').val('').text('Seleccione…'));
        }
        $('#grupoDeclaracionCorrige').toggle(!!corrige);
    },

    /**
     * Una fila de la tabla de actividades, la misma para las GUARDADAS y para
     * las que se cargan del contribuyente con base 0. El codigo y el nombre
     * vienen del catalogo y se escapan; nota es HTML armado aqui.
     *
     * @param {{id, codigo, nombre, nota, base, tarifa, impuesto}} a
     */
    filaActividad: function (a) {
        var html = function (t) {
            return String(t === null || t === undefined ? '' : t)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        };
        return '<tr>' +
                   '<td>' + html(a.codigo) + ' - ' + html(a.nombre) + (a.nota || '') +
                       '<input type="hidden" class="actividad-id" value="' + html(a.id) + '"></td>' +
                   '<td><input type="text" class="form-control base-gravable" value="' + html(a.base) + '"></td>' +
                   '<td><input type="text" class="form-control tarifa" value="' + html(a.tarifa) + '" readonly></td>' +
                   '<td><input type="text" class="form-control impuesto" readonly value="' + html(a.impuesto) + '"></td>' +
               '</tr>';
    },

    /**
     * Pone en la tabla las actividades del CONTRIBUYENTE con base 0 (funcion
     * 12: la misma lista con que nace la declaracion en "Crear Declaración").
     *
     * EL BORRADOR SIN ACTIVIDADES ERA UN CALLEJON SIN SALIDA (segunda revision
     * 2026-09-28). Crear no guarda actividades: se guardan con "Guardar". Un
     * borrador que se cerro sin guardar -hay decenas-, la correccion de una
     * declaracion que no tenia ninguna, o una firmada sin actividades a la que
     * "Editar" le quita las firmas, se abrian con la tabla VACIA: sin filas no
     * se guarda ("No hay actividades para guardar"), y sin guardar no se firma
     * ni se presenta. El aviso decia "ábrala, liquídela y guárdela", pero al
     * abrirla no habia con que. Vive aqui, y no en icaWebPresentar.js
     * (cargarActividadesContribuyente), porque Consultar -donde se editan las
     * correcciones- no tiene esa funcion.
     *
     * @param {number|string} idContribuyente dec_IdContribuyente de la declaracion
     * @param {string} numero el #numDeclaracion abierto al pedirlas: si la
     *        respuesta llega con otra declaracion ya abierta, no se pinta encima.
     */
    cargarActividadesContribuyente: function (idContribuyente, numero) {
        $.ajax({
            url: '../business/controller/class.declaracionesICA.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 12, dec_IdContribuyente: idContribuyente },
            success: function (arr) {
                if (String($('#numDeclaracion').val()) !== String(numero)) { return; }

                var $tbody = $('#tbodyActividades').empty();

                // Sin actividades en el RIT no hay con que declarar: se dice,
                // porque es lo que hay que registrar primero.
                if (!arr || arr.ok != 1) {
                    swal({
                        type: 'warning',
                        title: 'Sin actividades',
                        text: (arr && arr.mensaje) || 'El contribuyente no tiene actividades económicas registradas.'
                    });
                    return;
                }

                (arr.datos || []).forEach(function (a) {
                    // En cuantos locales del contribuyente aplica (informativo),
                    // como al crear.
                    var locales = Number(a.n_establecimientos) > 1
                        ? ' <small class="text-muted">(' + Number(a.n_establecimientos) + ' establecimientos)</small>'
                        : '';
                    $tbody.append(FormularioDeclaracion.filaActividad({
                        id: a.ace_IdCodigoActividad, codigo: a.acc_Codigo, nombre: a.acc_Nombre, nota: locales,
                        base: '0', tarifa: a.acc_Tarifa, impuesto: '0'
                    }));
                });

                if (typeof establecimientos !== 'undefined' && establecimientos.calcularTotalesActividades) {
                    establecimientos.calcularTotalesActividades();
                }
            },
            error: function (xhr) {
                console.error('Actividades del contribuyente:', xhr && xhr.responseText);
                swal({
                    type: 'error',
                    title: 'No se pudieron cargar las actividades',
                    text: 'Cierre la declaración y vuelva a abrirla; si persiste, avise a soporte.'
                });
            }
        });
    }
};

/**
 * Explica por que NO se pudo crear la declaracion, y ofrece el camino.
 *
 * LAS TRES PANTALLAS NO DECIAN LO MISMO
 *
 * Presentar Declaracion mostraba el motivo real que manda el servidor;
 * Consultar Declaraciones y el RIT mostraban un fijo "No se pudo crear la
 * declaracion" y TIRABAN el mensaje. Es el mismo boton y el mismo backend, asi
 * que segun por donde entrara, el mismo usuario recibia una explicacion o un
 * error mudo. Aqui se unifica.
 *
 * Llego a ofrecer ademas un boton para ir a Corregir cuando el rechazo era
 * "ya fue presentada". Ese rechazo ya no existe -el cliente pidio que crear
 * cree siempre- asi que la rama se retiro por quedarse sin caso.
 *
 * @param {object} arr la respuesta completa del backend (ok = 0)
 */
function avisarNoSePudoCrear(arr) {

    var d = arr.datos || {};
    var texto = arr.mensaje || 'No se pudo crear la declaración.';

    swal({ type: 'error', title: 'No se pudo crear', text: texto });
}

/**
 * Cuantos años hacia atras ofrece la lista. Es el mismo limite del servidor
 * (ControladorDeclaracionesICA::ANIOS_ANTERIORES), que es quien manda.
 */
var ANIOS_ANTERIORES_ICA = 10;

/**
 * Pregunta de que año es la declaracion antes de crearla.
 *
 * Juan (Paipa, 2026-10-05): las declaraciones de años anteriores se presentan
 * en todo momento. Escribir el año en la casilla del formulario no servia -el
 * servidor ponia siempre el actual-, por eso se pregunta aqui, al crear, y la
 * casilla queda de solo lectura. El año actual va marcado: para el caso de
 * siempre basta con pulsar "Crear".
 *
 * Las tres pantallas que crean (Presentar, Consultar y el RIT) pasan por aqui.
 *
 * @return {Promise<string|null>} el año elegido, o null si se cancelo
 */
function pedirAnioDeclaracion() {

    var actual = new Date().getFullYear();

    // Map y no objeto: las claves numericas de un objeto salen en orden
    // ascendente, y la lista debe empezar por el año actual.
    var anios = new Map();
    for (var a = actual; a >= actual - ANIOS_ANTERIORES_ICA; a--) {
        anios.set(String(a), String(a));
    }

    return swal({
        title: 'Año de la declaración',
        text: 'Elija el año que va a declarar. El número de la declaración sigue la serie de este año. '
            + 'Una declaración de un año anterior ya pasó su fecha límite: queda vencida y se paga '
            + 'con el recibo de pago y los intereses de mora.',
        input: 'select',
        inputOptions: anios,
        inputValue: String(actual),
        showCancelButton: true,
        confirmButtonText: 'Crear declaración',
        cancelButtonText: 'Cancelar'
    }).then(function (r) {
        return (r && r.value) ? r.value : null;
    });
}

var EditarDeclaracion = (function () {

    function abrir(decId) {

        $.ajax({
            url: '../business/controller/class.declaracionesICA.php',
            type: 'POST',
            dataType: 'json',
            data: { funcion: 13, dec_Id: decId },
            success: function (resp) {

                if (resp.ok != 1) {
                    /*
                     * Firmada: se toma el camino de "Editar" de una firmada, que
                     * pregunta y le quita las firmas (funcion 10) antes de
                     * abrirla. Asi llega, por ejemplo, la correccion en curso ya
                     * firmada que reabre "Corregir": antes se abria tal cual y
                     * se guardaba contenido nuevo debajo de las firmas viejas.
                     */
                    if (resp.datos && resp.datos.codigo === 'FIRMADA'
                        && typeof establecimientos !== 'undefined'
                        && typeof establecimientos.editarFirmada === 'function') {
                        establecimientos.editarFirmada(decId);
                        return;
                    }
                    swal({
                        type: 'error',
                        title: 'No se pudo abrir para editar',
                        text: resp.mensaje || 'Ocurrió un error al cargar la declaración.'
                    });
                    return;
                }

                var d = resp.datos.declaracion;
                var actividades = resp.datos.actividades || [];

                /*
                 * En blanco ANTES de rellenar, no despues.
                 *
                 * Se limpiaba despues de poner el numero, el año y el periodo
                 * -y limpiar borra justo esos tres-, asi que la declaracion se
                 * abria sin saber cual era: "Guardar" y cada renglon que se
                 * recalculaba viajaban sin id, el servidor respondia "Id de
                 * declaración requerido" y la pantalla decia "No se pudieron
                 * guardar las actividades" (revision del cliente 2026-09-25:
                 * "guardo, cierro, vuelvo a editar y me sale error"). Pasaba
                 * tambien con las correcciones, que se abren por aqui.
                 */
                limpiarFormularioDeclaracion();

                // El NUMERO, como al crear (ver icaWebPresentar.js): es lo que
                // ve el contribuyente, y el servidor busca primero por numero,
                // que es unico. Con el id, una declaracion vieja cuyo numero
                // coincidiera con este id se tomaria por esta.
                $('#numDeclaracion').val(d.dec_NumeroDeclaracion || d.dec_Id);
                $('#anioDeclaracion').val(d.dec_AnioDeclaracion);
                $('#periodoDeclaracion').val(d.dec_MesDeclaracion);

                // "Corrección" y el numero que corrige, si es una correccion (ver
                // FormularioDeclaracion.pintarOpcionUso).
                FormularioDeclaracion.pintarOpcionUso(d);

                // Fecha y hora: limpiar las borra y aquí no las volvía a poner
                // nadie. sqlsrv las manda como objeto ({date: 'AAAA-MM-DD hh:mm:ss…'}).
                FormularioDeclaracion.pintarFechaHora(d);

                // Los totales se repueblan desde las columnas dec_* (mismo
                // mapeo, a la inversa, que usa el guardado en
                // "Finalizar Declaración"); ingresos_municipio e
                // ingresos_gravables son de solo lectura y se recalculan.
                //
                // OJO: hay que formatearlos a formato colombiano ANTES de
                // meterlos al input. SQL Server devuelve los decimales como
                // texto con PUNTO decimal ("2500000.00"), pero estos campos se
                // leen despues con numero()/limpiarNumero(), que tratan el
                // punto como separador de MILES y por lo tanto lo eliminan:
                // "2500000.00" -> 250000000. El valor quedaba multiplicado por
                // 100 en cada pasada, y como la correccion copia y reabre la
                // declaracion, los ceros se iban acumulando (el bug de los
                // "00000" que reporto el cliente). Las actividades, mas abajo,
                // siempre hicieron bien este parseFloat + formatearCOP.
                // BD -> input: la conversion canonica vive en core/numeros.js.
                var aCOP = function (v) { return NumerosCOP.deBaseDeDatosAInput(v); };

                $('[data-campo="ingresos_total_pais"]').val(aCOP(d.dec_TotalIngresos));
                $('[data-campo="menos_fuera_municipio"]').val(aCOP(d.dec_IngresosFueraMunicipio));
                $('[data-campo="devoluciones"]').val(aCOP(d.dec_IngresosDevoluciones));
                $('[data-campo="exportaciones"]').val(aCOP(d.dec_IngresosExportaciones));
                $('[data-campo="venta_activos"]').val(aCOP(d.dec_IngresosVentas));
                $('[data-campo="actividades_excluidas"]').val(aCOP(d.dec_IngresosActividades));
                $('[data-campo="otras_exentas"]').val(aCOP(d.dec_IngresosOtrasActividades));

                if (typeof establecimientos !== 'undefined' && establecimientos.calcularIngresos) {
                    establecimientos.calcularIngresos();
                }

                /*
                 * La Liquidacion Privada tambien se pinta al abrir.
                 *
                 * Antes solo se pintaban los ingresos y las actividades, asi que
                 * los renglones 26 a 38 se quedaban en el "0" que trae el HTML
                 * aunque la declaracion tuviera valores guardados. La pantalla
                 * decia 0 y la base decia otra cosa, y el total -que se calcula
                 * sobre lo GUARDADO- no cuadraba con lo que se veia.
                 *
                 * Eso es lo que el cliente reporto el 2026-08-28 como "me sigue
                 * liquidando la sancion": el renglon aparentaba estar vacio y su
                 * importe seguia sumando.
                 */
                var v = function (campo, valor) {
                    $('[data-campo="' + campo + '"]').val(aCOP(valor));
                };

                v('industria_comercio',               d.dec_ValorConcepto1);
                v('avisos_tableros',                  d.dec_ValorConcepto2);
                v('sobretasa_bomberil',               d.dec_ValorConcepto3);
                v('total_impuesto_cargo',             d.dec_ValorConcepto4);
                v('valor_exencion_exoneracion',       d.dec_ValorConcepto5);
                v('menos_retenciones',                d.dec_ValorConcepto6);
                v('menos_autoretenciones',            d.dec_ValorConcepto7);
                v('anticipo_anterior',                d.dec_ValorConcepto8);
                v('anticipo_siguiente',               d.dec_ValorConcepto9);
                v('sanciones',                        d.dec_ValorConcepto10);
                v('saldo_favor_vigencias_anteriores', d.dec_ValorConcepto11);
                v('total_saldo_a_cargo',              d.dec_ValorConcepto12);
                v('total_saldo_a_favor',              d.dec_ValorConcepto13);
                v('valor_a_pagar',                    d.dec_ValorConcepto14);
                v('descuento_pronto_pago',            d.dec_ValorConcepto15);
                v('interes_mora',                     d.dec_ValorConcepto16);
                v('total_a_pagar',                    d.dec_ValorConcepto20);

                /*
                 * Renglones 18 y 19: generación de energía (capacidad instalada)
                 * e impuesto de la Ley 56 de 1981. Limpiar los deja en 0 y Guardar
                 * -y cada renglón que se recalcula- los manda, así que se pintaban
                 * en 0 y el servidor los grababa en 0: sp_calculo_comercio suma
                 * dec_ValorImpuesto al renglón 20 y la declaración de una
                 * generadora bajaba sin aviso. La capacidad va en kW y puede
                 * traer decimales: no se redondea a entero como los pesos.
                 */
                v('valor_impuesto', d.dec_ValorImpuesto);
                $('[data-campo="capacidad_instalada"]').val(
                    NumerosCOP.formatear(NumerosCOP.deBaseDeDatos(d.dec_CapacidadInstalada)));

                /*
                 * Y la sancion queda coherente con su opcion: si hay importe
                 * guardado, se marca el tipo; si no, "Ninguna" y bloqueada.
                 * Sin esto, una declaracion con sancion se abria con "Ninguna"
                 * marcada -es el valor por defecto del HTML- y el importe al
                 * lado, que es justo la contradiccion que se esta cerrando.
                 */
                if (Number(NumerosCOP.deBaseDeDatos(d.dec_ValorConcepto10)) > 0) {
                    $("#chkSinSancion").prop("checked", false);
                } else {
                    $("#chkSinSancion").prop("checked", true);
                }
                // Con el tipo guardado (migracion 038), se marca el que se eligio.
                if (d.dec_TipoSancion) { FormularioDeclaracion.pintarTipoSancion(d); }
                if (typeof sancionSegunTipo === 'function') { sancionSegunTipo(); }

                // Actividades: se muestran con la base/tarifa/impuesto TAL
                // COMO quedaron guardadas la ultima vez, no la lista
                // agregada desde cero que usa "Crear Declaración".
                var $tbody = $('#tbodyActividades').empty();

                actividades.forEach(function (a) {
                    var base = parseFloat(a.dia_BaseGravable) || 0;
                    var impuesto = parseFloat(a.dia_ValorImpuesto) || 0;
                    var fmt = (typeof establecimientos !== 'undefined' && establecimientos.formatearCOP)
                        ? establecimientos.formatearCOP
                        : function (n) { return n; };

                    $tbody.append(FormularioDeclaracion.filaActividad({
                        id: a.dia_IdActividad, codigo: a.acc_Codigo, nombre: a.acc_Nombre,
                        base: fmt(base), tarifa: a.dia_Tarifa, impuesto: fmt(impuesto)
                    }));
                });

                if (typeof establecimientos !== 'undefined' && establecimientos.calcularTotalesActividades) {
                    establecimientos.calcularTotalesActividades();
                }

                /*
                 * Ninguna guardada: las del contribuyente con base 0, como al
                 * crearla. Sin esto se abria con la tabla vacia y no habia forma
                 * de guardarla, ni por lo tanto de firmarla o presentarla (ver
                 * FormularioDeclaracion.cargarActividadesContribuyente). Nunca en
                 * una presentada, que no se edita.
                 */
                if (actividades.length === 0 && Number(d.dec_Estado) !== 2) {
                    FormularioDeclaracion.cargarActividadesContribuyente(
                        d.dec_IdContribuyente, $('#numDeclaracion').val());
                }

                $('#btnGenerarOficial, #btnLiquidar').prop('disabled', false);
                $('#btnDescargarPDF')
                    .prop('disabled', false)
                    .attr('onclick', "window.open('../extensiones/declaracion.php?dec_Id=" + d.dec_Id + "', '_blank')");

                $('#stepperDeclaracion').html(DeclaracionesUI.stepperHtml(d));

                $('#modal-CrearDeclaracion').modal({ backdrop: 'static', keyboard: false });
            },
            error: function (xhr) {
                console.error('Abrir para editar:', xhr && xhr.responseText);
                swal({
                    type: 'error',
                    title: 'No se pudo abrir para editar',
                    text: 'Intente de nuevo; si persiste, avise a soporte.'
                });
            }
        });
    }

    return { abrir: abrir };

})();

/**
 * Red de seguridad global para peticiones AJAX que fallan (500, timeout,
 * red caida, etc.). Antes ningun $.ajax() de estas pantallas definia
 * `error:`, asi que una peticion fallida dejaba el spinner de carga
 * girando para siempre y el usuario sin ningun mensaje. Esto no
 * reemplaza el manejo de errores propio de cada pantalla (los `success`
 * que revisan resp.ok siguen igual); solo cubre el caso de que la
 * peticion ni siquiera haya podido completarse.
 */
// La bandera window.__erpRedAjax la comparte dist/menu.php, que instala esta
// misma red en TODAS las pantallas (este archivo solo lo cargan dos). Quien
// registre primero gana; el otro no duplica el aviso.
if (typeof $ !== 'undefined' && !window.__erpRedAjax) {
    window.__erpRedAjax = true;
    $(document).ajaxError(function (event, jqxhr, settings) {
        $('#loading').hide();
        $('#wrapper').removeClass('body-load');

        if (typeof swal === 'function') {
            swal({
                type: 'error',
                title: 'Error de conexión',
                text: 'No se pudo completar la solicitud. Intenta de nuevo; si persiste, avisa a soporte.'
            });
        }
        if (window.console && console.error) {
            console.error('AJAX fallido:', settings && settings.url, jqxhr && jqxhr.status, jqxhr && jqxhr.responseText);
        }
    });
}


/* ===========================================================================
   EL BOTON "LIQUIDAR": CALCULA Y NO GUARDA
   ---------------------------------------------------------------------------
   Vuelve por pedido del cliente el 2026-08-26: "el boton de liquidar como el
   pasado, no guardarlo sino como estaba previo. El liquidar solo debe sumar o
   restar los bloqueados, lo demas debe salir en 0".

   Son DOS botones con dos trabajos distintos, no dos nombres para el mismo:

       Liquidar             calcula y muestra. No escribe nada.
       Guardar y liquidar   calcula y guarda. Es el que deja rastro.

   La vez pasada dos botones fueron el problema -uno tenia el manejador real y
   el otro mentia-, asi que aqui se hace al reves de entonces: los dos van al
   servidor, los dos usan el MISMO procedimiento de liquidacion y ninguno
   calcula por su cuenta. La unica diferencia esta en el backend: la funcion 14
   deshace la transaccion al terminar (ver _liquidarSinGuardar).

   Por que no se calcula en el navegador: las formulas de los renglones
   bloqueados viven en la tabla ind_Conceptos, no en el codigo. Reescribirlas
   en JavaScript daria dos liquidadores que se irian separando en silencio, y
   el contribuyente acabaria firmando una cifra distinta de la que vio.

   "Lo demas debe salir en 0" no hay que programarlo: los renglones que llena
   el contribuyente -retenciones, anticipos, sanciones- son suyos y Liquidar no
   los inventa. Salen en cero mientras no los escriba.
   =========================================================================== */
var LiquidacionEnPantalla = {

    /** Arma el mismo cuerpo que manda "Guardar y liquidar". */
    _datosDelFormulario: function () {
        // La misma lectura del formulario que el recalculo de un renglon
        // (FormularioDeclaracion): antes cada camino tenia su copia.
        var totales       = FormularioDeclaracion.totales();
        var idDeclaracion = $('#numDeclaracion').val();
        var actividades   = FormularioDeclaracion.actividades();

        return {
            funcion: 14,
            actividades: JSON.stringify(actividades),
            idDeclaracion: idDeclaracion,
            totales: JSON.stringify(totales),
            anio: $('#anioDeclaracion').val(),
            mes: $('#periodoDeclaracion').val(),
            numero: idDeclaracion,
            _cuantasActividades: actividades.length
        };
    },

    /**
     * Vuelca los renglones calculados al formulario.
     *
     * Solo toca los BLOQUEADOS. Los que llena el contribuyente se dejan como
     * estan: pisarlos con lo que devuelve el servidor le borraria en pantalla
     * lo que acaba de escribir.
     */
    pintar: function (d) {
        var v = function (campo, valor) {
            $('[data-campo="' + campo + '"]').val(
                establecimientos.formatearCOP(establecimientos.limpiarEntero(valor)));
        };

        v('industria_comercio',     d.dec_ValorConcepto1);
        v('avisos_tableros',        d.dec_ValorConcepto2);
        v('sobretasa_bomberil',     d.dec_ValorConcepto3);
        v('total_impuesto_cargo',   d.dec_ValorConcepto4);
        v('total_saldo_a_cargo',    d.dec_ValorConcepto12);
        v('total_saldo_a_favor',    d.dec_ValorConcepto13);
        v('valor_a_pagar',          d.dec_ValorConcepto14);
        v('total_a_pagar',          d.dec_ValorConcepto20);
    },

    calcular: function () {
        if (!establecimientos.validarBasesActividades()) { return; }

        var datos = this._datosDelFormulario();

        if (datos._cuantasActividades === 0) {
            swal('Sin actividades', 'Agregue al menos una actividad para liquidar.', 'error');
            return;
        }
        delete datos._cuantasActividades;

        var self = this;

        $.ajax({
            url: '../business/controller/class.declaracionesICA.php',
            type: 'POST',
            dataType: 'json',
            data: datos,
            success: function (arr) {
                if (arr.ok != 1 || !arr.datos) {
                    swal({ type: 'error', title: 'No se pudo liquidar', text: arr.mensaje || 'Intente de nuevo.' });
                    return;
                }

                self.pintar(arr.datos);

                // Texto del cliente (revisión 2026-09-25).
                swal({
                    type: 'info',
                    title: 'Liquidación calculada',
                    text: 'Estas son las cifras calculadas. Para conservarlas, pulse "Guardar".'
                });
            },
            // Sin error() la pantalla se queda muda si el backend no devuelve
            // JSON valido. Es exactamente como murio el Liquidar anterior.
            error: function (xhr) {
                console.error('Liquidar (sin guardar):', xhr.responseText);
                swal('Error', 'No se pudo liquidar. Intente de nuevo; si persiste, avise a soporte.', 'error');
            }
        });
    }
};

$(document).on('click', '#btnLiquidar', function () {
    LiquidacionEnPantalla.calcular();
});
