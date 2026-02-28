<?php
if (!current_user_can('generate_reports')) {
    wp_die(__('Brak uprawnień.', 'time-tracker'));
}

$time_tracker = time_tracker_pro();
$report_generator = $time_tracker->get_report_generator();
$client_manager = $time_tracker->get_client_manager();

// Pobierz klientów
$clients = $client_manager->get_clients(true);

// Obsługa formularza
$report_data = null;
$selected_client = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

if ($selected_client && $selected_month && $selected_year) {
    $report = $report_generator->generate_monthly_report($selected_client, $selected_month, $selected_year);
    
    if ($report['success']) {
        $report_data = $report['data'];
    }
}

// Obsługa zapisu faktury
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_invoice'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'save_invoice')) {
        wp_die('Błąd bezpieczeństwa');
    }
    
    $client_id = intval($_POST['client_id']);
    $month = intval($_POST['month']);
    $year = intval($_POST['year']);
    $invoice_number = sanitize_text_field($_POST['invoice_number']);
    $invoice_status = sanitize_text_field($_POST['invoice_status']);
    
    $result = $report_generator->save_statement($client_id, $month, $year, $invoice_number, $invoice_status);
    
    if ($result) {
        echo '<div class="notice notice-success is-dismissible"><p>Dane faktury zostały zapisane.</p></div>';
        
        // Odśwież dane raportu
        $report = $report_generator->generate_monthly_report($client_id, $month, $year);
        if ($report['success']) {
            $report_data = $report['data'];
        }
    }
}

