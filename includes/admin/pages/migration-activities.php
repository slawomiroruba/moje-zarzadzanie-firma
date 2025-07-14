<?php

/**
 * Strona administracyjna do migracji aktywności
 *
 * @package WPMZF
 * @subpackage Admin/Pages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Sprawdź czy klasa migracji istnieje
if (!class_exists('WPMZF_Activity_Migration')) {
    require_once WPMZF_PLUGIN_PATH . 'includes/utils/class-wpmzf-activity-migration.php';
}

// Obsługa akcji
$action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
$migration_report = null;

if ($action && wp_verify_nonce($_POST['_wpnonce'], 'wpmzf_migration_' . $action)) {
    
    switch ($action) {
        case 'migrate':
            $migration_report = WPMZF_Activity_Migration::migrate_activities();
            break;
        
        case 'rollback':
            $migration_report = WPMZF_Activity_Migration::rollback_migration();
            break;
    }
}

// Sprawdź status migracji
$is_migration_needed = WPMZF_Activity_Migration::is_migration_needed();
$activities_to_migrate = WPMZF_Activity_Migration::count_activities_to_migrate();

?>

<div class="wrap">
    <h1>Migracja Aktywności - Ujednolicenie Relacji</h1>
    
    <div class="notice notice-info">
        <p><strong>Informacja:</strong> Ta strona pozwala na migrację aktywności ze starych, rozdzielonych pól relacji 
        (<code>related_person</code>, <code>related_company</code>, <code>related_project</code>, <code>related_task</code>) 
        na nowe, ujednolicone pole <code>related_objects</code>.</p>
    </div>

    <!-- Status migracji -->
    <div class="postbox">
        <h2 class="hndle">Status Migracji</h2>
        <div class="inside">
            <?php if ($is_migration_needed): ?>
                <div class="notice notice-warning inline">
                    <p><strong>Migracja wymagana!</strong> Znaleziono <strong><?php echo $activities_to_migrate; ?></strong> aktywności używających starych pól relacji.</p>
                </div>
            <?php else: ?>
                <div class="notice notice-success inline">
                    <p><strong>Migracja nie jest potrzebna.</strong> Wszystkie aktywności używają już nowego systemu relacji.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Akcje migracji -->
    <?php if ($is_migration_needed): ?>
    <div class="postbox">
        <h2 class="hndle">Migracja Aktywności</h2>
        <div class="inside">
            <p>Kliknij poniższy przycisk, aby migrować wszystkie aktywności na nowy system relacji:</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('wpmzf_migration_migrate'); ?>
                <input type="hidden" name="action" value="migrate">
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" 
                           value="Migruj Aktywności (<?php echo $activities_to_migrate; ?>)"
                           onclick="return confirm('Czy na pewno chcesz przeprowadzić migrację? Zalecane jest wcześniejsze utworzenie kopii zapasowej bazy danych.');">
                </p>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rollback (tylko jeśli nie ma aktywności do migracji) -->
    <?php if (!$is_migration_needed): ?>
    <div class="postbox">
        <h2 class="hndle">Rollback Migracji</h2>
        <div class="inside">
            <p><strong>Uwaga:</strong> Rollback usuwa nowe pola <code>related_objects</code> ze wszystkich aktywności. 
            Działa tylko jeśli stare pola (<code>related_person</code>, etc.) nie zostały usunięte.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('wpmzf_migration_rollback'); ?>
                <input type="hidden" name="action" value="rollback">
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-secondary" 
                           value="Cofnij Migrację"
                           onclick="return confirm('Czy na pewno chcesz cofnąć migrację? To usunie nowe pola related_objects ze wszystkich aktywności!');">
                </p>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Raport migracji -->
    <?php if ($migration_report): ?>
    <div class="postbox">
        <h2 class="hndle">Raport Migracji</h2>
        <div class="inside">
            
            <?php if ($action === 'migrate'): ?>
                <h3>Wyniki Migracji</h3>
                <p><strong>Łącznie aktywności:</strong> <?php echo $migration_report['total_activities']; ?></p>
                <p><strong>Zmigrowano:</strong> <?php echo $migration_report['migrated_activities']; ?></p>
                <p><strong>Błędy:</strong> <?php echo count($migration_report['errors']); ?></p>
                
                <?php if ($migration_report['migrated_activities'] > 0): ?>
                    <div class="notice notice-success inline">
                        <p>Migracja zakończona pomyślnie!</p>
                    </div>
                <?php endif; ?>
            
            <?php elseif ($action === 'rollback'): ?>
                <h3>Wyniki Rollbacku</h3>
                <p><strong>Łącznie aktywności:</strong> <?php echo $migration_report['total_activities']; ?></p>
                <p><strong>Cofnięto:</strong> <?php echo $migration_report['rolled_back_activities']; ?></p>
                <p><strong>Błędy:</strong> <?php echo count($migration_report['errors']); ?></p>
                
                <?php if ($migration_report['rolled_back_activities'] > 0): ?>
                    <div class="notice notice-success inline">
                        <p>Rollback zakończony pomyślnie!</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Błędy -->
            <?php if (!empty($migration_report['errors'])): ?>
                <h4>Błędy:</h4>
                <ul style="background: #fff; border: 1px solid #ccd0d4; padding: 10px;">
                    <?php foreach ($migration_report['errors'] as $error): ?>
                        <li style="color: #d63638;"><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Szczegółowy raport -->
            <?php if (!empty($migration_report['details'])): ?>
                <h4>Szczegóły:</h4>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tytuł Aktywności</th>
                            <th>Status</th>
                            <?php if ($action === 'migrate'): ?>
                                <th>Zmigrowane Obiekty</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($migration_report['details'] as $detail): ?>
                            <tr>
                                <td><?php echo esc_html($detail['activity_id']); ?></td>
                                <td><?php echo esc_html($detail['activity_title']); ?></td>
                                <td>
                                    <?php if ($detail['status'] === 'success'): ?>
                                        <span style="color: #00a32a;">✓ Sukces</span>
                                    <?php else: ?>
                                        <span style="color: #d63638;">✗ Błąd</span>
                                        <?php if (isset($detail['error'])): ?>
                                            <br><small><?php echo esc_html($detail['error']); ?></small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php if ($action === 'migrate' && isset($detail['migrated_objects'])): ?>
                                    <td>
                                        <?php if (!empty($detail['migrated_objects'])): ?>
                                            <?php foreach ($detail['migrated_objects'] as $object_id): ?>
                                                <?php echo esc_html(get_the_title($object_id) . ' (ID: ' . $object_id . ')'); ?><br>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Informacje techniczne -->
    <div class="postbox">
        <h2 class="hndle">Informacje Techniczne</h2>
        <div class="inside">
            <h4>Co robi migracja?</h4>
            <ul>
                <li>Pobiera wszystkie aktywności z polami <code>related_person</code>, <code>related_company</code>, <code>related_project</code>, <code>related_task</code></li>
                <li>Tworzy nowe pole <code>related_objects</code> zawierające tablicę wszystkich powiązanych ID</li>
                <li>Zachowuje stare pola na wypadek konieczności rollbacku</li>
                <li>Aktualizuje logikę aplikacji do używania nowego pola</li>
            </ul>

            <h4>Korzyści z nowego systemu:</h4>
            <ul>
                <li><strong>Elastyczność:</strong> Jedna aktywność może być powiązana z wieloma obiektami różnych typów</li>
                <li><strong>Prostota:</strong> Jeden sposób obsługi relacji zamiast czterech oddzielnych</li>
                <li><strong>Dwukierunkowość:</strong> Łatwiejsze znajdowanie aktywności dla danego obiektu</li>
                <li><strong>Skalowalność:</strong> Łatwe dodawanie nowych typów obiektów w przyszłości</li>
            </ul>
        </div>
    </div>
</div>
