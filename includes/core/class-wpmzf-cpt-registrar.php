<?php
class WPMZF_CPT_Registrar {
    public function __construct() {
        add_action('init', array($this, 'register_all_cpts'));
    }

    public function register_all_cpts() {
        // Automatyczne ładowanie definicji CPT z każdego modułu
        $modules_dir = WPMZF_PLUGIN_PATH . 'includes/modules/';
        foreach (glob($modules_dir . '*/', GLOB_ONLYDIR) as $module_dir) {
            $cpt_file = $module_dir . basename($module_dir) . '-cpt.php';
            if (file_exists($cpt_file)) {
                require_once $cpt_file;
            }
        }
    }
}