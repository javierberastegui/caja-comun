<?php
$profile_a = (string) $this->settings_repository->get( 'ccf_frontend_profile_a_name', 'Perfil A' );
$profile_b = (string) $this->settings_repository->get( 'ccf_frontend_profile_b_name', 'Perfil B' );
?>
<div class="wrap ccf-admin-wrap">
	<h1>Ajustes frontend</h1>
	<p>La protección de acceso frontend ahora depende solo de la contraseña nativa de la página de WordPress que contiene el shortcode <code>[ccf_app]</code>.</p>
	<form method="post">
		<?php wp_nonce_field( 'ccf_save_frontend_settings_action' ); ?>
		<table class="form-table" role="presentation">
			<tr><th><label for="ccf_frontend_profile_a_name">Nombre Perfil A</label></th><td><input type="text" id="ccf_frontend_profile_a_name" name="ccf_frontend_profile_a_name" value="<?php echo esc_attr( $profile_a ); ?>" /></td></tr>
			<tr><th><label for="ccf_frontend_profile_b_name">Nombre Perfil B</label></th><td><input type="text" id="ccf_frontend_profile_b_name" name="ccf_frontend_profile_b_name" value="<?php echo esc_attr( $profile_b ); ?>" /></td></tr>
		</table>
		<p><button class="button button-primary" name="ccf_save_frontend_settings" value="1">Guardar ajustes frontend</button></p>
	</form>
	<h2>Shortcodes frontend</h2>
	<code>[ccf_app]</code> <code>[ccf_dashboard]</code> <code>[ccf_income_form]</code> <code>[ccf_transaction_form]</code> <code>[ccf_transactions_list]</code>
</div>
