<?php
if (!current_user_can('manage_time_tracker')) {
    wp_die(__('Brak uprawnień.', 'time-tracker'));
}

$time_tracker = time_tracker_pro();
$database = $time_tracker->get_database();

// Statystyki
$clients_count = count($database->get_clients(true));
$entries_count = $database->get_time_entries(array('limit' => 10000));
$entries_count = count($entries_count);

$pending_entries = $database->get_time_entries(array('status' => 'submitted', 'limit' => 10000));
$pending_count = count($pending_entries);

$current_month_entries = $database->get_time_entries(array(
    'date_from' => date('Y-m-01'),
    'date_to' => date('Y-m-t'),
    'limit' => 10000
));
$current_month_count = count($current_month_entries);
?>
<div class="wrap">
    <h1>Time Tracker - Panel Główny</h1>
    
    <div class="time-tracker-widgets">
        <div class="widget">
            <h3><span class="dashicons dashicons-businessman"></span> Klienci</h3>
            <div class="widget-content">
                <p class="stat-number"><?php echo $clients_count; ?></p>
                <p class="stat-label">Aktywnych klientów</p>
            </div>
            <div class="widget-footer">
                <a href="<?php echo admin_url('admin.php?page=time-tracker-clients'); ?>">Zarządzaj klientami →</a>
            </div>
        </div>
        
        <div class="widget">
            <h3><span class="dashicons dashicons-clock"></span> Wpisy</h3>
            <div class="widget-content">
                <p class="stat-number"><?php echo $entries_count; ?></p>
                <p class="stat-label">Wszystkich wpisów</p>
                <p class="stat-sub">W tym miesiącu: <?php echo $current_month_count; ?></p>
            </div>
            <div class="widget-footer">
                <a href="<?php echo admin_url('admin.php?page=time-tracker-entries'); ?>">Przeglądaj wpisy →</a>
            </div>
        </div>
        
        <div class="widget">
            <h3><span class="dashicons dashicons-yes"></span> Do zatwierdzenia</h3>
            <div class="widget-content">
                <p class="stat-number"><?php echo $pending_count; ?></p>
                <p class="stat-label">Wpisów oczekujących</p>
            </div>
            <div class="widget-footer">
                <a href="<?php echo admin_url('admin.php?page=time-tracker-entries&status=submitted'); ?>">Zatwierdź wpisy →</a>
            </div>
        </div>
    </div>
    
    <div class="recent-activity">
        <h2>Ostatnia aktywność</h2>
        <?php
        $recent_entries = $database->get_time_entries(array('limit' => 10));
        
        if ($recent_entries): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Pracownik</th>
                    <th>Klient</th>
                    <th>Usługa</th>
                    <th>Godziny/Kwota</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_entries as $entry): ?>
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
                        <?php endif; ?>
                    </td>
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
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>Brak ostatniej aktywności.</p>
        <?php endif; ?>
    </div>
</div>

<style>
.time-tracker-widgets {
    display: flex;
    gap: 20px;
    margin: 20px 0;
}
.widget {
    flex: 1;
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    border-radius: 4px;
}
.widget h3 {
    margin: 0;
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e5e5e5;
    font-size: 14px;
}
.widget h3 .dashicons {
    margin-right: 5px;
    vertical-align: middle;
}
.widget-content {
    padding: 20px;
    text-align: center;
}
.stat-number {
    font-size: 36px;
    font-weight: bold;
    margin: 0;
    color: #0073aa;
}
.stat-label {
    font-size: 14px;
    color: #666;
    margin: 5px 0;
}
.stat-sub {
    font-size: 12px;
    color: #999;
    margin: 5px 0 0;
}
.widget-footer {
    padding: 10px 15px;
    background: #f8f9fa;
    border-top: 1px solid #e5e5e5;
    text-align: right;
}
.widget-footer a {
    text-decoration: none;
    color: #0073aa;
    font-size: 13px;
}
.recent-activity {
    margin-top: 30px;
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