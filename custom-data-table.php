<?php
/**
 * Plugin Name: Enhanced Data Tables
 * Description: Admin creates multiple data tables (with images and text per cell) and displays them via shortcode.
 * Version: 2.0
 * Author: Tajwar
 */

if (!defined('ABSPATH')) exit;

class EnhancedDataTablesPlugin {

    private $option_key = 'cdt_all_tables_v2';

    public function __construct() {
        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_action('admin_init', [$this, 'handle_post_request']);
        add_action('admin_init', [$this, 'handle_table_delete']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_shortcode('custom_data_table', [$this, 'render_table_shortcode']);
    }

    public function enqueue_admin_scripts() {
        wp_enqueue_media();
        wp_enqueue_style('cdt-admin-style', plugin_dir_url(__FILE__) . 'admin-style.css?v='.time());
        wp_enqueue_script('cdt-media-uploader', plugin_dir_url(__FILE__) . 'uploader.js?v='.time(), ['jquery'], null, true);
        wp_enqueue_script('cdt-dynamic-editor', plugin_dir_url(__FILE__) . 'table-editor.js?v='.time(), ['jquery'], null, true);
    }

    public function create_admin_menu() {
        add_menu_page('Enhanced Tables', 'Data Tables', 'manage_options', 'cdt_main_menu', [$this, 'list_tables_page'], 'dashicons-editor-table', 20);
        add_submenu_page('cdt_main_menu', 'Add/Edit Table', 'Add New', 'manage_options', 'cdt_add_new', [$this, 'add_edit_table_page']);
    }

    public function list_tables_page() {
        $tables = get_option($this->option_key, []);
        ?>
        <div class="wrap">
            <h1>All Tables <a href="?page=cdt_add_new" class="page-title-action">Add New</a></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th>Table ID</th><th>Rows</th><th>Cols</th><th>Shortcode</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($tables as $id => $table): ?>
                        <tr>
                            <td><?php echo esc_html($id); ?></td>
                            <td><?php echo esc_html($table['rows']); ?></td>
                            <td><?php echo esc_html($table['cols']); ?></td>
                            <td>[custom_data_table id="<?php echo esc_html($id); ?>"]</td>
                            <td>
                                <a href="?page=cdt_add_new&edit=<?php echo esc_attr($id); ?>">Edit</a> |
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cdt_main_menu&delete=' . urlencode($id)), 'cdt_delete_table'); ?>"
                                onclick="return confirm('Are you sure you want to delete this table?');"
                                style="color:red;">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function add_edit_table_page() {
        $tables = get_option($this->option_key, []);
        $editing = isset($_GET['edit']) ? sanitize_text_field($_GET['edit']) : '';
        $table = $editing && isset($tables[$editing]) ? $tables[$editing] : ['rows' => 3, 'cols' => 3, 'cells' => []];

        ?>
        <div class="wrap">
            <h1><?php echo $editing ? "Edit Table: $editing" : "Add New Table"; ?></h1>
            <form method="post" id="cdt-table-form">
                <?php wp_nonce_field('cdt_save_table'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Table ID</th>
                        <td>
                            <?php
                            if ($editing) {
                                $id_to_show = esc_html($editing);
                            } elseif (!empty($_GET['edit'])) {
                                $id_to_show = esc_html($_GET['edit']);
                            } else {
                                // Try to preview next available ID
                                $tables = get_option($this->option_key, []);
                                $i = 1;
                                do {
                                    $id_to_show = 'table_' . $i;
                                    $i++;
                                } while (isset($tables[$id_to_show]));
                            }
                            ?>
                            <strong><?php echo $id_to_show; ?></strong>
                            <input type="hidden" name="cdt_table_id" value="<?php echo esc_attr($editing ?: $id_to_show); ?>">
                            <p class="description">Used in shortcode: <code>[custom_data_table id="<?php echo esc_html($id_to_show); ?>"]</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th>Custom Table Class</th>
                        <td>
                            <input type="text" name="cdt_custom_class" value="<?php echo esc_attr($table['class'] ?? ''); ?>" placeholder="e.g. my-table">
                            <p class="description">Optional CSS class to apply to this table on the frontend.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Custom CSS</th>
                        <td>
                            <textarea name="cdt_custom_css" rows="15" style="width:100%;" placeholder="e.g. .my-table-class td { background: #f0f0f0; }"><?php echo esc_textarea($table['css'] ?? ''); ?></textarea>
                            <p class="description">Optional CSS that will be included on the frontend. You must include a selector (e.g. a class).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Table Heading</th>
                        <td>
                            <input type="text" name="cdt_heading" value="<?php echo esc_attr($table['heading'] ?? ''); ?>" style="width: 60%;">
                            <p class="description">This will be displayed above the table on the frontend.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Rows</th>
                        <td><input type="number" id="cdt-rows" name="cdt_rows" value="<?php echo esc_attr($table['rows']); ?>" min="1" required></td>
                    </tr>
                    <tr>
                        <th>Columns</th>
                        <td>
                            <input type="number" id="cdt-cols" name="cdt_cols" value="<?php echo esc_attr($table['cols']); ?>" min="1" required>
                            <button type="button" class="button" id="cdt-update-grid">Update</button>
                        </td>
                    </tr>
                </table>

                <h2>Cell Content</h2>
                <div id="cdt-cell-grid">
                    <?php
                    for ($i = 0; $i < $table['rows']; $i++) {
                        echo "<div class='cdt-row' style='display:flex;'>";
                        for ($j = 0; $j < $table['cols']; $j++) {
                            $cell = $table['cells'][$i][$j] ?? ['text' => '', 'image' => ''];
                            $text = esc_attr($cell['text'] ?? '');
                            $image = esc_url($cell['image'] ?? '');
                            $field = "cdt_cells[$i][$j]";
                            echo "<div class='cdt-cell styled-cell'>
                                <div class='cdt-image-group'>
                                    <label>Image</label>
                                    <input type='hidden' name='{$field}[image]' class='cdt-image-url' value='{$image}'>
                                    <button class='button select-cdt-image'>Upload</button>
                                    <div class='cdt-preview' style='margin-top:5px;'>
                                        " . ($image ? "<img src='{$image}' style='max-width:100px; display:block;'>" : '') . "
                                        " . ($image ? "<button class='button button-small remove-cdt-image' style='margin-top:5px;'>X</button>" : '') . "
                                    </div>
                                </div>
                                <div class='cdt-text-group'>
                                    <label>Text</label>
                                    <textarea name='{$field}[text]' rows='2'>{$text}</textarea>
                                </div>
                            </div>";
                        }
                        echo "</div>";
                    }
                    ?>
                </div>
                <p><input type="submit" class="button-primary" value="Save Table"></p>
            </form>
        </div>
        <?php
    }

    public function handle_post_request() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cdt_table_id']) && check_admin_referer('cdt_save_table')) {
            $tables = get_option($this->option_key, []);
            $id = sanitize_text_field($_POST['cdt_table_id'] ?? '');

            if (empty($id)) {
                // Auto-incrementing ID
                $i = 1;
                do {
                    $id = 'table_' . $i;
                    $i++;
                } while (isset($tables[$id]));
            }
            $rows = max(1, intval($_POST['cdt_rows']));
            $cols = max(1, intval($_POST['cdt_cols']));
            $heading = sanitize_text_field($_POST['cdt_heading'] ?? '');
            $class = sanitize_html_class($_POST['cdt_custom_class'] ?? '');
            $css = wp_strip_all_tags($_POST['cdt_custom_css'] ?? '');
            $cells_input = $_POST['cdt_cells'];

            $cells = [];
            for ($i = 0; $i < $rows; $i++) {
                for ($j = 0; $j < $cols; $j++) {
                    $img = esc_url_raw($cells_input[$i][$j]['image'] ?? '');
                    $txt = sanitize_text_field($cells_input[$i][$j]['text'] ?? '');
                    $cells[$i][$j] = ['image' => $img, 'text' => $txt];
                }
            }

            $tables = get_option($this->option_key, []);
            $tables[$id] = ['rows' => $rows, 'cols' => $cols, 'heading' => $heading, 'cells' => $cells, 'class' => $class, 'css' => $css];
            update_option($this->option_key, $tables);

            wp_redirect(admin_url('admin.php?page=cdt_add_new&edit=' . urlencode($id) . '&updated=1'));
            exit;
        }
    }

    public function handle_table_delete() {
        if (isset($_GET['delete']) && current_user_can('manage_options') && check_admin_referer('cdt_delete_table')) {
            $id = sanitize_text_field($_GET['delete']);
            $tables = get_option($this->option_key, []);

            if (isset($tables[$id])) {
                unset($tables[$id]);
                update_option($this->option_key, $tables);
                wp_redirect(admin_url('admin.php?page=cdt_main_menu&deleted=1'));
                exit;
            }
        }
    }

    public function render_table_shortcode($atts) {
        $atts = shortcode_atts(['id' => ''], $atts);
        $id = sanitize_text_field($atts['id']);
        $tables = get_option($this->option_key, []);

        if (!isset($tables[$id])) return "<p>Table not found.</p>";

        $table = $tables[$id];
        $class = esc_attr($table['class'] ?? '');
        $css = trim($table['css'] ?? '');

        $html = '';

        if (!empty($css)) {
            $html .= "<style>{$css}</style>";
        }
        $heading_html = '';
        if (!empty($table['heading'])) {
            $heading_html = "<h3 class='{$class}-heading'>" . esc_html($table['heading']) . "</h3>";
        }
        $html .= "<table class='{$class}' cellpadding='10' style='border-collapse: collapse; text-align:center;'>";

        // Header
        $html .= '<thead><tr>';
        for ($j = 0; $j < $table['cols']; $j++) {
            $cell = $table['cells'][0][$j] ?? ['image' => '', 'text' => ''];
            $image = esc_url($cell['image']);
            $text = esc_html($cell['text']);
            
            $content = '';
            if (!empty($image)) {
                $content .= "<img src='{$image}' style='max-width:100px; height:auto; display:block; margin:0 auto;'>";
            }
            if (!empty($text)) {
                $content .= "<div>{$text}</div>";
            }

            $html .= "<th>{$content}</th>";
        }
        $html .= '</tr></thead>';

        // Body
        $html .= '<tbody>';
        for ($i = 1; $i < $table['rows']; $i++) {
            $html .= '<tr>';
            for ($j = 0; $j < $table['cols']; $j++) {
                $cell = $table['cells'][$i][$j] ?? ['image' => '', 'text' => ''];
                $image = esc_url($cell['image']);
                $text = esc_html($cell['text']);

                $content = '';
                if (!empty($image)) {
                    $content .= "<img src='{$image}' style='max-width:100px; height:auto; display:block; margin:0 auto;'>";
                }
                if (!empty($text)) {
                    $content .= "<div>{$text}</div>";
                }

                $html .= "<td>{$content}</td>";
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';

        $html .= '</table>';

        return $heading_html . $html;
    }
}

new EnhancedDataTablesPlugin();
