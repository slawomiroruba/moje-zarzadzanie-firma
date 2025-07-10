<?php
/**
 * Widok Dashboard
 *
 * @package WPMZF
 * @subpackage Admin/Views
 */

if (!defined('ABSPATH')) {
    exit;
}

// Pobieranie danych do dashboardu
$companies_count = wp_count_posts('company')->publish;
$persons_count = wp_count_posts('person')->publish;
$projects_count = wp_count_posts('project')->publish;
$time_entries_count = wp_count_posts('time_entry')->publish;

// Pobieranie ostatnich aktywności - bezpieczne zapytanie
$recent_activities_query = new WP_Query([
    'post_type' => 'activity',
    'posts_per_page' => 10,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC'
]);
$recent_activities = $recent_activities_query->posts;

// Statystyki użytkowników - dodane dzisiaj
$today_users = get_users([
    'date_query' => [
        [
            'year' => date('Y'),
            'month' => date('m'),
            'day' => date('d')
        ]
    ]
]);

// Statystyki osób - dodane dzisiaj
$today_persons = new WP_Query([
    'post_type' => 'person',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'date_query' => [
        [
            'year' => date('Y'),
            'month' => date('m'),
            'day' => date('d')
        ]
    ]
]);

// Statystyki osób - dodane w tym tygodniu
$this_week_persons = new WP_Query([
    'post_type' => 'person',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'date_query' => [
        [
            'year' => date('Y'),
            'week' => date('W')
        ]
    ]
]);

