<div class="wrap">
    <h1 class="wp-heading-inline">Klienci</h1>
    <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=add'); ?>" class="page-title-action">
        Dodaj nowego klienta
    </a>
    
    <hr class="wp-header-end">
    
    <?php
    $time_tracker = time_tracker_pro();
    $clients = $time_tracker->get_database()->get_clients(false);
    ?>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Nazwa firmy</th>
                <th>NIP</th>
                <th>Email</th>
                <th>Telefon</th>
                <th>Status</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clients as $client): ?>
            <tr>
                <td><?php echo esc_html($client->company_name); ?></td>
                <td><?php echo esc_html($client->nip); ?></td>
                <td><?php echo esc_html($client->email); ?></td>
                <td><?php echo esc_html($client->phone); ?></td>
                <td>
                    <span class="status-indicator <?php echo $client->active ? 'active' : 'inactive'; ?>">
                        <?php echo $client->active ? 'Aktywny' : 'Nieaktywny'; ?>
                    </span>
                </td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=edit&id=' . $client->id); ?>" 
                       class="button button-small">Edytuj</a>
                    <a href="<?php echo admin_url('admin.php?page=time-tracker-clients-services&client_id=' . $client->id); ?>" 
                       class="button button-small">Usługi</a>
                    <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=delete&id=' . $client->id); ?>" 
                       class="button button-small button-danger" 
                       onclick="return confirm('Czy na pewno chcesz usunąć tego klienta?');">Usuń</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.status-indicator {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}
.status-indicator.active {
    background: #d4edda;
    color: #155724;
}
.status-indicator.inactive {
    background: #f8d7da;
    color: #721c24;
}
.button-danger {
    color: #721c24;
    border-color: #f5c6cb;
    background: #f8d7da;
}
</style>