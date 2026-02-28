<?php
if (!current_user_can('manage_clients')) {
    wp_die(__('Brak uprawnień.', 'time-tracker'));
}

$time_tracker = time_tracker_pro();
$client_manager = $time_tracker->get_client_manager();

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

switch ($action) {
    case 'add':
    case 'edit':
        $this->client_edit_page($id);
        break;
    case 'delete':
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_client_' . $id)) {
            $client_manager->delete_client($id);
            wp_redirect(admin_url('admin.php?page=time-tracker-clients&message=deleted'));
            exit;
        }
        break;
    case 'services':
        $this->client_services_page($id);
        break;
    default:
        $this->clients_list_page();
}

function client_edit_page($client_id = 0) {
    global $client_manager;
    
    $client = null;
    if ($client_id > 0) {
        $client = $client_manager->get_client($client_id);
    }
    
    // Obsługa formularza
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_client'])) {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'save_client')) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        $result = $client_manager->save_client($_POST, $client_id);
        
        if ($result) {
            $message = $client_id ? 'updated' : 'created';
            wp_redirect(admin_url('admin.php?page=time-tracker-clients&message=' . $message));
            exit;
        }
    }
    ?>
    <div class="wrap">
        <h1><?php echo $client_id ? 'Edytuj klienta' : 'Dodaj nowego klienta'; ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('save_client'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="company_name">Nazwa firmy *</label></th>
                    <td>
                        <input type="text" name="company_name" id="company_name" 
                               value="<?php echo $client ? esc_attr($client->company_name) : ''; ?>" 
                               class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="nip">NIP *</label></th>
                    <td>
                        <input type="text" name="nip" id="nip" 
                               value="<?php echo $client ? esc_attr($client->nip) : ''; ?>" 
                               class="regular-text" required>
                        <p class="description">Numer NIP klienta</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="address">Adres</label></th>
                    <td>
                        <textarea name="address" id="address" rows="3" class="regular-text"><?php 
                            echo $client ? esc_textarea($client->address) : ''; 
                        ?></textarea>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="email">Email</label></th>
                    <td>
                        <input type="email" name="email" id="email" 
                               value="<?php echo $client ? esc_attr($client->email) : ''; ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="phone">Telefon</label></th>
                    <td>
                        <input type="text" name="phone" id="phone" 
                               value="<?php echo $client ? esc_attr($client->phone) : ''; ?>" 
                               class="regular-text">
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="active">Status</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="active" id="active" value="1" 
                                <?php echo (!$client || $client->active) ? 'checked' : ''; ?>>
                            Aktywny klient
                        </label>
                        <p class="description">Nieaktywni klienci nie będą wyświetlani przy dodawaniu wpisów</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_client" class="button button-primary" value="Zapisz klienta">
                <a href="<?php echo admin_url('admin.php?page=time-tracker-clients'); ?>" class="button">Anuluj</a>
            </p>
        </form>
    </div>
    <?php
}

