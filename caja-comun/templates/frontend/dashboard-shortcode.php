<div class="ccf-frontend-wrap">
	<h2>Caja Común · Resumen <?php echo esc_html( $summary['month_key'] ); ?></h2>
	<ul>
		<li><strong>Ingresos totales:</strong> <?php echo esc_html( number_format( (float) $summary['income_total'], 2 ) ); ?> €</li>
		<li><strong>Total separado (fuera de presupuesto):</strong> <?php echo esc_html( number_format( (float) $summary['separated_total'], 2 ) ); ?> €</li>
		<li><strong>Presupuesto común operativo:</strong> <?php echo esc_html( number_format( (float) $summary['common_budget'], 2 ) ); ?> €</li>
		<li><strong>Estado asignación:</strong> <?php echo esc_html( ucfirst( $summary['allocation_status'] ) ); ?></li>
	</ul>

	<?php if ( 'yes' === $atts['show_incomes'] ) : ?>
		<h3>Últimos ingresos</h3>
		<table>
			<thead><tr><th>Fecha</th><th>Usuario</th><th>Monto</th></tr></thead>
			<tbody>
			<?php foreach ( $summary['latest_incomes'] as $income ) : ?>
				<tr>
					<td><?php echo esc_html( $income['created_at'] ); ?></td>
					<td><?php echo esc_html( $income['user_id'] ); ?></td>
					<td><?php echo esc_html( number_format( (float) $income['amount'], 2 ) ); ?> €</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( 'yes' === $atts['show_movements'] ) : ?>
		<h3>Últimos movimientos automáticos</h3>
		<table>
			<thead><tr><th>Fecha</th><th>Tipo</th><th>Monto</th></tr></thead>
			<tbody>
			<?php foreach ( $summary['latest_transactions'] as $tx ) : ?>
				<tr>
					<td><?php echo esc_html( $tx['created_at'] ); ?></td>
					<td><?php echo esc_html( $tx['type'] ); ?></td>
					<td><?php echo esc_html( number_format( (float) $tx['amount'], 2 ) ); ?> €</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
