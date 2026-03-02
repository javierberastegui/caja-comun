<div class="wrap ccf-admin-wrap"><h1>Cuentas</h1>
<form method="post"><?php wp_nonce_field( 'ccf_save_account_action' ); ?>
<input type="hidden" name="id" value="">
<input type="text" name="name" placeholder="Nombre" required>
<input type="text" name="slug" placeholder="slug" required>
<select name="type"><option>common</option><option>personal</option><option>savings</option><option>debt</option><option>adjustment</option><option>custom</option></select>
<label><input type="checkbox" name="is_visible" value="1" checked>Visible</label>
<label><input type="checkbox" name="allow_manual" value="1" checked>Manual</label>
<label><input type="checkbox" name="monthly_process" value="1" checked>Mensual</label>
<button class="button button-primary" name="ccf_save_account" value="1">Guardar</button></form>
<table class="widefat striped"><thead><tr><th>ID</th><th>Nombre</th><th>Slug</th><th>Tipo</th><th>Estado</th></tr></thead><tbody><?php foreach ( $accounts as $a ) : ?><tr><td><?php echo esc_html( $a['id'] ); ?></td><td><?php echo esc_html( $a['name'] ); ?></td><td><?php echo esc_html( $a['slug'] ); ?></td><td><?php echo esc_html( $a['type'] ); ?></td><td><?php echo esc_html( $a['status'] ); ?></td></tr><?php endforeach; ?></tbody></table></div>
