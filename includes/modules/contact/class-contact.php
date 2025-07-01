<?php

require_once WPMZF_PLUGIN_PATH . 'includes/core/modules/class-wpmzf-erp-object.php';

class WPMZF_Contact extends WPMZF_ERP_Object {
    protected $object_type = 'contact';
    protected $post_type = 'wpmzf_contact';

    public function __construct($object_id = null) {
        parent::__construct($this->object_type, $object_id);
    }

    // Rejestracja Custom Post Type
    public function register_cpt() {
        $labels = [
            'name' => __('Contacts', 'moje-zarzadzanie-firma'),
            'singular_name' => __('Contact', 'moje-zarzadzanie-firma'),
        ];
        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'custom-fields'],
            'show_in_menu' => true,
        ];
        register_post_type($this->post_type, $args);
    }
}
