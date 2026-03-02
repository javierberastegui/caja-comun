<div class="wrap ccf-admin-wrap"><h1>Categorías</h1>
<form method="post"><?php wp_nonce_field( 'ccf_save_category_action' ); ?>
<input type="text" name="name" placeholder="Nombre" required>
<input type="text" name="slug" placeholder="slug">
<input type="color" name="color" value="#2271b1">
<input type="text" name="icon" placeholder="icono">
<button class="button button-primary" name="ccf_save_category" value="1">Guardar</button></form>
<table class="widefat striped"><thead><tr><th>ID</th><th>Nombre</th><th>Slug</th><th>Color</th><th>Activa</th></tr></thead><tbody><?php foreach ( $categories as $c ) : ?><tr><td><?php echo esc_html( $c['id'] ); ?></td><td><?php echo esc_html( $c['name'] ); ?></td><td><?php echo esc_html( $c['slug'] ); ?></td><td><span style="display:inline-block;width:20px;height:20px;background:<?php echo esc_attr( $c['color'] ?: '#2271b1' ); ?>"></span></td><td><?php echo esc_html( $c['active'] ); ?></td></tr><?php endforeach; ?></tbody></table></div>
