<?php
if (!current_user_can('manage_options')) {
    wp_die(__('Brak uprawnień.', 'time-tracker'));
}

// Obsługa zapisu ustawień
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'save_settings')) {
        wp_die('Błąd bezpieczeństwa');
    }
    
    // Zapisz ustawienia
    update_option('time_tracker_company_name', sanitize_text_field($_POST['company_name']));
    update_option('time_tracker_company_nip', sanitize_text_field($_POST['company_nip']));
    update_option('time_tracker_company_address', sanitize_textarea_field($_POST['company_address']));
    update_option('time_tracker_company_email', sanitize_email($_POST['company_email']));
    update_option('time_tracker_company_phone', sanitize_text_field($_POST['company_phone']));
    
    update_option('time_tracker_weekly_reminder', isset($_POST['weekly_reminder']) ? 1 : 0);
    update_option('time_tracker_reminder_day', intval($_POST['reminder_day']));
    update_option('time_tracker_reminder_time', sanitize_text_field($_POST['reminder_time']));
    
    echo '<div class="notice notice-success is-dismissible"><p>Ustawienia zostały zapisane.</p></div>';
}

// Pobierz obecne ustawienia
$company_name = get_option('time_tracker_company_name', get_bloginfo('name'));
$company_nip = get_option('time_tracker_company_nip', '');
$company_address = get_option('time_tracker_company_address', '');
$company_email = get_option('time_tracker_company_email', get_bloginfo('admin_email'));
$company_phone = get_option('time_tracker_company_phone', '');

$weekly_reminder = get_option('time_tracker_weekly_reminder', 1);
$reminder_day = get_option('time_tracker_reminder_day', 5); // Piątek
$reminder_time = get_option('time_tracker_reminder_time', '15:00');

$days_of_week = array(
    1 => 'Poniedziałek',
    2 => 'Wtorek',
    3 => 'Środa',
    4 => 'Czwartek',
    5 => 'Piątek',
    6 => 'Sobota',
    7 => 'Niedziela'
);
?>
<div class="wrap">
    <h1>Ustawienia Time Tracker</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('save_settings'); ?>
        
        <h2>Dane firmy</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="company_name">Nazwa firmy</label></th>
                <td>
                    <input type="text" name="company_name" id="company_name" 
                           value="<?php echo esc_attr($company_name); ?>" class="regular-text">
                    <p class="description">Nazwa Twojej firmy (będzie wyświetlana na fakturach)</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_nip">NIP firmy</label></th>
                <td>
                    <input type="text" name="company_nip" id="company_nip" 
                           value="<?php echo esc_attr($company_nip); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_address">Adres firmy</label></th>
                <td>
                    <textarea name="company_address" id="company_address" rows="3" class="regular-text"><?php 
                        echo esc_textarea($company_address); 
                    ?></textarea>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_email">Email firmy</label></th>
                <td>
                    <input type="email" name="company_email" id="company_email" 
                           value="<?php echo esc_attr($company_email); ?>" class="regular-text">
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="company_phone">Telefon firmy</label></th>
                <td>
                    <input type="text" name="company_phone" id="company_phone" 
                           value="<?php echo esc_attr($company_phone); ?>" class="regular-text">
                </td>
            </tr>
        </table>
        
        <h2>Powiadomienia</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="weekly_reminder">Przypomnienia tygodniowe</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="weekly_reminder" id="weekly_reminder" value="1" 
                            <?php checked($weekly_reminder, 1); ?>>
                        Wysyłaj automatyczne przypomnienia o uzupełnieniu godzin
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="reminder_day">Dzień przypomnienia</label></th>
                <td>
                    <select name="reminder_day" id="reminder_day">
                        <?php foreach ($days_of_week as $day_num => $day_name): ?>
                        <option value="<?php echo $day_num; ?>" <?php selected($reminder_day, $day_num); ?>>
                            <?php echo $day_name; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">W którym dniu tygodnia wysyłać przypomnienia?</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="reminder_time">Godzina przypomnienia</label></th>
                <td>
                    <input type="time" name="reminder_time" id="reminder_time" 
                           value="<?php echo esc_attr($reminder_time); ?>">
                    <p class="description">O której godzinie wysyłać przypomnienia?</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="save_settings" class="button button-primary" value="Zapisz ustawienia">
        </p>
    </form>
    
    <div class="system-info">
        <h2>Informacje o systemie</h2>
        <table class="widefat">
            <tr>
                <td><strong>Wersja Time Tracker Pro:</strong></td>
                <td><?php echo TIME_TRACKER_VERSION; ?></td>
            </tr>
            <tr>
                <td><strong>Wersja WordPress:</strong></td>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <td><strong>Wersja PHP:</strong></td>
                <td><?php echo phpversion(); ?></td>
            </tr>
            <tr>
                <td><strong>Baza danych:</strong></td>
                <td><?php echo get_option('time_tracker_db_version', '1.0.0'); ?></td>
            </tr>
        </table>
    </div>
</div>

<style>
.system-info {
    margin-top: 40px;
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
}

.system-info table {
    width: 100%;
}

.system-info td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

.system-info tr:last-child td {
    border-bottom: none;
}
</style>