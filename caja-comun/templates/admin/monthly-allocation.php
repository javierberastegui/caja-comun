<div class="wrap ccf-admin-wrap">
	<h1>Asignación Mensual</h1>
	<h2>Preview para <?php echo esc_html( $month_key ); ?></h2>
	<ul>
		<li>Total ingresos: <?php echo esc_html( number_format( (float) $preview['income_total'], 2 ) ); ?> €</li>
		<li>Total separado: <?php echo esc_html( number_format( (float) $preview['separated_total'], 2 ) ); ?> €</li>
		<li>Separado Usuario 1: <?php echo esc_html( number_format( (float) $preview['separated_user_1'], 2 ) ); ?> €</li>
		<li>Separado Usuario 2: <?php echo esc_html( number_format( (float) $preview['separated_user_2'], 2 ) ); ?> €</li>
		<li><strong>Presupuesto común operativo: <?php echo esc_html( number_format( (float) $preview['common_budget'], 2 ) ); ?> €</strong></li>
	</ul>
	<form method="post">
		<?php wp_nonce_field( 'ccf_run_allocation_action' ); ?>
		<input type="hidden" name="month_key" value="<?php echo esc_attr( $month_key ); ?>">
		<p><button type="submit" class="button button-primary" name="ccf_run_allocation" value="1">Ejecutar Asignación Mensual</button></p>
	</form>

	<?php if ( $result ) : ?>
		<h2>Resultado ejecutado</h2>
		<pre><?php echo esc_html( wp_json_encode( $result, JSON_PRETTY_PRINT ) ); ?></pre>
	<?php endif; ?>
</div>
