<?php
/**
 * Plugin Name: Economia Pro
 * Description: Sistema financiero doméstico.
 * Version: 2.4
 * Author: Loki
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('EconomiaPro')) {
final class EconomiaPro {
    private const OPTION_PASSWORD = 'ecopro_front_password';
    private const OPTION_PAGE_ID  = 'ecopro_front_page_id';
    private string $table_transactions;
    private string $table_categories;

    public function __construct() {
        global $wpdb;
        $this->table_transactions = $wpdb->prefix . 'eco_transactions';
        $this->table_categories   = $wpdb->prefix . 'eco_categories';
        register_activation_hook(__FILE__, [$this,'install']);
        add_action('init', [$this,'maybe_install']);
        add_action('admin_menu', [$this,'menu']);
        add_action('admin_post_ecopro_add_tx', [$this,'add_tx']);
        add_action('admin_post_ecopro_update_tx', [$this,'update_tx']);
        add_action('admin_post_ecopro_save_settings', [$this,'save_settings']);
        add_action('admin_post_ecopro_add_category', [$this,'add_category']);
        add_shortcode('economia_dashboard', [$this,'dashboard']);
    }

    public function install(): void { $this->create_or_update_tables(); $this->seed_default_categories(); }
    public function maybe_install(): void { $this->create_or_update_tables(); $this->seed_default_categories(); }

    private function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
    }

    private function column_exists(string $table, string $column): bool {
        global $wpdb;
        return (bool) $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }

    private function create_or_update_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        if (!$this->table_exists($this->table_categories)) {
            $wpdb->query("CREATE TABLE {$this->table_categories} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(191) NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'expense',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY eco_slug (slug),
                KEY eco_type (type)
            ) {$charset}");
        } else {
            if (!$this->column_exists($this->table_categories, 'slug')) {
                $wpdb->query("ALTER TABLE {$this->table_categories} ADD COLUMN slug VARCHAR(191) NULL AFTER name");
            }
            if (!$this->column_exists($this->table_categories, 'type')) {
                $wpdb->query("ALTER TABLE {$this->table_categories} ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT 'expense' AFTER slug");
            }
            if (!$this->column_exists($this->table_categories, 'created_at')) {
                $wpdb->query("ALTER TABLE {$this->table_categories} ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER type");
            }
        }

        if (!$this->table_exists($this->table_transactions)) {
            $wpdb->query("CREATE TABLE {$this->table_transactions} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(20) NOT NULL,
                category_id BIGINT UNSIGNED NULL,
                amount DECIMAL(10,2) NOT NULL,
                description VARCHAR(255) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY eco_type (type),
                KEY eco_category_id (category_id),
                KEY eco_created_at (created_at)
            ) {$charset}");
        } else {
            if (!$this->column_exists($this->table_transactions, 'category_id')) {
                $wpdb->query("ALTER TABLE {$this->table_transactions} ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER type");
            }
            if (!$this->column_exists($this->table_transactions, 'description')) {
                $wpdb->query("ALTER TABLE {$this->table_transactions} ADD COLUMN description VARCHAR(255) NOT NULL DEFAULT '' AFTER amount");
            }
            if (!$this->column_exists($this->table_transactions, 'created_at')) {
                $wpdb->query("ALTER TABLE {$this->table_transactions} ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER description");
            }
        }
    }

    private function unique_slug(string $name, int $exclude_id = 0): string {
        global $wpdb;
        $base = sanitize_title($name);
        if ($base === '') $base = 'categoria';
        $slug = $base;
        $i = 2;
        while ((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->table_categories} WHERE slug = %s AND id != %d", $slug, $exclude_id)) > 0) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    private function backfill_missing_slugs(): void {
        global $wpdb;
        if (!$this->column_exists($this->table_categories, 'slug')) return;
        $rows = $wpdb->get_results("SELECT id, name, slug FROM {$this->table_categories} ORDER BY id ASC");
        if (!$rows) return;
        foreach ($rows as $row) {
            if (!empty($row->slug)) continue;
            $slug = $this->unique_slug((string)$row->name, (int)$row->id);
            $wpdb->update($this->table_categories, ['slug' => $slug], ['id' => (int)$row->id], ['%s'], ['%d']);
        }
    }

    private function seed_default_categories(): void {
        global $wpdb;
        if (!$this->table_exists($this->table_categories)) return;
        $this->backfill_missing_slugs();
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_categories}");
        if ($count > 0) return;
        foreach ([['Salario','income'],['Extra','income'],['Comida','expense'],['Transporte','expense'],['Hogar','expense']] as $cat) {
            $wpdb->insert($this->table_categories, ['name'=>$cat[0],'slug'=>$this->unique_slug($cat[0]),'type'=>$cat[1]], ['%s','%s','%s']);
        }
    }

    public function menu(): void {
        add_menu_page('Economía Pro','Economía','manage_options','eco-pro',[$this,'admin_page'],'dashicons-chart-line',26);
    }

    private function get_totals(): array {
        global $wpdb;
        $income = (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table_transactions} WHERE type='income'");
        $expense = (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$this->table_transactions} WHERE type='expense'");
        return ['income'=>$income,'expense'=>$expense,'balance'=>$income-$expense];
    }

    private function get_categories(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table_categories} ORDER BY type ASC, name ASC");
    }

    private function get_transactions(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT t.*, c.name AS category_name FROM {$this->table_transactions} t LEFT JOIN {$this->table_categories} c ON c.id=t.category_id ORDER BY t.id DESC LIMIT 50");
    }

    private function get_transaction(int $id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_transactions} WHERE id = %d", $id));
    }

    private function get_front_category_summary(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT c.name, t.type, SUM(t.amount) AS total FROM {$this->table_transactions} t INNER JOIN {$this->table_categories} c ON c.id=t.category_id GROUP BY t.category_id,t.type ORDER BY total DESC LIMIT 8");
    }

    private function get_edit_transaction_id(): int {
        return isset($_GET['edit_tx']) ? absint($_GET['edit_tx']) : 0;
    }

    private function current_url_with(array $args = []): string {
        $scheme = is_ssl() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $url = $scheme . '://' . $host . $uri;
        foreach ($args as $k => $v) {
            if ($v === null) $url = remove_query_arg($k, $url);
            else $url = add_query_arg($k, $v, $url);
        }
        return esc_url($url);
    }

    private function get_notice_html(): string {
        $key = isset($_GET['eco_notice']) ? sanitize_text_field(wp_unslash($_GET['eco_notice'])) : '';
        if ($key === '') return '';
        $messages = [
            'category_added'=>['ok','Categoría creada correctamente.'],
            'tx_added'=>['ok','Movimiento guardado correctamente.'],
            'tx_updated'=>['ok','Movimiento actualizado correctamente.'],
            'invalid'=>['err','Faltan datos o no son válidos.'],
            'type_mismatch'=>['err','La categoría no coincide con el tipo de movimiento.'],
            'db_error'=>['err','No se pudo guardar en la base de datos.'],
        ];
        if (!isset($messages[$key])) return '';
        [$kind,$text] = $messages[$key];
        $bg = $kind==='ok' ? '#ecf7ed' : '#fcf0f1';
        $bd = $kind==='ok' ? '#46b450' : '#b32d2e';
        $cl = $kind==='ok' ? '#1e4620' : '#8a2424';
        return '<div style="margin:0 0 16px 0;padding:12px 14px;border-left:4px solid '.$bd.';background:'.$bg.';color:'.$cl.';border-radius:8px;">'.esc_html($text).'</div>';
    }

    public function admin_page(): void {
        if (!current_user_can('manage_options')) wp_die('No autorizado.');
        $totals = $this->get_totals();
        $rows = $this->get_transactions();
        $categories = $this->get_categories();
        $page_id=(int)get_option(self::OPTION_PAGE_ID,0);
        $pages=get_pages(['sort_column'=>'post_title','sort_order'=>'asc']);
        $edit_id = $this->get_edit_transaction_id();
        $edit_tx = $edit_id ? $this->get_transaction($edit_id) : null;
        ?>
        <div class="wrap"><h1>Economía Pro</h1><?php echo $this->get_notice_html(); ?>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:16px;max-width:960px;margin:18px 0 24px 0;">
            <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;"><strong>Ingresos</strong><div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($totals['income'],2,',','.')); ?> €</div></div>
            <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;"><strong>Gastos</strong><div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($totals['expense'],2,',','.')); ?> €</div></div>
            <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;"><strong>Balance</strong><div style="font-size:24px;margin-top:8px;"><?php echo esc_html(number_format($totals['balance'],2,',','.')); ?> €</div></div>
        </div>
        <div style="display:grid;grid-template-columns:minmax(320px,420px) 1fr;gap:24px;align-items:start;max-width:1280px;">
            <div style="display:grid;gap:24px;">
                <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
                    <h2 style="margin-top:0;">Ajustes</h2><p><code>[economia_dashboard]</code></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('ecopro_save_settings'); ?><input type="hidden" name="action" value="ecopro_save_settings">
                    <p><label><strong>Página frontend</strong></label><br><select name="ecopro_front_page" style="width:100%;max-width:360px;"><option value="0">— Sin sincronizar página —</option><?php foreach ($pages as $page): ?><option value="<?php echo esc_attr((string)$page->ID); ?>" <?php selected($page_id,(int)$page->ID); ?>><?php echo esc_html($page->post_title.' (#'.$page->ID.')'); ?></option><?php endforeach; ?></select></p>
                    <p><label><strong>Contraseña del frontend</strong></label><br><input type="password" name="eco_pass" placeholder="Escribe una nueva contraseña" style="width:100%;max-width:360px;"></p>
                    <p><button type="submit" class="button button-primary">Guardar ajustes</button></p></form>
                </div>
                <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
                    <h2 style="margin-top:0;">Categorías</h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;"><?php wp_nonce_field('ecopro_add_category'); ?><input type="hidden" name="action" value="ecopro_add_category"><select name="type"><option value="income">Ingreso</option><option value="expense">Gasto</option></select><input type="text" name="name" placeholder="Nueva categoría" required><button class="button">Añadir</button></form>
                    <table class="widefat striped" style="margin-top:16px;"><thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th></tr></thead><tbody><?php foreach($categories as $cat): ?><tr><td><?php echo (int)$cat->id; ?></td><td><?php echo esc_html($cat->name); ?></td><td><?php echo $cat->type==='income'?'Ingreso':'Gasto'; ?></td></tr><?php endforeach; ?></tbody></table>
                </div>
            </div>
            <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:20px;">
                <h2 style="margin-top:0;"><?php echo $edit_tx ? 'Editar movimiento' : 'Añadir movimiento'; ?></h2>
                <?php if ($edit_tx): ?>
                    <p><a href="<?php echo esc_url(admin_url('admin.php?page=eco-pro')); ?>">Cancelar edición</a></p>
                <?php endif; ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;">
                    <?php if ($edit_tx): wp_nonce_field('ecopro_update_tx'); ?><input type="hidden" name="action" value="ecopro_update_tx"><input type="hidden" name="tx_id" value="<?php echo (int)$edit_tx->id; ?>">
                    <?php else: wp_nonce_field('ecopro_add_tx'); ?><input type="hidden" name="action" value="ecopro_add_tx"><?php endif; ?>
                    <select name="type">
                        <option value="income" <?php selected($edit_tx && $edit_tx->type==='income'); ?>>Ingreso</option>
                        <option value="expense" <?php selected(!$edit_tx || $edit_tx->type==='expense'); ?>>Gasto</option>
                    </select>
                    <select name="category_id" required>
                        <option value="">Categoría</option>
                        <?php foreach($categories as $cat): ?><option value="<?php echo (int)$cat->id; ?>" <?php selected($edit_tx && (int)$edit_tx->category_id === (int)$cat->id); ?>><?php echo esc_html(($cat->type==='income'?'[Ingreso] ':'[Gasto] ').$cat->name); ?></option><?php endforeach; ?>
                    </select>
                    <input type="number" step="0.01" min="0" name="amount" placeholder="Cantidad" value="<?php echo $edit_tx ? esc_attr((string)$edit_tx->amount) : ''; ?>" required>
                    <input type="text" name="description" placeholder="Descripción" value="<?php echo $edit_tx ? esc_attr($edit_tx->description) : ''; ?>" style="min-width:220px;" required>
                    <button class="button button-primary"><?php echo $edit_tx ? 'Actualizar' : 'Guardar'; ?></button>
                </form>
                <h2 style="margin:24px 0 12px;">Movimientos</h2>
                <table class="widefat striped"><thead><tr><th>ID</th><th>Tipo</th><th>Categoría</th><th>Cantidad</th><th>Descripción</th><th>Fecha</th><th>Acción</th></tr></thead><tbody><?php if(!empty($rows)): foreach($rows as $r): ?><tr><td><?php echo (int)$r->id; ?></td><td><?php echo $r->type==='income'?'Ingreso':'Gasto'; ?></td><td><?php echo esc_html($r->category_name ?: '—'); ?></td><td><?php echo esc_html(number_format((float)$r->amount,2,',','.')); ?> €</td><td><?php echo esc_html($r->description); ?></td><td><?php echo esc_html($r->created_at); ?></td><td><a href="<?php echo esc_url(admin_url('admin.php?page=eco-pro&edit_tx='.(int)$r->id)); ?>">Editar</a></td></tr><?php endforeach; else: ?><tr><td colspan="7">No hay movimientos todavía.</td></tr><?php endif; ?></tbody></table>
            </div>
        </div></div><?php
    }

    public function save_settings(): void {
        if (!current_user_can('manage_options')) wp_die('No autorizado.');
        check_admin_referer('ecopro_save_settings');
        $password = isset($_POST['eco_pass']) ? sanitize_text_field(wp_unslash($_POST['eco_pass'])) : '';
        $page_id  = isset($_POST['ecopro_front_page']) ? absint($_POST['ecopro_front_page']) : 0;
        update_option(self::OPTION_PAGE_ID, $page_id, false);
        if ($password !== '') {
            update_option(self::OPTION_PASSWORD, password_hash($password, PASSWORD_DEFAULT), false);
            if ($page_id > 0 && get_post($page_id) instanceof WP_Post) {
                wp_update_post(['ID'=>$page_id,'post_password'=>$password]);
            }
        }
        wp_safe_redirect(admin_url('admin.php?page=eco-pro')); exit;
    }

    public function add_category(): void {
        check_admin_referer('ecopro_add_category');
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        global $wpdb;
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expense';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if (!in_array($type,['income','expense'],true) || $name==='') $this->safe_back_redirect('invalid');
        $this->create_or_update_tables();
        $this->backfill_missing_slugs();
        $result = $wpdb->insert($this->table_categories, ['name'=>$name,'slug'=>$this->unique_slug($name),'type'=>$type], ['%s','%s','%s']);
        if ($result === false) { error_log('economia-pro add_category DB error: '.$wpdb->last_error); $this->safe_back_redirect('db_error'); }
        $this->safe_back_redirect('category_added');
    }

    public function add_tx(): void {
        check_admin_referer('ecopro_add_tx');
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        global $wpdb;
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expense';
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $description = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';
        if (!in_array($type,['income','expense'],true) || $amount<=0 || $description==='' || $category_id<=0) $this->safe_back_redirect('invalid');
        $this->create_or_update_tables();
        $cat_type = $wpdb->get_var($wpdb->prepare("SELECT type FROM {$this->table_categories} WHERE id = %d", $category_id));
        if ($cat_type !== $type) $this->safe_back_redirect('type_mismatch');
        $result = $wpdb->insert($this->table_transactions, ['type'=>$type,'category_id'=>$category_id,'amount'=>$amount,'description'=>$description], ['%s','%d','%f','%s']);
        if ($result === false) { error_log('economia-pro add_tx DB error: '.$wpdb->last_error); $this->safe_back_redirect('db_error'); }
        $this->safe_back_redirect('tx_added');
    }

    public function update_tx(): void {
        check_admin_referer('ecopro_update_tx');
        if (!$this->frontend_or_admin_can_manage()) wp_die('No autorizado.');
        global $wpdb;
        $tx_id = isset($_POST['tx_id']) ? absint($_POST['tx_id']) : 0;
        $type = isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'expense';
        $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
        $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
        $description = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';
        if ($tx_id<=0 || !in_array($type,['income','expense'],true) || $amount<=0 || $description==='' || $category_id<=0) $this->safe_back_redirect('invalid');
        $cat_type = $wpdb->get_var($wpdb->prepare("SELECT type FROM {$this->table_categories} WHERE id = %d", $category_id));
        if ($cat_type !== $type) $this->safe_back_redirect('type_mismatch');
        $result = $wpdb->update($this->table_transactions, ['type'=>$type,'category_id'=>$category_id,'amount'=>$amount,'description'=>$description], ['id'=>$tx_id], ['%s','%d','%f','%s'], ['%d']);
        if ($result === false) { error_log('economia-pro update_tx DB error: '.$wpdb->last_error); $this->safe_back_redirect('db_error'); }
        $this->safe_back_redirect('tx_updated');
    }

    private function frontend_or_admin_can_manage(): bool {
        if (current_user_can('manage_options')) return true;
        return $this->frontend_access_granted();
    }

    private function frontend_access_granted(): bool {
        if (session_status()===PHP_SESSION_NONE && !headers_sent()) session_start();
        return !empty($_SESSION['ecopro_front_ok']);
    }

    private function mark_frontend_access_granted(): void {
        if (session_status()===PHP_SESSION_NONE && !headers_sent()) session_start();
        $_SESSION['ecopro_front_ok']=1;
    }

    private function safe_back_redirect(string $notice=''): void {
        $referer = wp_get_referer();
        if (!$referer) $referer = admin_url('admin.php?page=eco-pro');
        $referer = remove_query_arg('edit_tx', $referer);
        if ($notice !== '') $referer = add_query_arg('eco_notice', $notice, $referer);
        wp_safe_redirect($referer); exit;
    }

    private function front_css(): string {
        return '<style>.ecopro-wrap{max-width:900px;margin:24px auto;padding:24px;background:#fff;border:1px solid #dcdcde;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);color:#1d2327;box-sizing:border-box;width:calc(100% - 24px);}.ecopro-title{margin:0 0 18px 0;color:#1d2327;font-size:36px;line-height:1.1;word-break:break-word;}.ecopro-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:18px;}.ecopro-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin-bottom:16px;}.ecopro-card{background:#fff;border:1px solid #ddd;border-radius:12px;padding:18px;box-sizing:border-box;min-width:0;}.ecopro-form{display:flex;gap:10px;flex-wrap:wrap;}.ecopro-input,.ecopro-select{width:100%;padding:12px 14px;border:1px solid #c3c4c7;border-radius:10px;background:#fff;color:#1d2327;font-size:16px;box-sizing:border-box;min-width:0;}.ecopro-btn{display:inline-block;padding:12px 18px;border:0;border-radius:10px;background:#2271b1;color:#fff;font-weight:600;cursor:pointer;}.ecopro-table-wrap{overflow:auto;-webkit-overflow-scrolling:touch;}.ecopro-table{width:100%;border-collapse:collapse;}.ecopro-table th,.ecopro-table td{text-align:left;padding:10px;border-bottom:1px solid #eee;vertical-align:top;}.ecopro-table th{border-bottom-color:#ddd;}.ecopro-muted{margin:0;color:#50575e;}.ecopro-link{color:#2271b1;text-decoration:none;font-weight:600;}@media (max-width:900px){.ecopro-grid-3,.ecopro-grid-2{grid-template-columns:1fr;}}@media (max-width:640px){.ecopro-wrap{padding:16px;width:calc(100% - 16px);margin:16px auto;}.ecopro-title{font-size:28px;}.ecopro-card{padding:14px;}.ecopro-form{display:grid;grid-template-columns:1fr;gap:10px;}.ecopro-btn{width:100%;}}</style>';
    }

    private function render_front_stats(array $totals): string {
        return '<div class="ecopro-grid-3"><div class="ecopro-card"><strong>Ingresos</strong><div style="font-size:26px;margin-top:8px;">'.esc_html(number_format($totals['income'],2,',','.')).' €</div></div><div class="ecopro-card"><strong>Gastos</strong><div style="font-size:26px;margin-top:8px;">'.esc_html(number_format($totals['expense'],2,',','.')).' €</div></div><div class="ecopro-card"><strong>Balance</strong><div style="font-size:26px;margin-top:8px;">'.esc_html(number_format($totals['balance'],2,',','.')).' €</div></div></div>';
    }

    private function render_front_category_form(): string {
        return '<div class="ecopro-card"><h3 style="margin:0 0 14px 0;color:#1d2327;">Crear categoría</h3><form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ecopro-form">'.wp_nonce_field('ecopro_add_category','_wpnonce',true,false).'<input type="hidden" name="action" value="ecopro_add_category"><select name="type" class="ecopro-select"><option value="income">Ingreso</option><option value="expense">Gasto</option></select><input type="text" name="name" placeholder="Nueva categoría" class="ecopro-input" required><button type="submit" class="ecopro-btn">Añadir</button></form></div>';
    }

    private function render_front_category_list(array $categories): string {
        $html = '<div class="ecopro-card"><h3 style="margin:0 0 12px 0;color:#1d2327;">Categorías disponibles</h3>';
        if (empty($categories)) return $html.'<p class="ecopro-muted">No hay categorías todavía.</p></div>';
        $html .= '<div class="ecopro-table-wrap"><table class="ecopro-table"><thead><tr><th>ID</th><th>Nombre</th><th>Tipo</th></tr></thead><tbody>';
        foreach ($categories as $cat) $html .= '<tr><td>'.(int)$cat->id.'</td><td>'.esc_html($cat->name).'</td><td>'.($cat->type==='income'?'Ingreso':'Gasto').'</td></tr>';
        return $html.'</tbody></table></div></div>';
    }

    private function render_front_tx_form(array $categories, $edit_tx = null): string {
        $options = '<option value="">Categoría</option>';
        foreach ($categories as $cat) {
            $selected = $edit_tx && (int)$edit_tx->category_id === (int)$cat->id ? ' selected' : '';
            $options .= '<option value="'.(int)$cat->id.'"'.$selected.'>'.esc_html(($cat->type==='income'?'[Ingreso] ':'[Gasto] ').$cat->name).'</option>';
        }
        $is_edit = !empty($edit_tx);
        $nonce = $is_edit ? wp_nonce_field('ecopro_update_tx','_wpnonce',true,false) : wp_nonce_field('ecopro_add_tx','_wpnonce',true,false);
        $action = $is_edit ? 'ecopro_update_tx' : 'ecopro_add_tx';
        $button = $is_edit ? 'Actualizar' : 'Guardar';
        $title = $is_edit ? 'Editar movimiento' : 'Añadir movimiento';
        $cancel = $is_edit ? '<p style="margin:0 0 12px 0;"><a class="ecopro-link" href="'.$this->current_url_with(['edit_tx'=>None]).'">Cancelar edición</a></p>' : '';
        $typeIncomeSelected = $is_edit && $edit_tx->type === 'income' ? ' selected' : '';
        $typeExpenseSelected = (!$is_edit || $edit_tx->type === 'expense') ? ' selected' : '';
        $amount = $is_edit ? esc_attr((string)$edit_tx->amount) : '';
        $description = $is_edit ? esc_attr($edit_tx->description) : '';
        $hiddenId = $is_edit ? '<input type="hidden" name="tx_id" value="'.(int)$edit_tx->id.'">' : '';
        return '<div class="ecopro-card"><h3 style="margin:0 0 14px 0;color:#1d2327;">'.$title.'</h3>'.$cancel.'<form method="post" action="'.esc_url(admin_url('admin-post.php')).'" class="ecopro-form">'.$nonce.'<input type="hidden" name="action" value="'.$action.'">'.$hiddenId.'<select name="type" class="ecopro-select"><option value="income"'.$typeIncomeSelected.'>Ingreso</option><option value="expense"'.$typeExpenseSelected.'>Gasto</option></select><select name="category_id" class="ecopro-select" required>'.$options.'</select><input type="number" step="0.01" min="0" name="amount" placeholder="Cantidad" value="'.$amount.'" class="ecopro-input" required><input type="text" name="description" placeholder="Descripción" value="'.$description.'" class="ecopro-input" required><button type="submit" class="ecopro-btn">'.$button.'</button></form></div>';
    }

    private function render_front_category_summary(array $items): string {
        if (empty($items)) return '';
        $html = '<div class="ecopro-card"><h3 style="margin:0 0 12px 0;color:#1d2327;">Estadísticas por categoría</h3><ul style="margin:0;padding-left:18px;color:#50575e;font-size:16px;">';
        foreach ($items as $item) $html .= '<li><strong>'.esc_html($item->name).'</strong> ('.($item->type==='income'?'Ingreso':'Gasto').'): '.esc_html(number_format((float)$item->total,2,',','.')).' €</li>';
        return $html.'</ul></div>';
    }

    private function render_front_recent_transactions(array $rows): string {
        $html = '<div class="ecopro-card"><h3 style="margin:0 0 12px 0;color:#1d2327;">Últimos movimientos</h3>';
        if (empty($rows)) return $html.'<p class="ecopro-muted">No hay movimientos todavía.</p></div>';
        $html .= '<div class="ecopro-table-wrap"><table class="ecopro-table"><thead><tr><th>Tipo</th><th>Categoría</th><th>Cantidad</th><th>Descripción</th><th>Acción</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $edit_url = $this->current_url_with(['edit_tx' => (int)$r->id]);
            $html .= '<tr><td>'.($r->type==='income'?'Ingreso':'Gasto').'</td><td>'.esc_html($r->category_name ?: '—').'</td><td>'.esc_html(number_format((float)$r->amount,2,',','.')).' €</td><td>'.esc_html($r->description).'</td><td><a class="ecopro-link" href="'.$edit_url.'">Editar</a></td></tr>';
        }
        return $html.'</tbody></table></div></div>';
    }

    public function dashboard(): string {
        $stored_hash = (string)get_option(self::OPTION_PASSWORD,'');
        if ($stored_hash==='') return $this->front_css().'<div class="ecopro-wrap"><p style="margin:0;color:#1d2327;">El administrador todavía no ha configurado la contraseña.</p></div>';
        if (isset($_POST['eco_login'])) {
            $password = isset($_POST['eco_pass']) ? (string)wp_unslash($_POST['eco_pass']) : '';
            if (password_verify($password, $stored_hash)) $this->mark_frontend_access_granted();
            else return $this->front_css().'<div class="ecopro-wrap"><p style="margin:0 0 12px 0;color:#b32d2e;font-weight:600;">Contraseña incorrecta.</p><form method="post"><p style="margin:0 0 16px 0;"><input type="password" name="eco_pass" placeholder="Contraseña" class="ecopro-input" required></p><p style="margin:0;"><button type="submit" class="ecopro-btn">Reintentar</button></p><input type="hidden" name="eco_login" value="1"></form></div>';
        }
        if (!$this->frontend_access_granted()) return $this->front_css().'<form method="post" class="ecopro-wrap"><h2 class="ecopro-title">Acceso Economía</h2><p style="margin:0 0 18px 0;color:#50575e;">Introduce tu contraseña para acceder al panel financiero.</p><p style="margin:0 0 16px 0;"><input type="password" name="eco_pass" placeholder="Contraseña" class="ecopro-input" required></p><p style="margin:0;"><button type="submit" class="ecopro-btn">Entrar</button></p><input type="hidden" name="eco_login" value="1"></form>';
        $totals = $this->get_totals();
        $categories = $this->get_categories();
        $rows = $this->get_transactions();
        $summary = $this->get_front_category_summary();
        $edit_id = $this->get_edit_transaction_id();
        $edit_tx = $edit_id ? $this->get_transaction($edit_id) : null;
        return $this->front_css().'<div class="ecopro-wrap"><h2 class="ecopro-title">Dashboard Economía</h2>'.$this->get_notice_html().$this->render_front_stats($totals).'<div class="ecopro-grid-2">'.$this->render_front_category_form().$this->render_front_tx_form($categories, $edit_tx).'</div><div class="ecopro-grid-2">'.$this->render_front_category_list($categories).$this->render_front_category_summary($summary).'</div><div style="margin-top:16px;">'.$this->render_front_recent_transactions($rows).'</div></div>';
    }
}
new EconomiaPro();
}