// Statystyki osób - dodane w tym miesiącu
$this_month_persons = new WP_Query([
    'post_type' => 'person',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'date_query' => [
        [
            'year' => date('Y'),
            'month' => date('m')
        ]
    ]
]);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="luna-crm-dashboard">
        <!-- Statystyki -->
        <div class="luna-crm-stats-grid">
            <div class="luna-crm-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-building"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $companies_count; ?></h3>
                    <p>Firm</p>
                </div>
            </div>
            
            <div class="luna-crm-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $persons_count; ?></h3>
                    <p>Osób</p>
                </div>
            </div>
            
            <div class="luna-crm-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-portfolio"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $projects_count; ?></h3>
                    <p>Projektów</p>
                </div>
            </div>
            
            <div class="luna-crm-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $time_entries_count; ?></h3>
                    <p>Wpisów czasu</p>
                </div>
            </div>
        </div>
        
        <!-- Statystyki osób z podziałem na okresy -->
        <div class="luna-crm-stats-grid">
            <div class="luna-crm-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $today_persons->found_posts; ?></h3>
                    <p>Osób dodanych dzisiaj</p>
                </div>
            </div>
            
            <div class="luna-crm-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calendar"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $this_week_persons->found_posts; ?></h3>
                    <p>Osób dodanych w tym tygodniu</p>
                </div>
            </div>
            
            <div class="luna-crm-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo $this_month_persons->found_posts; ?></h3>
                    <p>Osób dodanych w tym miesiącu</p>
                </div>
            </div>
            
            <div class="luna-crm-stat-card">
                <div class="stat-icon">
                    <span class="dashicons dashicons-admin-users"></span>
                </div>
                <div class="stat-content">
                    <h3><?php echo count($today_users); ?></h3>
                    <p>Nowych użytkowników dzisiaj</p>
                </div>
            </div>
        </div>
        
        <!-- Główny obszar -->
        <div class="luna-crm-main-content">
            <div class="luna-crm-column">
                <!-- Tracker czasu -->
                <div class="luna-crm-card">
                    <h3>Tracker czasu</h3>
                    <div id="time-tracker">
                        <div class="timer-display">
                            <span id="timer-time">00:00:00</span>
                        </div>
                        <div class="timer-controls">
                            <select id="timer-project">
                                <option value="">Wybierz projekt</option>
                                <?php
                                $projects_query = new WP_Query([
                                    'post_type' => 'project',
                                    'posts_per_page' => -1,
                                    'post_status' => 'publish'
                                ]);
                                if ($projects_query->have_posts()) {
                                    while ($projects_query->have_posts()) {
                                        $projects_query->the_post();
                                        echo '<option value="' . get_the_ID() . '">' . esc_html(get_the_title()) . '</option>';
                                    }
                                    wp_reset_postdata();
                                }
                                ?>
                            </select>
                            <input type="text" id="timer-description" placeholder="Opis zadania">
                            <button id="timer-start" class="button button-primary">Start</button>
                            <button id="timer-stop" class="button" style="display: none;">Stop</button>
                        </div>
                    </div>
                </div>
                
                <!-- Ostatnie aktywności -->
                <div class="luna-crm-card">
                    <h3>Ostatnie aktywności</h3>
                    <div class="activities-list">
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <span class="dashicons dashicons-admin-generic"></span>
                                    </div>
                                    <div class="activity-content">
                                        <p><?php echo esc_html($activity->post_content); ?></p>
                                        <small><?php echo esc_html($activity->post_date); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Brak aktywności</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="luna-crm-column">
                <!-- Projekty w toku -->
                <div class="luna-crm-card">
                    <h3>Projekty w toku</h3>
                    <div class="projects-list">
                        <?php
                        $active_projects = WPMZF_Project::get_projects_by_status('active');
                        if (!empty($active_projects)):
                            foreach ($active_projects as $project):
                                $stats = $time_service->get_project_time_stats($project->id);
                        ?>
                            <div class="project-item">
                                <h4><?php echo esc_html($project->name); ?></h4>
                                <div class="project-stats">
                                    <span>Czas: <?php echo $stats['total_hours']; ?>h</span>
                                    <span>Budżet: <?php echo esc_html($project->budget); ?></span>
                                </div>
                                <div class="project-progress">
                                    <div class="progress-bar" style="width: 60%;"></div>
                                </div>
                            </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <p>Brak aktywnych projektów</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Szybkie akcje -->
                <div class="luna-crm-card">
                    <h3>Szybkie akcje</h3>
                    <div class="quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=luna-crm-companies'); ?>" class="button">
                            <span class="dashicons dashicons-building"></span> Dodaj firmę
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=luna-crm-persons'); ?>" class="button">
                            <span class="dashicons dashicons-groups"></span> Dodaj osobę
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=luna-crm-projects'); ?>" class="button">
                            <span class="dashicons dashicons-portfolio"></span> Nowy projekt
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Inicjalizacja trackera czasu
    $('#timer-start').click(function() {
        var projectId = $('#timer-project').val();
        var description = $('#timer-description').val();
        
        if (!projectId) {
            alert('Wybierz projekt');
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmzf_start_timer',
                project_id: projectId,
                description: description,
                nonce: '<?php echo wp_create_nonce("wpmzf_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#timer-start').hide();
                    $('#timer-stop').show();
                    startTimerDisplay();
                }
            }
        });
    });
    
    $('#timer-stop').click(function() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmzf_stop_timer',
                nonce: '<?php echo wp_create_nonce("wpmzf_nonce"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#timer-start').show();
                    $('#timer-stop').hide();
                    stopTimerDisplay();
                    location.reload();
                }
            }
        });
    });
    
    // Sprawdź czy timer jest aktywny
    checkTimerStatus();
    
    function checkTimerStatus() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wpmzf_get_timer_status',
                nonce: '<?php echo wp_create_nonce("wpmzf_nonce"); ?>'
            },
            success: function(response) {
                if (response.success && response.data.active) {
                    $('#timer-start').hide();
                    $('#timer-stop').show();
                    $('#timer-project').val(response.data.project_id);
                    $('#timer-description').val(response.data.description);
                    startTimerDisplay(response.data.elapsed);
                }
            }
        });
    }
    
    var timerInterval;
    var timerSeconds = 0;
    
    function startTimerDisplay(initialSeconds = 0) {
        timerSeconds = initialSeconds;
        timerInterval = setInterval(function() {
            timerSeconds++;
            updateTimerDisplay();
        }, 1000);
    }
    
    function stopTimerDisplay() {
        clearInterval(timerInterval);
        timerSeconds = 0;
        updateTimerDisplay();
    }
    
    function updateTimerDisplay() {
        var hours = Math.floor(timerSeconds / 3600);
        var minutes = Math.floor((timerSeconds % 3600) / 60);
        var seconds = timerSeconds % 60;
        
        var display = pad(hours) + ':' + pad(minutes) + ':' + pad(seconds);
        $('#timer-time').text(display);
    }
    
    function pad(num) {
        return num < 10 ? '0' + num : num;
    }
});
</script>
