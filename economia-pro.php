
<?php
/**
 * Plugin Name: Economia Pro
 * Description: Sistema financiero doméstico.
 * Version: 2.0
 * Author: Loki
 */

if (!defined('ABSPATH')) exit;

class EconomiaPro {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . "eco_transactions";

        register_activation_hook(__FILE__, [$this,"install"]);
        add_action("admin_menu", [$this,"menu"]);
        add_shortcode("economia_dashboard", [$this,"dashboard"]);
        add_action("admin_post_ecopro_add_tx", [$this,"add_tx"]);
    }

    public function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(20) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        require_once(ABSPATH . "wp-admin/includes/upgrade.php");
        dbDelta($sql);
    }

    public function menu() {
        add_menu_page(
            "Economía Pro",
            "Economía",
            "manage_options",
            "eco-pro",
            [$this,"admin"],
            "dashicons-chart-line",
            26
        );
    }

    public function admin() {
        global $wpdb;
        $rows = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY id DESC LIMIT 50");
        ?>

        <div class="wrap">
        <h1>Economía Pro</h1>

        <h2>Añadir movimiento</h2>

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="ecopro_add_tx">

            <select name="type">
                <option value="income">Ingreso</option>
                <option value="expense">Gasto</option>
            </select>

            <input type="number" step="0.01" name="amount" placeholder="Cantidad">

            <input type="text" name="description" placeholder="Descripción">

            <button class="button button-primary">Guardar</button>
        </form>

        <h2>Movimientos</h2>

        <table class="widefat striped">
        <thead>
        <tr>
            <th>ID</th>
            <th>Tipo</th>
            <th>Cantidad</th>
            <th>Descripción</th>
            <th>Fecha</th>
        </tr>
        </thead>
        <tbody>

        <?php foreach($rows as $r): ?>
        <tr>
            <td><?php echo $r->id; ?></td>
            <td><?php echo esc_html($r->type); ?></td>
            <td><?php echo number_format($r->amount,2); ?> €</td>
            <td><?php echo esc_html($r->description); ?></td>
            <td><?php echo $r->created_at; ?></td>
        </tr>
        <?php endforeach; ?>

        </tbody>
        </table>

        </div>

        <?php
    }

    public function add_tx() {
        if (!current_user_can("manage_options")) wp_die();

        global $wpdb;

        $wpdb->insert(
            $this->table,
            [
                "type" => sanitize_text_field($_POST["type"]),
                "amount" => floatval($_POST["amount"]),
                "description" => sanitize_text_field($_POST["description"])
            ]
        );

        wp_redirect(admin_url("admin.php?page=eco-pro"));
        exit;
    }

    public function dashboard() {
        global $wpdb;

        $income = $wpdb->get_var("SELECT SUM(amount) FROM {$this->table} WHERE type='income'");
        $expense = $wpdb->get_var("SELECT SUM(amount) FROM {$this->table} WHERE type='expense'");

        $income = $income ?: 0;
        $expense = $expense ?: 0;
        $balance = $income - $expense;

        ob_start();
        ?>

        <div style="max-width:720px;margin:40px auto;padding:30px;background:#fff;border-radius:14px;">
            <h2>Dashboard Economía</h2>

            <p><strong>Ingresos:</strong> <?php echo number_format($income,2); ?> €</p>
            <p><strong>Gastos:</strong> <?php echo number_format($expense,2); ?> €</p>
            <p><strong>Balance:</strong> <?php echo number_format($balance,2); ?> €</p>
        </div>

        <?php
        return ob_get_clean();
    }

}

new EconomiaPro();
