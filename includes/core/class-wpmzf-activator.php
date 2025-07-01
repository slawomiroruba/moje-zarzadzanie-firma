<?php
class WPMZF_Activator {
    public static function activate() {
        flush_rewrite_rules();
    }
}