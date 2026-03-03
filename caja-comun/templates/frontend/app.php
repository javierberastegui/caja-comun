<div class="ccf-app" data-ccf-view="app">
	<div class="ccf-shell">
		<header class="ccf-header">
			<div>
				<p class="ccf-eyebrow">Caja Común</p>
				<h2>Libro de movimientos</h2>
				<p class="ccf-subtitle">Registra, revisa y visualiza tus movimientos del mes en una sola pantalla.</p>
			</div>
			<div class="ccf-header-actions">
				<label for="ccf-month">Mes</label>
				<input type="month" id="ccf-month" value="<?php echo esc_attr( gmdate( 'Y-m' ) ); ?>" />
				<button type="button" id="ccf-new-movement" class="ccf-btn ccf-btn-primary">Nuevo movimiento</button>
			</div>
		</header>

		<section class="ccf-kpis" aria-label="Resumen mensual">
			<article class="ccf-kpi-card"><span>Ingresos</span><strong id="ccf-kpi-income">0,00 €</strong></article>
			<article class="ccf-kpi-card"><span>Gastos</span><strong id="ccf-kpi-expense">0,00 €</strong></article>
			<article class="ccf-kpi-card"><span>Balance</span><strong id="ccf-kpi-balance">0,00 €</strong></article>
			<article class="ccf-kpi-card"><span>Presupuesto común</span><strong id="ccf-kpi-common">0,00 €</strong></article>
		</section>

		<section class="ccf-card" aria-label="Movimientos">
			<div class="ccf-section-header">
				<h3>Movimientos del mes</h3>
				<p id="ccf-table-feedback" class="ccf-feedback" role="status"></p>
			</div>
			<div class="ccf-table-wrap">
				<table id="ccf-transactions-table">
					<thead>
						<tr>
							<th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Categoría</th><th>Cuenta</th><th>Importe</th><th>Ticket</th><th>Estado</th><th>Acciones</th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
			<p id="ccf-empty-state" class="ccf-empty" hidden>Aún no hay movimientos en este mes. Añade tu primer ingreso o gasto.</p>
		</section>

		<section class="ccf-card" aria-label="Gráficas">
			<div class="ccf-section-header"><h3>Visión rápida</h3></div>
			<div class="ccf-charts-grid">
				<div class="ccf-chart-card"><h4>Ingresos vs gastos</h4><canvas id="ccf-chart-income"></canvas><p class="ccf-chart-empty" hidden>No hay datos suficientes para mostrar esta gráfica.</p></div>
				<div class="ccf-chart-card"><h4>Distribución por categorías</h4><canvas id="ccf-chart-category"></canvas><p class="ccf-chart-empty" hidden>No hay datos suficientes para mostrar esta gráfica.</p></div>
				<div class="ccf-chart-card"><h4>Evolución mensual</h4><canvas id="ccf-chart-trend"></canvas><p class="ccf-chart-empty" hidden>No hay datos suficientes para mostrar esta gráfica.</p></div>
			</div>
		</section>
	</div>

	<dialog id="ccf-movement-modal" class="ccf-modal">
		<form id="ccf-movement-form" class="ccf-modal-form" method="dialog" enctype="multipart/form-data">
			<input type="hidden" name="id" value="" />
			<div class="ccf-modal-header">
				<h3 id="ccf-modal-title">Nuevo movimiento</h3>
				<button type="button" id="ccf-modal-close" class="ccf-btn ccf-btn-soft">Cerrar</button>
			</div>
			<div class="ccf-form-grid">
				<label>Fecha<input type="date" name="transaction_date" value="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" required></label>
				<label>Tipo
					<select name="type" required>
						<option value="expense">Gasto</option>
						<option value="income">Ingreso</option>
						<option value="transfer">Transferencia</option>
						<option value="adjustment">Ajuste</option>
					</select>
				</label>
				<label>Concepto<input type="text" name="description" required></label>
				<div class="ccf-field-block">
					<label>Categoría<select name="category_id" id="ccf-category-select"></select></label>
					<p id="ccf-category-empty" class="ccf-field-empty" hidden>No hay categorías creadas todavía.</p>
					<div class="ccf-inline-actions">
						<button type="button" id="ccf-open-create-category" class="ccf-btn ccf-btn-soft">+ Nueva categoría</button>
					</div>
					<div id="ccf-create-category-wrap" class="ccf-inline-create" hidden>
						<label>Nueva categoría<input type="text" id="ccf-create-category-name" placeholder="Ej: Alimentación"></label>
						<div class="ccf-inline-actions">
							<button type="button" id="ccf-create-category-submit" class="ccf-btn ccf-btn-primary">Guardar categoría</button>
							<button type="button" id="ccf-create-category-cancel" class="ccf-btn ccf-btn-soft">Cancelar</button>
						</div>
						<p id="ccf-category-create-feedback" class="ccf-feedback" role="status"></p>
					</div>
				</div>
				<div class="ccf-field-block">
					<label>Cuenta<select name="source_account_id" id="ccf-source-account"></select></label>
					<p id="ccf-account-empty" class="ccf-field-empty" hidden>No hay cuentas creadas todavía.</p>
					<div class="ccf-inline-actions">
						<button type="button" id="ccf-open-create-account" class="ccf-btn ccf-btn-soft">+ Nueva cuenta</button>
					</div>
					<div id="ccf-create-account-wrap" class="ccf-inline-create" hidden>
						<label>Nueva cuenta<input type="text" id="ccf-create-account-name" placeholder="Ej: Cuenta principal"></label>
						<div class="ccf-inline-actions">
							<button type="button" id="ccf-create-account-submit" class="ccf-btn ccf-btn-primary">Guardar cuenta</button>
							<button type="button" id="ccf-create-account-cancel" class="ccf-btn ccf-btn-soft">Cancelar</button>
						</div>
						<p id="ccf-account-create-feedback" class="ccf-feedback" role="status"></p>
					</div>
				</div>
				<label>Importe<input type="number" name="amount" min="0.01" step="0.01" required></label>
				<label>Estado
					<select name="status">
						<option value="posted">Revisado</option>
						<option value="pending">Pendiente</option>
					</select>
				</label>
				<label>Ticket / justificante<input type="file" name="files[]" id="ccf-attachments" multiple accept="image/*,application/pdf"></label>
			</div>
			<p id="ccf-modal-feedback" class="ccf-feedback" role="status"></p>
			<div class="ccf-modal-actions">
				<button type="submit" class="ccf-btn ccf-btn-primary">Guardar</button>
			</div>
		</form>
	</dialog>

	<input type="file" id="ccf-inline-attachment" accept="image/*,application/pdf" hidden />
</div>
