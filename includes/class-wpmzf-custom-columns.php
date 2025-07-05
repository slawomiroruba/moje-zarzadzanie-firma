<?php
// Plik: moje-zarzadzanie-firma/includes/class-wpmzf-custom-columns.php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ta klasa-serwis obsługuje całą integrację z Admin Columns.
 * To jest wzorzec zaczerpnięty bezpośrednio z dodatków do wtyczki.
 */
class WPMZF_Custom_Columns_Service implements AC\Registerable {

    /**
     * Ta metoda jest wywoływana przez Admin Columns.
     * Podpinamy tutaj nasze funkcje do odpowiednich haków.
     */
    public function register(): void {
        // Hak do rejestrowania naszej grupy kolumn (dla porządku)
        add_action( 'ac/column_groups', [ $this, 'register_column_group' ] );
        
        // Hak do rejestrowania naszych własnych typów kolumn
        add_action( 'ac/column_types', [ $this, 'register_column_types' ] );
    }
    
    /**
     * Rejestruje naszą niestandardową grupę w menu wyboru kolumn.
     */
    public function register_column_group( AC\Groups $groups ): void {
        $groups->add( 'wpmzf_custom', 'Własne Kolumny Firmy', 15 );
    }

    /**
     * Rejestruje wszystkie nasze niestandardowe kolumny.
     * Ta funkcja jest wywoływana dla każdego ekranu w panelu admina.
     */
    public function register_column_types( AC\ListScreen $list_screen ): void {
        // Sprawdzamy, czy jesteśmy na ekranie listy dla typu posta 'employee'
        if ( $list_screen instanceof AC\ListScreenPost && 'employee' === $list_screen->get_post_type() ) {
            
            // Rejestrujemy naszą kolumnę "Funkcja/Shortcode"
            $list_screen->register_column_type( new WPMZF_Column_FunctionShortcode() );

            // Rejestrujemy naszą kolumnę "Status Kontaktu"
            $list_screen->register_column_type( new WPMZF_Column_LastContact() );

            // Rejestrujemy naszą kolumnę "PHP Snippet"
            $list_screen->register_column_type( new WPMZF_Column_PhpSnippet() );
            
            // Tutaj w przyszłości możesz dodawać kolejne kolumny
        }
    }
}


/**
 * Definicja naszej kolumny "Funkcja/Shortcode".
 */
class WPMZF_Column_FunctionShortcode extends AC\Column {

    public function __construct() {
        $this->set_type( 'wpmzf_function_shortcode' );
        $this->set_group( 'wpmzf_custom' );
    }

    public function get_defaults() {
        return [ 'label' => 'Funkcja/Shortcode' ];
    }
    
    public function get_value( $id ) {
        $template = $this->get_setting( 'template' )->get_value();

        if ( ! $template ) {
            return $this->get_empty_char();
        }
        
        // Zastąp placeholder {ID} rzeczywistym ID
        $content_to_execute = str_replace( '{ID}', $id, $template );
        
        // Sprawdź czy to shortcode czy funkcja PHP
        if ( strpos( $content_to_execute, '[' ) === 0 && strpos( $content_to_execute, ']' ) !== false ) {
            // To jest shortcode
            $value = do_shortcode( $content_to_execute );
        } else {
            // To jest funkcja PHP
            if ( function_exists( trim( explode( '(', $content_to_execute )[0] ) ) ) {
                ob_start();
                try {
                    $value = eval( 'return ' . $content_to_execute . ';' );
                    if ( ob_get_length() > 0 ) {
                        $output = ob_get_clean();
                        $value = $output . $value;
                    } else {
                        ob_end_clean();
                    }
                } catch ( Throwable $e ) {
                    ob_end_clean();
                    return '<span style="color: red;">Błąd: ' . esc_html( $e->getMessage() ) . '</span>';
                }
            } else {
                return '<span style="color: orange;">Funkcja nie istnieje</span>';
            }
        }
        
        return empty( $value ) ? $this->get_empty_char() : $value;
    }

