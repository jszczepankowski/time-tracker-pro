<?php
if (!current_user_can('view_all_time_entries')) {
    wp_die(__('Brak uprawnień.', 'time-tracker'));
}

$time_tracker = time_tracker_pro();
$database = $time_tracker->get_database();
$time_entry = $time_tracker->get_time_entry();

// Filtry
$status = isset($_GET['status']) ? $_GET['status'] : '';
$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Argumenty dla zapytania
$args = array(
    'status' => $status ? $status : null,
    'employee_id' => $employee_id ? $employee_id : null,
    'client_id' => $client_id ? $client_id : null,
    'date_from' => $date_from ? $date_from : null,
    'date_to' => $date_to ? $date_to : null,
    'limit' => 50
);

$entries = $database->get_time_entries($args);

// Pobierz listę pracowników i klientów dla filtrów
$employees = get_users(array(
    'role__in' => array('author', 'contributor', 'time_tracker_manager'),
    'fields' => array('ID', 'display_name')
));

$clients = $database->get_clients(true);

// Obsługa akcji
$action = isset($_GET['action']) ? $_GET['action'] : '';
$entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;

if ($action === 'approve' && $entry_id) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'approve_entry_' . $entry_id)) {
        $current_user = wp_get_current_user();
        $time_entry->approve_entry($entry_id, $current_user->ID);
        wp_redirect(admin_url('admin.php?page=time-tracker-entries&message=approved'));
        exit;
    }
}

if ($action === 'reject' && $entry_id) {
    if (wp_verify_nonce($_GET['_wpnonce'], 'reject_entry_' . $entry_id)) {
        // Tutaj można dodać formularz do podania powodu odrzucenia
        // Na razie przekierowujemy z powrotem
        wp_redirect(admin_url('admin.php?page=time-tracker-entries&message=rejected'));
        exit;
    }
}

// Komunikat o sukcesie
$messages = array(
    'approved' => 'Wpis został zatwierdzony.',
    'rejected' => 'Wpis został odrzucony.'
);

if (isset($_GET['message']) && isset($messages[$_GET['message']])) {
    echo '<div class="notice notice-success is-dismissible"><p>' . $messages[$_GET['message']] . '</p></div>';
}
?>
<div class="wrap">
    <h1>Wpisy godzinowe</h1>
    
    <div class="time-tracker-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="time-tracker-entries">
            
            <div class="filter-row">
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status">
                        <option value="">Wszystkie</option>
                        <option value="draft" <?php selected($status, 'draft'); ?>>Szkic</option>
                        <option value="submitted" <?php selected($status, 'submitted'); ?>>Zgłoszony</option>
                        <option value="approved" <?php selected($status, 'approved'); ?>>Zatwierdzony</option>
                        <option value="rejected" <?php selected($status, 'rejected'); ?>>Odrzucony</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="employee_id">Pracownik:</label>
                    <select name="employee_id" id="employee_id">
                        <option value="">Wszyscy</option>
                        <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee->ID; ?>" <?php selected($employee_id, $employee->ID); ?>>
                            <?php echo esc_html($employee->display_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="client_id">Klient:</label>
                    <select name="client_id" id="client_id">
                        <option value="">Wszyscy</option>
                        <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client->id; ?>" <?php selected($client_id, $client->id); ?>>
                            <?php echo esc_html($client->company_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="filter-row">
                <div class="filter-group">
                    <label for="date_from">Od:</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_to">Do:</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($date_to); ?>">
                </div>
                
                <div class="filter-group">
                    <input type="submit" class="button" value="Filtruj">
                    <a href="<?php echo admin_url('admin.php?page=time-tracker-entries'); ?>" class="button">Wyczyść filtry</a>
                </div>
            </div>
        </form>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Data</th>
                <th>Pracownik</th>
                <th>Klient</th>
                <th>Usługa</th>
                <th>Godziny/Kwota</th>
                <th>Opis</th>
                <th>Status</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($entries as $entry): ?>
            <tr>
                <td><?php echo date('d.m.Y', strtotime($entry->entry_date)); ?></td>
                <td><?php echo esc_html($entry->employee_name); ?></td>
                <td><?php echo esc_html($entry->client_name); ?></td>
                <td><?php echo esc_html($entry->service_name); ?></td>
                <td>
                    <?php if ($entry->is_fixed_price): ?>
                        <?php echo number_format($entry->fixed_amount, 2); ?> zł
                    <?php else: ?>
                        <?php echo number_format($entry->hours, 2); ?> h
                        <br><small><?php echo number_format($entry->hourly_rate, 2); ?> zł/h</small>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html(wp_trim_words($entry->description, 5)); ?></td>
                <td>
                    <span class="status-badge status-<?php echo $entry->status; ?>">
                        <?php 
                        $status_labels = array(
                            'draft' => 'Szkic',
                            'submitted' => 'Zgłoszony',
                            'approved' => 'Zatwierdzony',
                            'rejected' => 'Odrzucony'
                        );
                        echo $status_labels[$entry->status];
                        ?>
                    </span>
                </td>
                <td>
                    <?php if ($entry->status === 'submitted' && current_user_can('approve_time_entries')): ?>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-entries&action=approve&entry_id=' . $entry->id), 'approve_entry_' . $entry->id); ?>" 
                           class="button button-small button-success">Zatwierdź</a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-entries&action=reject&entry_id=' . $entry->id), 'reject_entry_' . $entry->id); ?>" 
                           class="button button-small button-danger">Odrzuć</a>
                    <?php endif; ?>
                    
                    <?php if (current_user_can('manage_time_tracker')): ?>
                        <a href="#" class="button button-small">Edytuj</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.time-tracker-filters {
    background: #fff;
    padding: 15px;
    border: 1px solid #ccd0d4;
    margin: 20px 0;
}
.filter-row {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
}
.filter-row:last-child {
    margin-bottom: 0;
}
.filter-group {
    display: flex;
    align-items: center;
    gap: 5px;
}
.filter-group label {
    font-weight: bold;
    min-width: 60px;
}
.filter-group select,
.filter-group input[type="date"] {
    padding: 5px;
}
.button-success {
    background: #5cb85c;
    border-color: #4cae4c;
    color: white;
}
.button-success:hover {
    background: #449d44;
    border-color: #398439;
}
.button-danger {
    background: #d9534f;
    border-color: #d43f3a;
    color: white;
}
.button-danger:hover {
    background: #c9302c;
    border-color: #ac2925;
}
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}
.status-draft { background: #f0ad4e; color: white; }
.status-submitted { background: #5bc0de; color: white; }
.status-approved { background: #5cb85c; color: white; }
.status-rejected { background: #d9534f; color: white; }
</style>