<!-- ══════════ MODAL FIRMA PERSONAL DEL USUARIO ══════════ -->
<div class="modal fade" id="modal-FirmaUsuario" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-md" role="document">
		<div class="modal-content">
			<div class="modal-header" style="background:#6c757d;">
				<h5 class="modal-title text-white">
					<i class="fa fa-pencil-square-o"></i> Mi Firma Digital
				</h5>
				<button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">

				<!-- ── PASO 1: Verificación OTP ─────────────────────────────── -->
				<div id="fu-paso-1">
					<div class="text-center mb-3">
						<i class="fa fa-envelope-o fa-2x text-secondary mb-2" style="display:block;margin-bottom:8px;"></i>
						<p style="font-size:13px;margin-bottom:0;">
							Para registrar tu firma digital necesitas verificar tu identidad.<br>
							Se enviará un código de verificación a tu correo registrado.
						</p>
					</div>

					<!-- Botón enviar -->
					<div id="fu-otp-enviar" class="text-center">
						<button type="button" class="btn btn-secondary" id="fu-btn-enviar"
							onclick="perfilFirma.enviarCodigo()">
							<i class="fa fa-paper-plane-o"></i> Enviar código
						</button>
					</div>

					<!-- Input verificación (aparece tras enviar) -->
					<div id="fu-otp-verificar" style="display:none;margin-top:16px;">
						<p class="text-center" style="font-size:13px;color:#388e3c;">
							<i class="fa fa-check-circle"></i> Código enviado. Revisa tu correo.
						</p>
						<div class="form-group">
							<label style="font-size:13px;">Ingresa el código de 6 dígitos:</label>
							<input type="text" class="form-control text-center" id="fu-otp-input"
								maxlength="6" placeholder="● ● ● ● ● ●"
								style="font-size:22px;letter-spacing:8px;font-weight:bold;">
						</div>
						<div class="text-center">
							<button type="button" class="btn btn-primary" id="fu-btn-verificar"
								onclick="perfilFirma.verificarCodigo()">
								<i class="fa fa-check"></i> Verificar
							</button>
						</div>
					</div>
				</div>

				<!-- ── PASO 2: Dibujar firma ─────────────────────────────────── -->
				<div id="fu-paso-2" style="display:none;">

					<!-- Firma existente -->
					<div id="fu-info-existente" class="alert alert-info" style="display:none;font-size:13px;margin-bottom:10px;"></div>

					<p class="mb-2" style="font-size:13px;">
						Dibuja tu firma en el recuadro. Esta firma será usada al firmar documentos oficiales.
					</p>

					<!-- Canvas de firma -->
					<div style="border:2px solid #6c757d;border-radius:6px;background:#fff;position:relative;">
						<canvas id="fu-canvas"
							style="width:100%;height:150px;display:block;touch-action:none;"></canvas>
					</div>

					<div class="d-flex justify-content-between mt-2 mb-3">
						<button type="button" class="btn btn-outline-secondary btn-sm"
							onclick="perfilFirma.limpiarCanvas()">
							<i class="fa fa-eraser"></i> Limpiar
						</button>
						<span class="text-muted" style="font-size:11px;align-self:center;">
							Firma con el mouse o con el dedo
						</span>
					</div>

					<!-- Preview firma guardada -->
					<div id="fu-preview-wrap" style="display:none;">
						<hr>
						<p class="text-muted" style="font-size:12px;margin-bottom:4px;">Firma guardada actualmente:</p>
						<img id="fu-preview-img" src="" alt="Firma actual"
							style="max-width:100%;max-height:80px;border:1px dashed #ccc;padding:4px;border-radius:4px;">
						<br>
						<small id="fu-preview-fecha" class="text-muted"></small>
					</div>

				</div>

			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
					Cancelar
				</button>
				<button type="button" class="btn btn-primary btn-sm" id="fu-btn-guardar"
					disabled style="display:none;" onclick="perfilFirma.guardar()">
					<i class="fa fa-save"></i> Guardar firma
				</button>
			</div>
		</div>
	</div>
</div>

