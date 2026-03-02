<div class="wrap ccf-admin-wrap">
	<h1>Caja Común - Dashboard</h1>
	<div class="ccf-kpi-grid">
		<div class="ccf-kpi-card"><h3>Ingresos Totales</h3><p><?php echo esc_html( number_format( (float) $summary['income_total'], 2 ) ); ?> €</p></div>
		<div class="ccf-kpi-card"><h3>Total Separado (informativo)</h3><p><?php echo esc_html( number_format( (float) $summary['separated_total'], 2 ) ); ?> €</p></div>
		<div class="ccf-kpi-card"><h3>Presupuesto Común operativo</h3><p><?php echo esc_html( number_format( (float) $summary['common_budget'], 2 ) ); ?> €</p></div>
		<div class="ccf-kpi-card"><h3>Gasto común real</h3><p><?php echo esc_html( number_format( (float) $summary['common_expense'], 2 ) ); ?> €</p></div>
	</div>

	<div class="ccf-kpi-card" style="margin-top:20px;">
		<h2>Charts (data ready)</h2>
		<p>Los datos agregados están disponibles vía REST y listos para consumirse en la Fase UI.</p>
		<pre id="ccf-charts-ready">Cargando datos...</pre>
	</div>
	<script>
	(function () {
		const target = document.getElementById('ccf-charts-ready');
		if (!target || !window.ccfAdmin) return;
		fetch(`${window.ccfAdmin.restRoot}dashboard/charts-data-ready?month_key=<?php echo esc_js( $month_key ); ?>`, {
			headers: { 'X-WP-Nonce': window.ccfAdmin.nonce }
		})
			.then((res) => res.json())
			.then((data) => {
				target.textContent = JSON.stringify(data, null, 2);
			})
			.catch(() => {
				target.textContent = 'No se pudo cargar el endpoint de charts.';
			});
	})();
	</script>
</div>
