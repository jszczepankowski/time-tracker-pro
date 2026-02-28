<?php
function time_tracker_entry_form_shortcode() {
    // Tylko dla zalogowanych użytkowników
    if (!is_user_logged_in()) {
        return '<p>' . __('Musisz być zalogowany, aby dodać wpis.', 'time-tracker') . '</p>';
    }
    
    // Sprawdź uprawnienia
    if (!current_user_can('add_time_entries')) {
        return '<p>' . __('Nie masz uprawnień do dodawania wpisów.', 'time-tracker') . '</p>';
    }
    
    ob_start();
    
    // Pobierz aktywnych klientów
    global $wpdb;
    $clients = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}tt_clients 
         WHERE active = 1 
         ORDER BY company_name"
    );
    
    // Obsługa formularza
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_time_entry'])) {
        if (!wp_verify_nonce($_POST['time_entry_nonce'], 'add_time_entry')) {
            echo '<div class="error"><p>' . __('Błąd bezpieczeństwa.', 'time-tracker') . '</p></div>';
        } else {
            $time_tracker = time_tracker_pro();
            $result = $time_tracker->get_time_entry()->add_entry($_POST);
            
            if ($result) {
                echo '<div class="success"><p>' . __('Wpis został dodany.', 'time-tracker') . '</p></div>';
            } else {
                echo '<div class="error"><p>' . __('Wystąpił błąd podczas dodawania wpisu.', 'time-tracker') . '</p></div>';
            }
        }
    }
    ?>
    
    <div class="time-tracker-form">
        <h2><?php _e('Dodaj nowy wpis godzinowy', 'time-tracker'); ?></h2>
        
        <form method="post" action="" id="time-entry-form">
            <?php wp_nonce_field('add_time_entry', 'time_entry_nonce'); ?>
            
            <div class="form-group">
                <label for="client_id"><?php _e('Klient:', 'time-tracker'); ?> *</label>
                <select name="client_id" id="client_id" required>
                    <option value=""><?php _e('Wybierz klienta...', 'time-tracker'); ?></option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client->id; ?>">
                            <?php echo esc_html($client->company_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="entry_date"><?php _e('Data:', 'time-tracker'); ?> *</label>
                <input type="date" name="entry_date" id="entry_date" 
                       value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="service_id"><?php _e('Usługa:', 'time-tracker'); ?> *</label>
                <select name="service_id" id="service_id" required disabled>
                    <option value=""><?php _e('Najpierw wybierz klienta', 'time-tracker'); ?></option>
                </select>
            </div>
            
            <div class="form-group" id="hours-field">
                <label for="hours"><?php _e('Liczba godzin:', 'time-tracker'); ?></label>
                <input type="number" name="hours" id="hours" step="0.25" min="0.25" max="24">
                <small><?php _e('Wpisz liczbę godzin (np. 1.5)', 'time-tracker'); ?></small>
            </div>
            
            <div class="form-group" id="fixed-amount-field" style="display: none;">
                <label for="fixed_amount"><?php _e('Kwota ryczałtowa:', 'time-tracker'); ?></label>
                <input type="number" name="fixed_amount" id="fixed_amount" step="0.01" min="0">
                <small><?php _e('Wpisz kwotę dla usługi ryczałtowej', 'time-tracker'); ?></small>
            </div>
            
            <div class="form-group">
                <label for="description"><?php _e('Opis zadania:', 'time-tracker'); ?></label>
                <textarea name="description" id="description" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <input type="submit" name="submit_time_entry" 
                       value="<?php _e('Dodaj wpis', 'time-tracker'); ?>">
                <button type="button" id="save-draft" class="button-secondary">
                    <?php _e('Zapisz jako szkic', 'time-tracker'); ?>
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
                        nonce: '<?php echo wp_create_nonce('get_services_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var options = '<option value=""><?php _e('Wybierz usługę...', 'time-tracker'); ?></option>';
                            $.each(response.data, function(index, service) {
                                options += '<option value="' + service.id + '" data-is-fixed="' + service.is_fixed_price + '">' + service.service_name + '</option>';
                            });
                            $('#service_id').html(options);
                        }
                    }
                });
            } else {
                $('#service_id').prop('disabled', true).html('<option value=""><?php _e('Najpierw wybierz klienta', 'time-tracker'); ?></option>');
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
    
    <style>
    .time-tracker-form {
        max-width: 600px;
        margin: 20px auto;
        padding: 20px;
        background: #f9f9f9;
        border-radius: 5px;
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
    </style>
    
    <?php
    return ob_get_clean();
}
add_shortcode('time_entry_form', 'time_tracker_entry_form_shortcode');

// AJAX endpoint dla usług
add_action('wp_ajax_get_client_services', 'time_tracker_get_client_services_ajax');
add_action('wp_ajax_nopriv_get_client_services', 'time_tracker_get_client_services_ajax');

function time_tracker_get_client_services_ajax() {
    if (!wp_verify_nonce($_POST['nonce'], 'get_services_nonce')) {
        wp_die('Security check failed');
    }
    
    $client_id = intval($_POST['client_id']);
    $time_tracker = time_tracker_pro();
    $services = $time_tracker->get_database()->get_client_services($client_id);
    
    wp_send_json_success($services);
}