// Pobierz dane faktury jeśli istnieją
$statement = null;
if ($selected_client && $selected_month && $selected_year) {
    $statement = $report_generator->get_statement($selected_client, $selected_month, $selected_year);
}
?>
<div class="wrap">
    <h1>Generuj zestawienia</h1>
    
    <div class="report-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="time-tracker-reports">
            
            <div class="filter-row">
                <div class="filter-group">
                    <label for="client_id">Klient:</label>
                    <select name="client_id" id="client_id" required>
                        <option value="">Wybierz klienta...</option>
                        <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client->id; ?>" <?php selected($selected_client, $client->id); ?>>
                            <?php echo esc_html($client->company_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="month">Miesiąc:</label>
                    <select name="month" id="month" required>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($selected_month, $i); ?>>
                            <?php echo date_i18n('F', mktime(0, 0, 0, $i, 1)); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="year">Rok:</label>
                    <select name="year" id="year" required>
                        <?php for ($i = date('Y') - 5; $i <= date('Y') + 1; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($selected_year, $i); ?>>
                            <?php echo $i; ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <input type="submit" class="button button-primary" value="Generuj raport">
                </div>
            </div>
        </form>
    </div>
    
    <?php if ($report_data): ?>
    <div class="report-results">
        <h2>Raport dla: <?php echo esc_html($report_data['client']->company_name); ?> - 
            <?php echo date_i18n('F Y', mktime(0, 0, 0, $report_data['month'], 1, $report_data['year'])); ?></h2>
        
        <div class="report-summary">
            <h3>Podsumowanie</h3>
            <table class="widefat">
                <tr>
                    <td><strong>Łączna liczba godzin:</strong></td>
                    <td><?php echo number_format($report_data['summary']['total_hours'], 2); ?> h</td>
                </tr>
                <tr>
                    <td><strong>Kwota za godziny:</strong></td>
                    <td><?php echo number_format($report_data['summary']['hourly_amount'], 2); ?> zł</td>
                </tr>
                <tr>
                    <td><strong>Kwota ryczałtowa:</strong></td>
                    <td><?php echo number_format($report_data['summary']['fixed_amount'], 2); ?> zł</td>
                </tr>
                <tr>
                    <td><strong>Łączna kwota:</strong></td>
                    <td><strong><?php echo number_format($report_data['summary']['total_amount'], 2); ?> zł</strong></td>
                </tr>
            </table>
        </div>
        
        <div class="report-details">
            <h3>Szczegóły</h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Pracownik</th>
                        <th>Usługa</th>
                        <th>Godziny</th>
                        <th>Stawka</th>
                        <th>Kwota</th>
                        <th>Opis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['entries'] as $entry): ?>
                    <tr>
                        <td><?php echo date('d.m.Y', strtotime($entry->entry_date)); ?></td>
                        <td><?php echo esc_html($entry->employee_name); ?></td>
                        <td><?php echo esc_html($entry->service_name); ?></td>
                        <td>
                            <?php if ($entry->is_fixed_price): ?>
                                <em>ryczałt</em>
                            <?php else: ?>
                                <?php echo number_format($entry->hours, 2); ?> h
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry->is_fixed_price): ?>
                                -
                            <?php else: ?>
                                <?php echo number_format($entry->hourly_rate, 2); ?> zł/h
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($entry->is_fixed_price): ?>
                                <?php echo number_format($entry->fixed_amount, 2); ?> zł
                            <?php else: ?>
                                <?php echo number_format($entry->hours * $entry->hourly_rate, 2); ?> zł
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html(wp_trim_words($entry->description, 5)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="invoice-details">
            <h3>Dane faktury</h3>
            <form method="post" action="">
                <?php wp_nonce_field('save_invoice'); ?>
                
                <input type="hidden" name="client_id" value="<?php echo $selected_client; ?>">
                <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="invoice_number">Numer faktury</label></th>
                        <td>
                            <input type="text" name="invoice_number" id="invoice_number" 
                                   value="<?php echo $statement ? esc_attr($statement->invoice_number) : ''; ?>" 
                                   class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="invoice_status">Status faktury</label></th>
                        <td>
                            <select name="invoice_status" id="invoice_status">
                                <option value="draft" <?php selected($statement ? $statement->invoice_status : 'draft', 'draft'); ?>>Szkic</option>
                                <option value="sent" <?php selected($statement ? $statement->invoice_status : 'draft', 'sent'); ?>>Wysłana</option>
                                <option value="paid" <?php selected($statement ? $statement->invoice_status : 'draft', 'paid'); ?>>Opłacona</option>
                                <option value="overdue" <?php selected($statement ? $statement->invoice_status : 'draft', 'overdue'); ?>>Przeterminowana</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_invoice" class="button button-primary" value="Zapisz dane faktury">
                    <button type="button" class="button" onclick="window.print();">Drukuj raport</button>
                    <a href="#" class="button button-secondary">Eksportuj do PDF</a>
                    <a href="#" class="button button-secondary">Eksportuj do CSV</a>
                </p>
            </form>
        </div>
        
        <div class="report-by-employee">
            <h3>Podsumowanie po pracownikach</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Pracownik</th>
                        <th>Godziny</th>
                        <th>Kwota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['summary']['by_employee'] as $employee => $data): ?>
                    <tr>
                        <td><?php echo esc_html($employee); ?></td>
                        <td><?php echo number_format($data['hours'], 2); ?> h</td>
                        <td><?php echo number_format($data['amount'], 2); ?> zł</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="report-by-service">
            <h3>Podsumowanie po usługach</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Usługa</th>
                        <th>Godziny</th>
                        <th>Kwota</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data['summary']['by_service'] as $service => $data): ?>
                    <tr>
                        <td><?php echo esc_html($service); ?></td>
                        <td><?php echo number_format($data['hours'], 2); ?> h</td>
                        <td><?php echo number_format($data['amount'], 2); ?> zł</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($selected_client): ?>
        <div class="notice notice-info">
            <p>Brak danych dla wybranego okresu.</p>
        </div>
    <?php endif; ?>
</div>

<style>
.report-filters {
    background: #fff;
    padding: 15px;
    border: 1px solid #ccd0d4;
    margin: 20px 0;
}
.filter-row {
    display: flex;
    gap: 15px;
    align-items: center;
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
.report-summary,
.report-details,
.invoice-details,
.report-by-employee,
.report-by-service {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    margin: 20px 0;
}
.report-summary table {
    width: 100%;
}
.report-summary td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}
.report-summary tr:last-child td {
    border-bottom: none;
}
</style>