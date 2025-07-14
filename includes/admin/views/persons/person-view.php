<?php
/**
 * Widok Osoby - używa logiki z class-wpmzf-admin-pages.php
 * 
 * @package WPMZF
 * @subpackage Admin/Views
 */

// Ten plik jest pusty, ponieważ cała logika widoku osoby
// została przeniesiona do metody render_single_person_page()
// w klasie WPMZF_Admin_Pages

if (!defined('ABSPATH')) {
    exit;
}

// Automatyczne przekierowanie do metody render_single_person_page
// jeśli przypadkowo trafimy na ten plik
if (class_exists('WPMZF_Admin_Pages')) {
    $admin_pages = new WPMZF_Admin_Pages();
    $admin_pages->render_single_person_page();
}
