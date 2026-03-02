<div class="wrap ccf-admin-wrap">
	<h1>Ingresos Mensuales</h1>
	<form method="post">
		<?php wp_nonce_field( 'ccf_save_income_action' ); ?>
		<table class="form-table">
			<tr><th><label for="month_key">Mes</label></th><td><input type="month" name="month_key" id="month_key" value="<?php echo esc_attr( $month_key ); ?>" required></td></tr>
			<tr><th><label for="user_id">Usuario</label></th><td><input type="number" min="1" name="user_id" id="user_id" required></td></tr>
			<tr><th><label for="amount">Monto</label></th><td><input type="number" step="0.01" min="0.01" name="amount" id="amount" required></td></tr>
			<tr><th><label for="notes">Notas</label></th><td><input type="text" name="notes" id="notes" class="regular-text"></td></tr>
		</table>
		<p><button type="submit" class="button button-primary" name="ccf_save_income" value="1">Guardar Ingreso</button></p>
	</form>

	<h2>Registros del mes <?php echo esc_html( $month_key ); ?></h2>
	<table class="widefat striped">
		<thead><tr><th>ID</th><th>Usuario</th><th>Monto</th><th>Notas</th><th>Actualizado</th></tr></thead>
		<tbody>
		<?php foreach ( $incomes as $income ) : ?>
			<tr>
				<td><?php echo esc_html( $income['id'] ); ?></td>
				<td><?php echo esc_html( $income['user_id'] ); ?></td>
				<td><?php echo esc_html( number_format( (float) $income['amount'], 2 ) ); ?> €</td>
				<td><?php echo esc_html( $income['notes'] ); ?></td>
				<td><?php echo esc_html( $income['updated_at'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