    public function register_settings() {
        $this->add_setting( new WPMZF_Column_Template_Setting( $this ) );
    }
}


/**
 * Definicja naszej kolumny "Status Kontaktu".
 */
class WPMZF_Column_LastContact extends AC\Column {
    
    public function __construct() {
        $this->set_type( 'wpmzf_last_contact' );
        $this->set_group( 'wpmzf_custom' );
    }
    
    public function get_defaults() {
        return [ 'label' => 'Status Kontaktu' ];
    }

    public function get_value( $id ) {
        $comments = get_comments( [ 'post_id' => $id, 'number'  => 1, 'status'  => 'approve' ] );
        if ( empty( $comments ) ) return '<span style="color: #d63638; font-weight: bold;">Brak notatek!</span>';
        $days_diff = floor( ( time() - strtotime( $comments[0]->comment_date ) ) / ( 60*60*24 ) );

        if ( $days_diff <= 7 ) return '<span style="color: #2271b1;">Aktywny (' . $days_diff . ' dni temu)</span>';
        if ( $days_diff <= 30 ) return '<span>Kontakt ' . $days_diff . ' dni temu</span>';
        return '<span style="color: #d63638;">Wymaga uwagi (> 30 dni)</span>';
    }
}

/**
 * Definicja naszej kolumny "PHP Snippet".
 */
class WPMZF_Column_PhpSnippet extends AC\Column {
    
    public function __construct() {
        $this->set_type( 'wpmzf_php_snippet' );
        $this->set_group( 'wpmzf_custom' );
    }
    
    public function get_defaults() {
        return [ 'label' => 'PHP Snippet' ];
    }

    public function get_value( $id ) {
        $php_code = $this->get_setting( 'php_snippet' )->get_value();

        if ( ! $php_code ) {
            return $this->get_empty_char();
        }

        // Zapewniamy dostęp do zmiennych $id, $post, $user
        $post = get_post( $id );
        $user = wp_get_current_user();

        ob_start();
        try {
            eval( '?>' . $php_code );
        } catch ( Throwable $e ) {
            // W przypadku błędu, wyświetlamy go w kolumnie
            return '<span style="color: red;">Błąd: ' . esc_html( $e->getMessage() ) . '</span>';
        }
        $value = ob_get_clean();
        
        return empty( $value ) ? $this->get_empty_char() : $value;
    }

    public function register_settings() {
        $setting = new AC\Settings\Setting\Textarea(
            $this,
            'php_snippet',
            'PHP Snippet',
            'Wpisz kod PHP do wykonania. Możesz użyć zmiennych <code>$id</code>, <code>$post</code>, <code>$user</code>. Pamiętaj, aby użyć <code>echo</code> lub <code>return</code> wewnątrz kodu, aby wyświetlić wynik.'
        );
        $this->add_setting( $setting );
    }
}

/**
 * Klasa ustawienia dla szablonu funkcji/shortcode.
 */
class WPMZF_Column_Template_Setting extends AC\Settings\Column {

    protected function define_options() {
        return [ 'template' ];
    }

    public function create_view() {
        $setting = $this->create_element( 'textarea' )
                        ->set_rows( 3 )
                        ->set_placeholder( 'Przykład: [my_shortcode id="{ID}"] lub my_function({ID})' );

        $view = new AC\View( [
            'label'   => __( 'Szablon funkcji/shortcode', 'wpmzf' ),
            'tooltip' => __( 'Wpisz shortcode lub funkcję PHP. Użyj {ID} jako placeholder dla ID wpisu.', 'wpmzf' ),
            'setting' => $setting,
        ] );

        return $view;
    }

    /**
     * @return string
     */
    public function get_template() {
        return $this->template;
    }

    /**
     * @param string $template
     * @return bool
     */
    public function set_template( $template ) {
        $this->template = $template;
        return true;
    }
}