function clients_list_page() {
    global $client_manager;
    
    $clients = $client_manager->get_clients(false);
    
    // Komunikat o sukcesie
    $messages = array(
        'created' => 'Klient został dodany.',
        'updated' => 'Klient został zaktualizowany.',
        'deleted' => 'Klient został usunięty.'
    );
    
    if (isset($_GET['message']) && isset($messages[$_GET['message']])) {
        echo '<div class="notice notice-success is-dismissible"><p>' . $messages[$_GET['message']] . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Klienci</h1>
        <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=add'); ?>" class="page-title-action">
            Dodaj nowego klienta
        </a>
        
        <hr class="wp-header-end">
        
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
                        <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=services&id=' . $client->id); ?>" 
                           class="button button-small">Usługi</a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-clients&action=delete&id=' . $client->id), 'delete_client_' . $client->id); ?>" 
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
    <?php
}

function client_services_page($client_id) {
    global $client_manager;
    
    $client = $client_manager->get_client($client_id);
    $services = $client_manager->get_client_services($client_id);
    
    // Obsługa dodawania/edycji usługi
    $service_action = isset($_GET['service_action']) ? $_GET['service_action'] : '';
    $service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;
    
    if ($service_action === 'edit' || $service_action === 'add') {
        $this->service_edit_page($client_id, $service_id);
        return;
    }
    
    if ($service_action === 'delete' && $service_id) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'delete_service_' . $service_id)) {
            $client_manager->delete_service($service_id);
            wp_redirect(admin_url('admin.php?page=time-tracker-clients&action=services&id=' . $client_id . '&message=deleted'));
            exit;
        }
    }
    ?>
    <div class="wrap">
        <h1>Usługi dla klienta: <?php echo esc_html($client->company_name); ?></h1>
        
        <p>
            <a href="<?php echo admin_url('admin.php?page=time-tracker-clients'); ?>" class="button">← Powrót do listy klientów</a>
            <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=services&id=' . $client_id . '&service_action=add'); ?>" class="button button-primary">Dodaj nową usługę</a>
        </p>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Nazwa usługi</th>
                    <th>Stawka godzinowa</th>
                    <th>Typ</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                <tr>
                    <td><?php echo esc_html($service->service_name); ?></td>
                    <td>
                        <?php if ($service->is_fixed_price): ?>
                            <em>Usługa ryczałtowa</em>
                        <?php else: ?>
                            <?php echo number_format($service->hourly_rate, 2); ?> zł/h
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $service->is_fixed_price ? 'Ryczałt' : 'Godzinowa'; ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=services&id=' . $client_id . '&service_action=edit&service_id=' . $service->id); ?>" 
                           class="button button-small">Edytuj</a>
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-clients&action=services&id=' . $client_id . '&service_action=delete&service_id=' . $service->id), 'delete_service_' . $service->id); ?>" 
                           class="button button-small button-danger" 
                           onclick="return confirm('Czy na pewno chcesz usunąć tę usługę?');">Usuń</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function service_edit_page($client_id, $service_id = 0) {
    global $client_manager;
    
    $service = null;
    if ($service_id > 0) {
        $service = $client_manager->get_service($service_id);
    }
    
    // Obsługa formularza
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_service'])) {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'save_service')) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        $_POST['client_id'] = $client_id;
        $result = $client_manager->save_service($_POST, $service_id);
        
        if ($result) {
            $message = $service_id ? 'updated' : 'created';
            wp_redirect(admin_url('admin.php?page=time-tracker-clients&action=services&id=' . $client_id . '&message=' . $message));
            exit;
        }
    }
    
    $client = $client_manager->get_client($client_id);
    ?>
    <div class="wrap">
        <h1><?php echo $service_id ? 'Edytuj usługę' : 'Dodaj nową usługę'; ?> dla klienta: <?php echo esc_html($client->company_name); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('save_service'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="service_name">Nazwa usługi *</label></th>
                    <td>
                        <input type="text" name="service_name" id="service_name" 
                               value="<?php echo $service ? esc_attr($service->service_name) : ''; ?>" 
                               class="regular-text" required>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="is_fixed_price">Typ usługi</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="is_fixed_price" id="is_fixed_price" value="1" 
                                <?php echo ($service && $service->is_fixed_price) ? 'checked' : ''; ?>
                                onchange="toggleRateField()">
                            Usługa ryczałtowa (bez stawki godzinowej)
                        </label>
                    </td>
                </tr>
                
                <tr id="hourly_rate_row" style="<?php echo ($service && $service->is_fixed_price) ? 'display: none;' : ''; ?>">
                    <th scope="row"><label for="hourly_rate">Stawka godzinowa (zł/h) *</label></th>
                    <td>
                        <input type="number" name="hourly_rate" id="hourly_rate" 
                               value="<?php echo $service ? esc_attr($service->hourly_rate) : ''; ?>" 
                               class="regular-text" step="0.01" min="0">
                        <p class="description">Wpisz stawkę godzinową dla tej usługi</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_service" class="button button-primary" value="Zapisz usługę">
                <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=services&id=' . $client_id); ?>" class="button">Anuluj</a>
            </p>
        </form>
    </div>
    
    <script>
    function toggleRateField() {
        var isFixed = document.getElementById('is_fixed_price').checked;
        var rateRow = document.getElementById('hourly_rate_row');
        
        if (isFixed) {
            rateRow.style.display = 'none';
            document.getElementById('hourly_rate').required = false;
        } else {
            rateRow.style.display = '';
            document.getElementById('hourly_rate').required = true;
        }
    }
    </script>
    <?php
}

// Wywołanie odpowiedniej funkcji
if (function_exists($action . '_page')) {
    call_user_func($action . '_page', $id);
}