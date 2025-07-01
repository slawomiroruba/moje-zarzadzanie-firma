<?php

class WPMZF_ERP_Object {
    protected $object_type; // Typ obiektu, np. 'client', 'contractor', 'employee'
    protected $object_id;   // ID obiektu w bazie danych
    protected $post_type;   // CPT slug

    public function __construct($object_type, $object_id = null) {
        $this->object_type = $object_type;
        $this->object_id = $object_id;
        $this->post_type = 'wpmzf_' . $object_type;

        add_action('init', [$this, 'register_cpt']);
        add_action('add_meta_boxes', [$this, 'register_metaboxes']);
        add_action('save_post_' . $this->post_type, [$this, 'save_metabox_data']);
    }

    // Rejestracja Custom Post Type
    public function register_cpt() {
        $labels = [
            'name' => ucfirst($this->object_type) . 's',
            'singular_name' => ucfirst($this->object_type),
        ];
        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor'],
            'show_in_menu' => true,
        ];
        register_post_type($this->post_type, $args);
    }

    // Rejestracja metaboxów
    public function register_metaboxes() {
        add_meta_box(
            $this->post_type . '_details',
            ucfirst($this->object_type) . ' Details',
            [$this, 'render_metabox'],
            $this->post_type,
            'normal',
            'default'
        );
    }

    // Renderowanie metaboxa
    public function render_metabox($post) {
        // Przykładowe pole
        $value = get_post_meta($post->ID, '_wpmzf_field', true);
        echo '<label for="wpmzf_field">Pole przykładowe:</label>';
        echo '<input type="text" id="wpmzf_field" name="wpmzf_field" value="' . esc_attr($value) . '" />';
        wp_nonce_field('wpmzf_save_metabox', 'wpmzf_metabox_nonce');
    }

    // Zapis danych z metaboxa
    public function save_metabox_data($post_id) {
        if (!isset($_POST['wpmzf_metabox_nonce']) || !wp_verify_nonce($_POST['wpmzf_metabox_nonce'], 'wpmzf_save_metabox')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (isset($_POST['wpmzf_field'])) {
            update_post_meta($post_id, '_wpmzf_field', sanitize_text_field($_POST['wpmzf_field']));
        }
    }

    // Przykładowa metoda do pobierania danych
    public function get_data() {
        // ...
    }

    // Przykładowa metoda do zapisywania danych
    public function save_data($data) {
        // ...
    }

    // Inne metody specyficzne dla obiektów ERP
}