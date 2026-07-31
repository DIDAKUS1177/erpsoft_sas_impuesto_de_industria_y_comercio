<!-- ══════════ MODAL FIRMA DE DECLARACIÓN ══════════ -->
<div class="modal fade" id="modal-FirmaDeclaracion" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header" style="background:#28a745;">
				<h5 class="modal-title text-white">
					<i class="fa fa-file-signature"></i> Firmar Declaración
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">

				<!-- Info de la declaración seleccionada -->
				<div id="decl-info" class="alert alert-secondary" style="font-size:13px;margin-bottom:12px;">
					<i class="fa fa-file-text-o"></i>
					Declaración N° <strong id="decl-numero-display">—</strong>
				</div>

				<!-- Indicador de pasos -->
				<div class="d-flex justify-content-between mb-3" style="font-size:12px;font-weight:600;">
					<span class="text-success"><i class="fa fa-envelope"></i> 1. Enviar código</span>
					<span class="text-muted">→</span>
					<span class="text-success"><i class="fa fa-key"></i> 2. Verificar código</span>
					<span class="text-muted">→</span>
					<span class="text-success"><i class="fa fa-check-circle"></i> Firmado</span>
				</div>

				<!-- PASO 1 -->
				<div id="decl-paso-1" class="decl-paso text-center py-3">
					<p class="mb-3">Se enviará un código de verificación a tu correo para autorizar la firma.</p>
					<button type="button" class="btn btn-success" id="decl-btn-enviar"
						onclick="firmaDeclaracion.enviarCodigo()">
						<i class="fa fa-envelope"></i> Enviar código al correo
					</button>
				</div>

				<!-- PASO 2 -->
				<div id="decl-paso-2" class="decl-paso" style="display:none;">
					<p class="text-center mb-3">Ingresa el código de 6 dígitos que recibiste en tu correo.</p>
					<div class="text-center mb-2" id="decl-timer-wrap" style="display:none;">
						<span id="decl-timer-badge" class="badge badge-warning" style="font-size:14px;padding:6px 16px;">
							<i class="fa fa-clock-o"></i> Código válido por: <strong id="decl-timer-display">10:00</strong>
						</span>
					</div>
					<div class="form-group">
						<input type="text" class="form-control text-center" id="decl-codigo-input"
							maxlength="6" placeholder="000000"
							style="font-size:28px;letter-spacing:10px;font-weight:bold;">
					</div>
					<div class="d-flex justify-content-between mt-3">
						<button type="button" class="btn btn-outline-secondary btn-sm"
							onclick="firmaDeclaracion._irPaso(1)">
							<i class="fa fa-arrow-left"></i> Reenviar código
						</button>
						<button type="button" class="btn btn-success" id="decl-btn-verificar"
							onclick="firmaDeclaracion.verificarYFirmar()">
							<i class="fa fa-check"></i> Verificar y Firmar
						</button>
					</div>
				</div>

				<!-- PASO FINAL: Firmado -->
				<div id="decl-paso-ok" class="decl-paso text-center py-4" style="display:none;">
					<i class="fa fa-check-circle" style="font-size:48px;color:#28a745;"></i>
					<h5 class="mt-3 text-success">¡Declaración Firmada!</h5>
					<p class="text-muted" id="decl-ok-msg" style="font-size:13px;"></p>
				</div>

			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
					Cerrar
				</button>
			</div>
		</div>
	</div>
</div>
<!-- ══════════════════════════════════════ -->
