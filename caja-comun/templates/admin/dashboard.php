<div class="wrap ccf-admin-wrap">
	<h1>Caja Común - Dashboard</h1>
	<div class="ccf-kpi-grid">
		<div class="ccf-kpi-card">
			<h3>Ingresos Totales</h3>
			<p><?php echo esc_html( number_format( (float) $summary['income_total'], 2 ) ); ?> €</p>
		</div>
		<div class="ccf-kpi-card">
			<h3>Total Separado (Fuera de Presupuesto)</h3>
			<p><?php echo esc_html( number_format( (float) $summary['separated_total'], 2 ) ); ?> €</p>
		</div>
		<div class="ccf-kpi-card">
			<h3>Presupuesto Común</h3>
			<p><?php echo esc_html( number_format( (float) $summary['common_budget'], 2 ) ); ?> €</p>
		</div>
		<div class="ccf-kpi-card">
			<h3>Estado Asignación</h3>
			<p><?php echo esc_html( ucfirst( $summary['allocation_status'] ) ); ?></p>
		</div>
	</div>

	<h2>Últimos ingresos registrados</h2>
	<table class="widefat striped">
		<thead><tr><th>Fecha</th><th>Usuario</th><th>Monto</th><th>Notas</th></tr></thead>
		<tbody>
		<?php foreach ( $summary['latest_incomes'] as $income ) : ?>
			<tr>
				<td><?php echo esc_html( $income['created_at'] ); ?></td>
				<td><?php echo esc_html( $income['user_id'] ); ?></td>
				<td><?php echo esc_html( number_format( (float) $income['amount'], 2 ) ); ?> €</td>
				<td><?php echo esc_html( $income['notes'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2>Últimos movimientos automáticos</h2>
	<table class="widefat striped">
		<thead><tr><th>Fecha</th><th>Tipo</th><th>Monto</th><th>Descripción</th></tr></thead>
		<tbody>
		<?php foreach ( $summary['latest_transactions'] as $transaction ) : ?>
			<tr>
				<td><?php echo esc_html( $transaction['created_at'] ); ?></td>
				<td><?php echo esc_html( $transaction['type'] ); ?></td>
				<td><?php echo esc_html( number_format( (float) $transaction['amount'], 2 ) ); ?> €</td>
				<td><?php echo esc_html( $transaction['description'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="ccf-chart-placeholder">
		<h2>Área de gráficas (Fase 2)</h2>
		<p>Preparado para: evolución ingresos, presupuesto común, gasto por categoría, comparativas y separado vs común.</p>
	</div>
</div>
