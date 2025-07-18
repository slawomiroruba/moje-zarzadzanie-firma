<?php
/**
 * Komponent zakładek aktywności
 * 
 * @package WPMZF
 * @subpackage Admin/Views/Universal/Components
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="wpmzf-activity-box">
    <div class="activity-tabs">
        <button class="tab-link active" data-tab="note">📝 Dodaj notatkę</button>
        <button class="tab-link" data-tab="email">✉️ Wyślij e-mail</button>
    </div>

    <!-- Zakładka notatki -->
    <div id="note-tab-content" class="tab-content active">
        <form id="wpmzf-add-note-form" method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('wpmzf_universal_view_nonce', 'wpmzf_note_security'); ?>
            <input type="hidden" name="<?php echo $config['param_name']; ?>" value="<?php echo esc_attr($object_id); ?>">
            
            <input type="file" id="wpmzf-note-files-input" name="activity_files[]" multiple style="display: none;">

            <div id="wpmzf-note-main-editor">
                <div id="wpmzf-note-editor-placeholder" class="wpmzf-editor-placeholder">
                    <div class="placeholder-text">Opisz co się wydarzyło...</div>
                </div>
                
                <div id="wpmzf-note-editor-container" style="display: none;">
                    <?php
                    wp_editor('', 'wpmzf-note-content', array(
                        'textarea_name' => 'content',
                        'textarea_rows' => 4,
                        'media_buttons' => false,
                        'teeny' => true,
                        'quicktags' => true,
                        'tinymce' => array(
                            'toolbar1' => 'bold,italic,underline,forecolor,bullist,numlist,link,unlink,removeformat,undo,redo',
                            'toolbar2' => '',
                            'height' => 120,
                            'plugins' => 'lists,link,paste,textcolor'
                        )
                    ));
                    ?>
                </div>
            </div>

            <div class="activity-meta-controls">
                <div class="activity-options">
                    <div>
                        <label for="note-type">Typ zdarzenia:</label>
                        <select id="note-type" name="activity_type">
                            <option value="notatka">Notatka</option>
                            <option value="rozmowa">Rozmowa telefoniczna</option>
                            <option value="spotkanie">Spotkanie</option>
                            <option value="inne">Inne</option>
                        </select>
                    </div>
                    <div>
                        <label for="wpmzf-note-date">Data aktywności:</label>
                        <input type="datetime-local" id="wpmzf-note-date" name="activity_date" value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                </div>
                
                <div class="activity-submit-controls">
                    <button type="button" id="wpmzf-note-attach-files-btn" class="button">📎 Załącz pliki</button>
                    <button type="submit" class="button button-primary">Dodaj notatkę</button>
                </div>
            </div>
            
            <div id="wpmzf-note-attachments-preview-container" style="display: none;"></div>
        </form>
    </div>

    <!-- Zakładka e-mail -->
    <div id="email-tab-content" class="tab-content">
        <form id="wpmzf-send-email-form" method="post">
            <?php wp_nonce_field('wpmzf_universal_view_nonce', 'wpmzf_email_security'); ?>
            <input type="hidden" name="<?php echo $config['param_name']; ?>" value="<?php echo esc_attr($object_id); ?>">
            
            <div class="email-fields">
                <div class="email-field">
                    <label for="email-to">Do:</label>
                    <input type="email" id="email-to" name="email_to" required>
                </div>
                
                <div class="email-field">
                    <label for="email-subject">Temat:</label>
                    <input type="text" id="email-subject" name="email_subject" required>
                </div>
                
                <div class="email-field">
                    <label for="email-content">Wiadomość:</label>
                    <?php
                    wp_editor('', 'email-content', array(
                        'textarea_name' => 'content',
                        'textarea_rows' => 6,
                        'media_buttons' => false,
                        'teeny' => true,
                        'quicktags' => true
                    ));
                    ?>
                </div>
                
                <div class="email-advanced-fields" style="display: none;">
                    <div class="email-field">
                        <label for="email-cc">DW:</label>
                        <input type="text" id="email-cc" name="email_cc">
                    </div>
                    
                    <div class="email-field">
                        <label for="email-bcc">UDW:</label>
                        <input type="text" id="email-bcc" name="email_bcc">
                    </div>
                </div>
                
                <div class="email-controls">
                    <button type="button" id="toggle-email-advanced" class="button">Pokaż zaawansowane</button>
                    <button type="submit" class="button button-primary">Wyślij e-mail</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Główny formularz aktywności (ukryty, używany przez JavaScript) -->
<div style="display: none;">
    <form id="wpmzf-add-activity-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('wpmzf_universal_view_nonce', 'security'); ?>
        <input type="hidden" name="<?php echo $config['param_name']; ?>" value="<?php echo esc_attr($object_id); ?>">
        
        <textarea id="wpmzf-activity-content" name="content"></textarea>
        
        <select id="wpmzf-activity-type" name="activity_type">
            <option value="notatka">Notatka</option>
            <option value="rozmowa">Rozmowa telefoniczna</option>
            <option value="spotkanie">Spotkanie</option>
            <option value="email">E-mail</option>
            <option value="inne">Inne</option>
        </select>
        
        <input type="datetime-local" id="wpmzf-activity-date" name="activity_date">
        
        <!-- Pola dla emaili -->
        <input type="email" name="email_to" placeholder="Do">
        <input type="text" name="email_cc" placeholder="DW">
        <input type="text" name="email_bcc" placeholder="UDW">
        <input type="text" name="email_subject" placeholder="Temat">
        
        <button type="submit">Dodaj aktywność</button>
        <button type="button" id="wpmzf-note-attach-files-btn">Załącz pliki</button>
        <input type="file" id="wpmzf-note-files-input" name="activity_files[]" multiple style="display: none;">
        
        <div id="wpmzf-note-attachments-preview-container"></div>
    </form>
</div>

<style>
    .activity-tabs {
        display: flex;
        border-bottom: 1px solid #e1e5e9;
        margin-bottom: 16px;
    }

    .tab-link {
        background: none;
        border: none;
        padding: 12px 16px;
        cursor: pointer;
        font-size: 14px;
        color: #646970;
        border-bottom: 2px solid transparent;
        transition: all 0.2s ease;
    }

    .tab-link:hover {
        color: #2271b1;
        background: #f6f7f7;
    }

    .tab-link.active {
        color: #2271b1;
        border-bottom-color: #2271b1;
        background: #f6f7f7;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .activity-meta-controls {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-top: 16px;
        gap: 16px;
    }

    .activity-options {
        display: flex;
        gap: 16px;
        flex: 1;
    }

    .activity-options > div {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .activity-options label {
        font-size: 12px;
        font-weight: 600;
        color: #1d2327;
    }

    .activity-options select,
    .activity-options input {
        padding: 6px 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
    }

    .activity-submit-controls {
        display: flex;
        gap: 8px;
        align-items: flex-end;
    }

    .email-fields {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .email-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .email-field label {
        font-weight: 600;
        color: #1d2327;
    }

    .email-field input {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .email-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 16px;
    }

    .wpmzf-editor-placeholder {
        background: #f9f9f9;
        border: 2px dashed #ddd;
        border-radius: 6px;
        padding: 20px;
        text-align: center;
        color: #646970;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .wpmzf-editor-placeholder:hover {
        border-color: #2271b1;
        background: #f6f7f7;
    }

    .placeholder-text {
        font-style: italic;
    }
</style>
