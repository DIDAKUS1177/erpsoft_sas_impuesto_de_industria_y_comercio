<!-- ══════════ MODAL FIRMA DIGITAL (Establecimiento / RIT) ══════════ -->
<div class="modal fade" id="modal-Firma" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header" style="background:#1a73e8;">
				<h5 class="modal-title text-white"><i class="fa fa-pencil-square-o"></i> Firma Digital del Funcionario</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">

				<!-- Alerta firma existente -->
				<div id="firma-info-existente" class="alert alert-info" style="display:none;font-size:13px;"></div>

				<!-- Indicador de pasos -->
				<div class="d-flex justify-content-between mb-3" style="font-size:12px;font-weight:600;">
					<span class="text-primary"><i class="fa fa-envelope"></i> 1. Enviar código</span>
					<span class="text-muted">→</span>
					<span class="text-primary"><i class="fa fa-key"></i> 2. Verificar código</span>
					<span class="text-muted">→</span>
					<span class="text-primary"><i class="fa fa-pencil"></i> 3. Dibujar firma</span>
				</div>

				<!-- PASO 1: Enviar código -->
				<div id="firma-paso-1" class="firma-paso text-center py-3">
					<p class="mb-3">Se enviará un código de verificación al correo registrado de tu cuenta.</p>
					<button type="button" class="btn btn-primary" id="firma-btn-enviar" onclick="firmas.enviarCodigo()">
						<i class="fa fa-envelope"></i> Enviar código al correo
					</button>
				</div>

				<!-- PASO 2: Verificar código -->
				<div id="firma-paso-2" class="firma-paso" style="display:none;">
					<p class="text-center mb-3">Ingresa el código de 6 dígitos que recibiste en tu correo.</p>
					<div class="text-center mb-2" id="firma-timer-wrap" style="display:none;">
						<span id="firma-timer-badge" class="badge badge-warning" style="font-size:14px;padding:6px 16px;">
							<i class="fa fa-clock-o"></i> Código válido por: <strong id="firma-timer-display">10:00</strong>
						</span>
					</div>
					<div class="form-group">
						<input type="text" class="form-control text-center" id="firma-codigo-input"
							maxlength="6" placeholder="000000"
							style="font-size:28px;letter-spacing:10px;font-weight:bold;">
					</div>
					<div class="d-flex justify-content-between mt-3">
						<button type="button" class="btn btn-outline-secondary btn-sm" onclick="firmas._irPaso(1)">
							<i class="fa fa-arrow-left"></i> Reenviar código
						</button>
						<button type="button" class="btn btn-success" id="firma-btn-verificar" onclick="firmas.verificarCodigo()">
							<i class="fa fa-check"></i> Verificar código
						</button>
					</div>
				</div>

				<!-- PASO 3: Dibujar firma -->
				<div id="firma-paso-3" class="firma-paso" style="display:none;">
					<p class="text-center mb-2">Dibuja tu firma en el recuadro de abajo.</p>
					<div style="border:2px solid #1a73e8;border-radius:6px;background:#fff;position:relative;">
						<canvas id="firma-canvas" style="width:100%;height:140px;display:block;touch-action:none;"></canvas>
					</div>
					<div class="d-flex justify-content-between mt-2">
						<button type="button" class="btn btn-outline-secondary btn-sm" id="firma-btn-limpiar"
							onclick="firmas.limpiarCanvas()">
							<i class="fa fa-eraser"></i> Limpiar
						</button>
						<span class="text-muted" style="font-size:11px;align-self:center;">
							Firma con el mouse o con el dedo
						</span>
					</div>
				</div>

			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-danger btn-sm" data-dismiss="modal">
					<i class="fa fa-times"></i> Cancelar
				</button>
				<button type="button" class="btn btn-success btn-sm" id="firma-btn-guardar"
					disabled onclick="firmas.guardarFirma()">
					<i class="fa fa-save"></i> Guardar firma
				</button>
			</div>
		</div>
	</div>
</div>
<!-- ══════════════════════════════════════ -->
