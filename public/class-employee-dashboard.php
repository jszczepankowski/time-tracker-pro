<?php
class TimeTracker_Employee_Dashboard {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        add_shortcode('time_entry_form', array($this, 'time_entry_form_shortcode'));
        add_shortcode('employee_time_summary', array($this, 'employee_summary_shortcode'));
    }
    
    public function enqueue_public_scripts() {
        if (is_page()) {
            $post = get_post();
            if (has_shortcode($post->post_content, 'time_entry_form') || 
                has_shortcode($post->post_content, 'employee_time_summary')) {
                
                wp_enqueue_script('jquery');
                
                // Inline styles
                add_action('wp_head', array($this, 'add_inline_styles'));
            }
        }
    }
    
    public function add_inline_styles() {
        ?>
        <style>
        .time-tracker-form,
        .employee-summary {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group select,
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-group input[type="submit"],
        .form-group button {
            padding: 10px 20px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        .form-group input[type="submit"]:hover,
        .form-group button:hover {
            background: #005a87;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .time-tracker-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .time-tracker-table th,
        .time-tracker-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .time-tracker-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .summary-stats {
            display: flex;
            gap: 20px;
            margin: 30px 0;
        }
        .stat-box {
            flex: 1;
            background: #0073aa;
            color: white;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-box h3 {
            margin: 0;
            font-size: 2em;
        }
        .stat-box p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        </style>
        <?php
    }
    
    public function time_entry_form_shortcode() {
        // Tylko dla zalogowanych użytkowników
        if (!is_user_logged_in()) {
            return '<p>Musisz być zalogowany, aby dodać wpis.</p>';
        }
        
        // Sprawdź uprawnienia
        if (!current_user_can('add_time_entries')) {
            return '<p>Nie masz uprawnień do dodawania wpisów.</p>';
        }
        
        ob_start();
        
        // Pobierz aktywnych klientów
        $time_tracker = time_tracker_pro();
        $clients = $time_tracker->get_client_manager()->get_clients(true);
        
        // Obsługa formularza
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_time_entry'])) {
            if (!wp_verify_nonce($_POST['time_entry_nonce'], 'add_time_entry')) {
                echo '<div class="error"><p>Błąd bezpieczeństwa.</p></div>';
            } else {
                $result = $time_tracker->get_time_entry()->add_entry($_POST);
                
                if ($result['success']) {
                    echo '<div class="success"><p>Wpis został dodany.</p></div>';
                } else {
                    echo '<div class="error"><p>';
                    foreach ($result['errors'] as $error) {
                        echo $error . '<br>';
                    }
                    echo '</p></div>';
                }
            }
        }
        ?>
        
        <div class="time-tracker-form">
            <h2>Dodaj nowy wpis godzinowy</h2>
            
            <form method="post" action="" id="time-entry-form">
                <?php wp_nonce_field('add_time_entry', 'time_entry_nonce'); ?>
                
                <div class="form-group">
                    <label for="client_id">Klient: *</label>
                    <select name="client_id" id="client_id" required>
                        <option value="">Wybierz klienta...</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client->id; ?>">
                                <?php echo esc_html($client->company_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="entry_date">Data: *</label>
                    <input type="date" name="entry_date" id="entry_date" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="service_id">Usługa: *</label>
                    <select name="service_id" id="service_id" required disabled>
                        <option value="">Najpierw wybierz klienta</option>
                    </select>
                </div>
                
                <div class="form-group" id="hours-field">
                    <label for="hours">Liczba godzin:</label>
                    <input type="number" name="hours" id="hours" step="0.25" min="0.25" max="24">
                    <small>Wpisz liczbę godzin (np. 1.5)</small>
                </div>
                
                <div class="form-group" id="fixed-amount-field" style="display: none;">
                    <label for="fixed_amount">Kwota ryczałtowa:</label>
                    <input type="number" name="fixed_amount" id="fixed_amount" step="0.01" min="0">
                    <small>Wpisz kwotę dla usługi ryczałtowej</small>
                </div>
                
                <div class="form-group">
                    <label for="description">Opis zadania:</label>
                    <textarea name="description" id="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <input type="submit" name="submit_time_entry" 
                           value="Dodaj wpis">
                    <button type="button" id="save-draft" class="button-secondary">
                        Zapisz jako szkic
                    </button>
                </div>
                
                <input type="hidden" name="status" id="entry_status" value="draft">
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Dynamiczne ładowanie usług dla klienta
            $('#client_id').change(function() {
                var clientId = $(this).val();
                if (clientId) {
                    $('#service_id').prop('disabled', false);
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'get_client_services',
                            client_id: clientId,
                            nonce: '<?php echo wp_create_nonce("get_services_nonce"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var options = '<option value="">Wybierz usługę...</option>';
                                $.each(response.data, function(index, service) {
                                    options += '<option value="' + service.id + '" data-is-fixed="' + service.is_fixed_price + '">' + service.service_name + '</option>';
                                });
                                $('#service_id').html(options);
                            }
                        }
                    });
                } else {
                    $('#service_id').prop('disabled', true).html('<option value="">Najpierw wybierz klienta</option>');
                }
            });
            
            // Przełączanie między godzinami a kwotą ryczałtową
            $('#service_id').change(function() {
                var selectedOption = $(this).find('option:selected');
                var isFixed = selectedOption.data('is-fixed');
                
                if (isFixed == 1) {
                    $('#hours-field').hide();
                    $('#hours').val('').prop('required', false);
                    $('#fixed-amount-field').show();
                    $('#fixed_amount').prop('required', true);
                } else {
                    $('#hours-field').show();
                    $('#hours').prop('required', true);
                    $('#fixed-amount-field').hide();
                    $('#fixed_amount').val('').prop('required', false);
                }
            });
            
            // Zapisz jako szkic
            $('#save-draft').click(function() {
                $('#entry_status').val('draft');
                $('#time-entry-form').submit();
            });
            
            // Domyślnie zapisz jako zgłoszony
            $('input[name="submit_time_entry"]').click(function() {
                $('#entry_status').val('submitted');
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    public function employee_summary_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>Musisz być zalogowany, aby zobaczyć podsumowanie.</p>';
        }
        
        $current_user = wp_get_current_user();
        $current_month = date('n');
        $current_year = date('Y');
        
        $time_tracker = time_tracker_pro();
        $summary = $time_tracker->get_database()->get_employee_summary(
            $current_user->ID,
            $current_month,
            $current_year
        );
        
        // Pobierz również wpisy z tego miesiąca
        $entries = $time_tracker->get_time_entry()->get_employee_entries(
            $current_user->ID,
            null,
            date('Y-m-01'),
            date('Y-m-t')
        );
        
        ob_start();
        ?>
        
        <div class="employee-summary">
            <h2>Podsumowanie miesiąca: <?php echo date_i18n('F Y'); ?></h2>
            
            <?php if (empty($summary)): ?>
                <p>Brak zatwierdzonych godzin w tym miesiącu.</p>
            <?php else: ?>
                <table class="time-tracker-table">
                    <thead>
                        <tr>
                            <th>Klient</th>
                            <th>Łączna liczba godzin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_hours = 0;
                        foreach ($summary as $item): 
                            $total_hours += $item->total_hours;
                        ?>
                        <tr>
                            <td><?php echo esc_html($item->company_name); ?></td>
                            <td><?php echo number_format($item->total_hours, 2); ?> h</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td><strong>Razem:</strong></td>
                            <td><strong><?php echo number_format($total_hours, 2); ?> h</strong></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
            
            <div class="summary-stats">
                <div class="stat-box">
                    <h3><?php echo number_format($total_hours, 2); ?></h3>
                    <p>Łączna liczba godzin</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo count($summary); ?></h3>
                    <p>Liczba klientów</p>
                </div>
            </div>
            
            <div class="recent-entries">
                <h3>Moje wpisy w tym miesiącu</h3>
                <?php if ($entries): ?>
                <table class="time-tracker-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Klient</th>
                            <th>Usługa</th>
                            <th>Godziny/Kwota</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($entries, 0, 10) as $entry): ?>
                        <tr>
                            <td><?php echo date_i18n('d.m.Y', strtotime($entry->entry_date)); ?></td>
                            <td><?php echo esc_html($entry->client_name); ?></td>
                            <td><?php echo esc_html($entry->service_name); ?></td>
                            <td>
                                <?php 
                                if ($entry->is_fixed_price) {
                                    echo number_format($entry->fixed_amount, 2) . ' zł';
                                } else {
                                    echo number_format($entry->hours, 2) . ' h';
                                }
                                ?>
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
                    <p>Brak wpisów w tym miesiącu.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
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
        
        <?php
        return ob_get_clean();
    }
}