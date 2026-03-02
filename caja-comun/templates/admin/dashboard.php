<div class="wrap ccf-admin-wrap">
	<h1>Caja Común - Dashboard</h1>
	<div class="ccf-kpi-grid">
		<div class="ccf-kpi-card"><h3>Ingresos Totales</h3><p><?php echo esc_html( number_format( (float) $summary['income_total'], 2 ) ); ?> €</p></div>
		<div class="ccf-kpi-card"><h3>Total Separado (informativo)</h3><p><?php echo esc_html( number_format( (float) $summary['separated_total'], 2 ) ); ?> €</p></div>
		<div class="ccf-kpi-card"><h3>Presupuesto Común operativo</h3><p><?php echo esc_html( number_format( (float) $summary['common_budget'], 2 ) ); ?> €</p></div>
		<div class="ccf-kpi-card"><h3>Gasto común real</h3><p><?php echo esc_html( number_format( (float) $summary['common_expense'], 2 ) ); ?> €</p></div>
	</div>
	<div class="ccf-chart-grid">
		<canvas id="ccf-chart-income"></canvas>
		<canvas id="ccf-chart-budget-expense"></canvas>
		<canvas id="ccf-chart-categories"></canvas>
		<canvas id="ccf-chart-evolution"></canvas>
	</div>
	<script>window.ccfDashboardCharts = <?php echo wp_json_encode( $summary['charts'] ); ?>;</script>
</div>
