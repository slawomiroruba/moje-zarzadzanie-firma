<?php

/**
 * Klasa ładująca wszystkie komponenty wtyczki
 *
 * @package WPMZF
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPMZF_Loader {

    /**
     * Tablica zarejestrowanych akcji
     */
    protected $actions;

    /**
     * Tablica zarejestrowanych filtrów
     */
    protected $filters;

    /**
     * Konstruktor klasy
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Dodaje akcję do tablicy akcji
     *
     * @param string $hook Nazwa hooka
     * @param object $component Komponent
     * @param string $callback Nazwa metody
     * @param int $priority Priorytet
     * @param int $accepted_args Liczba argumentów
     */
    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Dodaje filtr do tablicy filtrów
     *
     * @param string $hook Nazwa hooka
     * @param object $component Komponent
     * @param string $callback Nazwa metody
     * @param int $priority Priorytet
     * @param int $accepted_args Liczba argumentów
     */
    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    /**
     * Dodaje hook do tablicy
     *
     * @param array $hooks Tablica hooków
     * @param string $hook Nazwa hooka
     * @param object $component Komponent
     * @param string $callback Nazwa metody
     * @param int $priority Priorytet
     * @param int $accepted_args Liczba argumentów
     * @return array
     */
    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );

        return $hooks;
    }

    /**
     * Rejestruje wszystkie akcje i filtry
     */
    public function run() {
        foreach ($this->filters as $hook) {
            add_filter($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }

        foreach ($this->actions as $hook) {
            add_action($hook['hook'], array($hook['component'], $hook['callback']), $hook['priority'], $hook['accepted_args']);
        }
    }
}
