<?php
/**
 * Plugin Name: Time Tracker Pro
 * Description: System do rozliczania godzin pracy z klientami
 * Version: 1.1.2
 * Author: Twoje Imię
 * Text Domain: time-tracker
 */

// Zabezpieczenie przed bezpośrednim dostępem
if (!defined('ABSPATH')) {
    exit;
}

// Buforowanie outputu na początku
if (!ob_get_level()) {
    ob_start();
}

// Definicje stałych
define('TIME_TRACKER_VERSION', '1.1.2');
define('TIME_TRACKER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TIME_TRACKER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Tymczasowo wyłącz deprecated warnings dla developmentu
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_reporting(E_ALL & ~E_DEPRECATED);
}

class TimeTrackerPro {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Aktywacja/deaktywacja wtyczki
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Inicjalizacja
        add_action('plugins_loaded', array($this, 'init'));
        
        // Sprawdź uprawnienia administratora
        add_action('admin_init', array($this, 'check_admin_caps'));
        
        // Przetwarzanie formularzy admina
        add_action('admin_init', array($this, 'process_admin_forms'));
        
        // AJAX handlers
        add_action('wp_ajax_get_client_services', array($this, 'ajax_get_client_services'));
        add_action('wp_ajax_nopriv_get_client_services', array($this, 'ajax_get_client_services_nopriv'));
        add_action('wp_ajax_get_client_projects', array($this, 'ajax_get_client_projects'));
        add_action('wp_ajax_nopriv_get_client_projects', array($this, 'ajax_get_client_projects_nopriv'));
    }
    
    public function activate() {
        // Utwórz tabele
        $this->setup_database();
        
        // Przypisz uprawnienia
        $this->set_capabilities();
        
        // Stwórz role
        $this->create_roles();
        
        // Dodaj przykładowe dane
        $this->add_sample_data();
        
        // Ustaw cron jobs
        $this->setup_cron_jobs();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function setup_database() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela klientów
        $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}time_tracker_clients (
            id int(11) NOT NULL AUTO_INCREMENT,
            company_name varchar(255) NOT NULL,
            nip varchar(20) NOT NULL,
            address text,
            email varchar(100),
            phone varchar(20),
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY nip (nip)
        ) $charset_collate;";
        
        // Tabela usług klienta
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}time_tracker_client_services (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_id int(11) NOT NULL,
            service_name varchar(100) NOT NULL,
            hourly_rate decimal(10,2),
            is_fixed_price tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY client_id (client_id)
        ) $charset_collate;";
        
        // Tabela wpisów godzinowych
        $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}time_tracker_entries (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            client_id int(11) NOT NULL,
            service_id int(11) NOT NULL,
            project_id int(11),
            entry_date date NOT NULL,
            hours decimal(4,2),
            fixed_amount decimal(10,2),
            description text,
            status varchar(20) DEFAULT 'draft',
            submission_date datetime,
            approved_by int(11),
            approved_date datetime,
            rejection_reason text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY employee_id (employee_id),
            KEY client_id (client_id),
            KEY service_id (service_id),
            KEY project_id (project_id),
            KEY entry_date (entry_date),
            KEY status (status)
        ) $charset_collate;";

        // Tabela projektów klienta (miesięcznych)
        $sql5 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}time_tracker_projects (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_id int(11) NOT NULL,
            project_name varchar(255) NOT NULL,
            month int(2) NOT NULL,
            year int(4) NOT NULL,
            created_by int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_client_project_period (client_id, project_name, month, year),
            KEY client_id (client_id),
            KEY month_year (month, year)
        ) $charset_collate;";
        
        // Tabela faktur (dodana dla przyszłych funkcji)
        $sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}time_tracker_invoices (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_id int(11) NOT NULL,
            invoice_number varchar(50) NOT NULL,
            invoice_date date NOT NULL,
            amount decimal(10,2) NOT NULL,
            status varchar(20) DEFAULT 'draft',
            month int(2) NOT NULL,
            year int(4) NOT NULL,
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            created_by int(11) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY invoice_number (invoice_number),
            KEY client_id (client_id),
            KEY month_year (month, year)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
        dbDelta($sql5);
    }
    
    private function add_sample_data() {
        global $wpdb;
        
        // Sprawdź czy już istnieją klienci
        $clients_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}time_tracker_clients");
        
        if ($clients_count == 0) {
            // Dodaj przykładowego klienta
            $wpdb->insert(
                "{$wpdb->prefix}time_tracker_clients",
                array(
                    'company_name' => 'Przykładowa Firma Sp. z o.o.',
                    'nip' => '1234567890',
                    'address' => 'ul. Przykładowa 1, 00-000 Warszawa',
                    'email' => 'biuro@przyklad.pl',
                    'phone' => '123-456-789',
                    'active' => 1
                )
            );
            
            $client_id = $wpdb->insert_id;
            
            // Dodaj przykładowe usługi
            $services = array(
                array('service_name' => 'Programowanie', 'hourly_rate' => 150.00),
                array('service_name' => 'Projektowanie', 'hourly_rate' => 120.00),
                array('service_name' => 'Konsultacje', 'hourly_rate' => 100.00),
                array('service_name' => 'Wydruki', 'is_fixed_price' => 1)
            );
            
            foreach ($services as $service) {
                $wpdb->insert(
                    "{$wpdb->prefix}time_tracker_client_services",
                    array(
                        'client_id' => $client_id,
                        'service_name' => $service['service_name'],
                        'hourly_rate' => isset($service['hourly_rate']) ? $service['hourly_rate'] : null,
                        'is_fixed_price' => isset($service['is_fixed_price']) ? $service['is_fixed_price'] : 0
                    )
                );
            }
        }
    }
    
    public function deactivate() {
        // Wyczyść cron jobs
        wp_clear_scheduled_hook('time_tracker_weekly_reminder');
        
        flush_rewrite_rules();
    }
    
    public function check_admin_caps() {
        // Upewnij się, że administrator ma wszystkie potrzebne uprawnienia
        $admin = get_role('administrator');
        if ($admin) {
            $required_caps = array(
                'manage_time_tracker',
                'view_all_time_entries',
                'approve_time_entries',
                'manage_clients',
                'edit_time_entries',
                'edit_others_time_entries',
                'delete_time_entries',
                'generate_reports'
            );
            
            foreach ($required_caps as $cap) {
                if (!$admin->has_cap($cap)) {
                    $admin->add_cap($cap);
                }
            }
        }
    }
    
    private function create_roles() {
        // Tworzenie roli managera
        if (!get_role('time_tracker_manager')) {
            add_role(
                'time_tracker_manager',
                'Time Tracker Manager',
                array(
                    'read' => true,
                    'manage_time_tracker' => true,
                    'view_all_time_entries' => true,
                    'approve_time_entries' => true,
                    'manage_clients' => true,
                    'edit_time_entries' => true,
                    'edit_others_time_entries' => true,
                    'generate_reports' => true,
                    'upload_files' => true,
                    'edit_pages' => true,
                    'edit_posts' => true,
                    'publish_posts' => true,
                    'moderate_comments' => false
                )
            );
        }
        
        // Tworzenie roli pracownika
        if (!get_role('time_tracker_employee')) {
            add_role(
                'time_tracker_employee',
                'Time Tracker Employee',
                array(
                    'read' => true,
                    'add_time_entries' => true,
                    'edit_own_time_entries' => true,
                    'view_own_reports' => true
                )
            );
        }
    }
    
    private function set_capabilities() {
        // Dla administratora - pełne uprawnienia
        $admin = get_role('administrator');
        if ($admin) {
            $admin_caps = array(
                'manage_time_tracker',
                'view_all_time_entries',
                'approve_time_entries',
                'manage_clients',
                'edit_time_entries',
                'edit_others_time_entries',
                'delete_time_entries',
                'generate_reports'
            );
            
            foreach ($admin_caps as $cap) {
                $admin->add_cap($cap);
            }
        }
        
        // Dla managera
        $manager = get_role('time_tracker_manager');
        if ($manager) {
            $manager_caps = array(
                'manage_time_tracker' => true,
                'view_all_time_entries' => true,
                'approve_time_entries' => true,
                'manage_clients' => true,
                'edit_time_entries' => true,
                'edit_others_time_entries' => true,
                'generate_reports' => true
            );
            
            foreach ($manager_caps as $cap => $value) {
                $manager->add_cap($cap);
            }
        }
        
        // Dla istniejących ról WordPressa
        $author = get_role('author');
        if ($author) {
            $author->add_cap('add_time_entries');
            $author->add_cap('edit_own_time_entries');
            $author->add_cap('view_own_reports');
        }
        
        $contributor = get_role('contributor');
        if ($contributor) {
            $contributor->add_cap('add_time_entries');
            $contributor->add_cap('edit_own_time_entries');
            $contributor->add_cap('view_own_reports');
        }
        
        // Dla naszej roli pracownika
        $employee = get_role('time_tracker_employee');
        if ($employee) {
            $employee->add_cap('add_time_entries');
            $employee->add_cap('edit_own_time_entries');
            $employee->add_cap('view_own_reports');
        }
    }
    
    public function init() {
        // Rejestracja shortcodów
        add_shortcode('time_entry_form', array($this, 'time_entry_form_shortcode'));
        add_shortcode('employee_time_summary', array($this, 'employee_summary_shortcode'));
        
        // Inicjalizacja panelu admina
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
        
        // Enqueue scripts dla frontendu
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        
        // Dodaj style admina
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Upewnij się, że migracja projektów jest zastosowana także po aktualizacji wtyczki
        $this->ensure_project_schema();
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'time-tracker') !== false) {
            wp_enqueue_style('time-tracker-admin', TIME_TRACKER_PLUGIN_URL . 'admin/assets/css/admin-style.css', array(), TIME_TRACKER_VERSION);
            wp_enqueue_script('time-tracker-admin', TIME_TRACKER_PLUGIN_URL . 'admin/assets/js/admin-script.js', array('jquery'), TIME_TRACKER_VERSION, true);
            
            // Lokalizacja skryptu
            wp_localize_script('time-tracker-admin', 'timeTrackerAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('get_services_nonce')
            ));
        }
    }
    
    public function process_admin_forms() {
        // Tylko w panelu admina
        if (!is_admin()) {
            return;
        }
        
        // Zapisywanie klienta
        if (isset($_POST['save_client']) && isset($_POST['client_nonce'])) {
            $this->save_client();
        }
        
        // Usuwanie klienta
        if (isset($_GET['time_tracker_action']) && $_GET['time_tracker_action'] == 'delete_client' && isset($_GET['id'])) {
            $this->delete_client();
        }
        
        // Zapisywanie usługi
        if (isset($_POST['save_service']) && isset($_POST['service_nonce'])) {
            $this->save_service();
        }

        // Dodawanie projektu
        if (isset($_POST['save_project']) && isset($_POST['project_nonce'])) {
            $this->save_project();
        }
        
        // Usuwanie usługi
        if (isset($_GET['time_tracker_action']) && $_GET['time_tracker_action'] == 'delete_service' && isset($_GET['id'])) {
            $this->delete_service();
        }

        // Usuwanie projektu
        if (isset($_GET['time_tracker_action']) && $_GET['time_tracker_action'] == 'delete_project' && isset($_GET['id'])) {
            $this->delete_project();
        }
        
        // Zatwierdzanie wpisów
        if (isset($_GET['time_tracker_action']) && $_GET['time_tracker_action'] == 'approve_entry' && isset($_GET['entry_id'])) {
            $this->approve_time_entry();
        }
        
        // Odrzucanie wpisów
        if (isset($_GET['time_tracker_action']) && $_GET['time_tracker_action'] == 'reject_entry' && isset($_GET['entry_id'])) {
            $this->reject_time_entry();
        }
        
        // Usuwanie wpisu
        if (isset($_GET['time_tracker_action']) && $_GET['time_tracker_action'] == 'delete_entry' && isset($_GET['id'])) {
            $this->delete_time_entry();
        }
    }
    
    public function add_admin_menu() {
        // Główne menu
        add_menu_page(
            'Time Tracker',
            'Time Tracker',
            'manage_time_tracker',
            'time-tracker',
            array($this, 'admin_dashboard_page'),
            'dashicons-clock',
            30
        );
        
        // Podstrony - ZMIANA PORZĄDKU I DODANIE USŁUG
        add_submenu_page(
            'time-tracker',
            'Dashboard',
            'Dashboard',
            'manage_time_tracker',
            'time-tracker',
            array($this, 'admin_dashboard_page')
        );
        
        add_submenu_page(
            'time-tracker',
            'Klienci',
            'Klienci',
            'manage_clients',
            'time-tracker-clients',
            array($this, 'admin_clients_page')
        );
        
        // DODAJEMY WIDOCZNĄ ZAKŁADKĘ DLA USŁUG
        add_submenu_page(
            'time-tracker',
            'Usługi',
            'Usługi',
            'manage_clients',
            'time-tracker-services',
            array($this, 'admin_services_page')
        );
        
        add_submenu_page(
            'time-tracker',
            'Wpisy godzinowe',
            'Wpisy godzinowe',
            'view_all_time_entries',
            'time-tracker-entries',
            array($this, 'admin_entries_page')
        );
        
        add_submenu_page(
            'time-tracker',
            'Zestawienia',
            'Zestawienia',
            'generate_reports',
            'time-tracker-reports',
            array($this, 'admin_reports_page')
        );

        add_submenu_page(
            'time-tracker',
            'Projekty',
            'Projekty',
            'manage_clients',
            'time-tracker-projects',
            array($this, 'admin_projects_page')
        );
        
        add_submenu_page(
            'time-tracker',
            'Ustawienia',
            'Ustawienia',
            'manage_time_tracker',
            'time-tracker-settings',
            array($this, 'admin_settings_page')
        );
        
        // Ukryte strony
        add_submenu_page(
            null,
            'Usługi klienta',
            'Usługi klienta',
            'manage_clients',
            'time-tracker-client-services',
            array($this, 'admin_client_services_page')
        );
        
        add_submenu_page(
            null,
            'Edytuj wpis',
            'Edytuj wpis',
            'edit_time_entries',
            'time-tracker-edit-entry',
            array($this, 'admin_edit_entry_page')
        );
        
        add_submenu_page(
            null,
            'Faktury',
            'Faktury',
            'manage_time_tracker',
            'time-tracker-invoices',
            array($this, 'admin_invoices_page')
        );
    }
    
    public function enqueue_frontend_scripts() {
        global $post;
        if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'time_entry_form') || has_shortcode($post->post_content, 'employee_time_summary'))) {
            wp_enqueue_script('jquery');
            
            // Inline styles
            add_action('wp_head', function() {
                ?>
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
            });
        }
    }
    
    public function ajax_get_client_services() {
        // Sprawdź czy są wymagane dane
        if (!isset($_POST['nonce']) || !isset($_POST['client_id'])) {
            wp_send_json_error('Brak wymaganych danych');
        }
        
        check_ajax_referer('get_services_nonce', 'nonce');
        
        $client_id = intval($_POST['client_id']);
        if (!$client_id) {
            wp_send_json_error('Nieprawidłowy klient');
        }
        
        global $wpdb;
        
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}time_tracker_client_services 
             WHERE client_id = %d 
             ORDER BY service_name",
            $client_id
        ));
        
        if (is_array($services)) {
            wp_send_json_success($services);
        } else {
            wp_send_json_error('Błąd pobierania usług');
        }
    }
    
    public function ajax_get_client_services_nopriv() {
        wp_send_json_error('Brak dostępu');
    }

    public function ajax_get_client_projects() {
        if (!isset($_POST['nonce']) || !isset($_POST['client_id'])) {
            wp_send_json_error('Brak wymaganych danych');
        }

        check_ajax_referer('get_services_nonce', 'nonce');

        $client_id = intval($_POST['client_id']);
        $month = isset($_POST['month']) ? intval($_POST['month']) : intval(date('n'));
        $year = isset($_POST['year']) ? intval($_POST['year']) : intval(date('Y'));

        if (!$client_id || $month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            wp_send_json_error('Nieprawidłowe dane');
        }

        global $wpdb;

        $projects = $wpdb->get_results($wpdb->prepare(
            "SELECT id, project_name
             FROM {$wpdb->prefix}time_tracker_projects
             WHERE client_id = %d
               AND month = %d
               AND year = %d
             ORDER BY project_name",
            $client_id,
            $month,
            $year
        ));

        wp_send_json_success(is_array($projects) ? $projects : array());
    }

    public function ajax_get_client_projects_nopriv() {
        wp_send_json_error('Brak dostępu');
    }

    private function ensure_project_schema() {
        global $wpdb;

        $projects_table = $wpdb->prefix . 'time_tracker_projects';
        $entries_table = $wpdb->prefix . 'time_tracker_entries';

        $project_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $projects_table));
        if ($project_table_exists !== $projects_table) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE IF NOT EXISTS {$projects_table} (
                id int(11) NOT NULL AUTO_INCREMENT,
                client_id int(11) NOT NULL,
                project_name varchar(255) NOT NULL,
                month int(2) NOT NULL,
                year int(4) NOT NULL,
                created_by int(11) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_client_project_period (client_id, project_name, month, year),
                KEY client_id (client_id),
                KEY month_year (month, year)
            ) {$charset_collate};";
            dbDelta($sql);
        }

        $project_column = $wpdb->get_var("SHOW COLUMNS FROM {$entries_table} LIKE 'project_id'");
        if (!$project_column) {
            $wpdb->query("ALTER TABLE {$entries_table} ADD COLUMN project_id int(11) NULL AFTER service_id");
            $project_column = $wpdb->get_var("SHOW COLUMNS FROM {$entries_table} LIKE 'project_id'");
        }

        $project_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $projects_table));
        if ($project_table_exists !== $projects_table || !$project_column) {
            return false;
        }

        return true;
    }

    // Admin pages
    public function admin_dashboard_page() {
        if (!current_user_can('manage_time_tracker')) {
            wp_die('Brak uprawnień');
        }
        ?>
        <div class="wrap">
            <h1>Time Tracker - Panel Główny</h1>
            <div class="time-tracker-widgets">
                <div class="widget">
                    <h3>Szybkie statystyki</h3>
                    <?php
                    global $wpdb;
                    
                    // Liczba aktywnych klientów
                    $clients_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}time_tracker_clients WHERE active = 1");
                    
                    // Liczba wpisów w tym miesiącu
                    $entries_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}time_tracker_entries 
                         WHERE MONTH(entry_date) = %d AND YEAR(entry_date) = %d",
                        date('n'), date('Y')
                    ));
                    
                    // Liczba wpisów do zatwierdzenia
                    $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}time_tracker_entries WHERE status = 'submitted'");
                    ?>
                    
                    <ul>
                        <li>Aktywnych klientów: <strong><?php echo $clients_count; ?></strong></li>
                        <li>Wpisów w tym miesiącu: <strong><?php echo $entries_count; ?></strong></li>
                        <li>Wpisów do zatwierdzenia: <strong><?php echo $pending_count; ?></strong></li>
                    </ul>
                </div>
                
                <div class="widget">
                    <h3>Szybkie linki</h3>
                    <ul>
                        <li><a href="<?php echo admin_url('admin.php?page=time-tracker-clients'); ?>">Zarządzaj klientami</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=time-tracker-services'); ?>">Przeglądaj usługi</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=time-tracker-entries'); ?>">Przeglądaj wpisy</a></li>
                        <li><a href="<?php echo admin_url('admin.php?page=time-tracker-reports'); ?>">Generuj zestawienia</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function admin_clients_page() {
        if (!current_user_can('manage_clients')) {
            wp_die('Brak uprawnień');
        }
        
        $action = isset($_GET['action']) ? $_GET['action'] : 'list';
        
        switch ($action) {
            case 'add':
            case 'edit':
                $this->client_edit_page();
                break;
            default:
                $this->clients_list_page();
        }
    }
    
    private function clients_list_page() {
        global $wpdb;
        $clients = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}time_tracker_clients ORDER BY company_name");
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Klienci</h1>
            <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=add'); ?>" class="page-title-action">
                Dodaj nowego klienta
            </a>
            
            <hr class="wp-header-end">
            
            <?php if (isset($_GET['client_saved']) && $_GET['client_saved'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Klient został zapisany.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['client_deleted']) && $_GET['client_deleted'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Klient został usunięty.</p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($clients)): ?>
                <p>Brak klientów w systemie. <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=add'); ?>">Dodaj pierwszego klienta</a>.</p>
            <?php else: ?>
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
                                <a href="<?php echo admin_url('admin.php?page=time-tracker-client-services&client_id=' . $client->id); ?>" 
                                   class="button button-small">Usługi</a>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-clients&time_tracker_action=delete_client&id=' . $client->id), 'delete_client_' . $client->id); ?>" 
                                   class="button button-small button-danger" 
                                   onclick="return confirm('Czy na pewno chcesz usunąć tego klienta?');">Usuń</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function client_edit_page() {
        global $wpdb;
        
        $client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $client = null;
        
        if ($client_id > 0) {
            $client = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}time_tracker_clients WHERE id = %d",
                $client_id
            ));
        }
        ?>
        <div class="wrap">
            <h1><?php echo $client_id ? 'Edytuj klienta' : 'Dodaj nowego klienta'; ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_client', 'client_nonce'); ?>
                
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
    
    private function save_client() {
        if (!wp_verify_nonce($_POST['client_nonce'], 'save_client')) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        global $wpdb;
        
        $client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        $data = array(
            'company_name' => isset($_POST['company_name']) ? sanitize_text_field($_POST['company_name']) : '',
            'nip' => isset($_POST['nip']) ? sanitize_text_field($_POST['nip']) : '',
            'address' => isset($_POST['address']) ? sanitize_textarea_field($_POST['address']) : '',
            'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '',
            'active' => isset($_POST['active']) ? 1 : 0
        );
        
        if (empty($data['company_name']) || empty($data['nip'])) {
            wp_die('Wymagane pola nie mogą być puste');
        }
        
        if ($client_id > 0) {
            $wpdb->update("{$wpdb->prefix}time_tracker_clients", $data, array('id' => $client_id));
        } else {
            $wpdb->insert("{$wpdb->prefix}time_tracker_clients", $data);
        }
        
        wp_redirect(admin_url('admin.php?page=time-tracker-clients&client_saved=1'));
        exit;
    }
    
    private function delete_client() {
        $client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_client_' . $client_id)) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        if ($client_id > 0) {
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}time_tracker_clients", array('id' => $client_id));
        }
        
        wp_redirect(admin_url('admin.php?page=time-tracker-clients&client_deleted=1'));
        exit;
    }
    
    public function admin_client_services_page() {
        if (!current_user_can('manage_clients')) {
            wp_die('Brak uprawnień');
        }

        $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        if (!$client_id) {
            wp_die('Nieprawidłowy klient');
        }

        global $wpdb;
        $client = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}time_tracker_clients WHERE id = %d",
            $client_id
        ));

        if (!$client) {
            wp_die('Klient nie istnieje');
        }

        // Pobierz usługi klienta
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}time_tracker_client_services 
             WHERE client_id = %d 
             ORDER BY service_name",
            $client_id
        ));

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Usługi klienta: <?php echo esc_html($client->company_name); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=time-tracker-clients'); ?>" class="page-title-action">Wróć do klientów</a>
            
            <hr class="wp-header-end">
            
            <?php if (isset($_GET['service_saved']) && $_GET['service_saved'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Usługa została zapisana.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['service_deleted']) && $_GET['service_deleted'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Usługa została usunięta.</p>
                </div>
            <?php endif; ?>
            
            <div class="time-tracker-services">
                <div class="services-list">
                    <h2>Lista usług</h2>
                    <?php if (empty($services)): ?>
                        <p>Brak usług dla tego klienta.</p>
                    <?php else: ?>
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
                                        <?php 
                                        if ($service->is_fixed_price == 1) {
                                            echo 'Ryczałt';
                                        } else {
                                            echo number_format(floatval($service->hourly_rate), 2) . ' zł/h';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo $service->is_fixed_price == 1 ? 'Ryczałt' : 'Godzinowa'; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=time-tracker-client-services&action=edit&client_id=' . $client_id . '&id=' . $service->id); ?>" 
                                           class="button button-small">Edytuj</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-client-services&time_tracker_action=delete_service&client_id=' . $client_id . '&id=' . $service->id), 'delete_service_' . $service->id); ?>" 
                                           class="button button-small button-danger" 
                                           onclick="return confirm('Czy na pewno chcesz usunąć tę usługę?');">Usuń</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="service-form">
                    <h2><?php echo isset($_GET['action']) && $_GET['action'] == 'edit' ? 'Edytuj usługę' : 'Dodaj nową usługę'; ?></h2>
                    
                    <?php
                    $service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
                    $service = null;
                    if ($service_id > 0) {
                        $service = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}time_tracker_client_services WHERE id = %d",
                            $service_id
                        ));
                    }
                    ?>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('save_service', 'service_nonce'); ?>
                        <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                        
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
                                <th scope="row"><label for="service_type">Typ usługi</label></th>
                                <td>
                                    <select name="service_type" id="service_type">
                                        <option value="hourly" <?php echo ($service && $service->is_fixed_price == 0) ? 'selected' : ''; ?>>Stawka godzinowa</option>
                                        <option value="fixed" <?php echo ($service && $service->is_fixed_price == 1) ? 'selected' : ''; ?>>Ryczałt</option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr id="hourly_rate_row" style="<?php echo ($service && $service->is_fixed_price == 1) ? 'display: none;' : ''; ?>">
                                <th scope="row"><label for="hourly_rate">Stawka godzinowa (zł) *</label></th>
                                <td>
                                    <input type="number" name="hourly_rate" id="hourly_rate" 
                                           value="<?php echo $service ? esc_attr($service->hourly_rate) : ''; ?>" 
                                           step="0.01" min="0" class="small-text" required>
                                    <p class="description">Stawka za godzinę pracy</p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="save_service" class="button button-primary" value="Zapisz usługę">
                            <?php if (isset($_GET['action']) && $_GET['action'] == 'edit'): ?>
                                <a href="<?php echo admin_url('admin.php?page=time-tracker-client-services&client_id=' . $client_id); ?>" class="button">Anuluj</a>
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function admin_services_page() {
        if (!current_user_can('manage_clients')) {
            wp_die('Brak uprawnień');
        }
        
        global $wpdb;
        
        // Pobierz wszystkie usługi z informacjami o klientach
        $services = $wpdb->get_results(
            "SELECT cs.*, 
                    c.company_name,
                    c.id as client_id
             FROM {$wpdb->prefix}time_tracker_client_services cs
             LEFT JOIN {$wpdb->prefix}time_tracker_clients c ON cs.client_id = c.id
             WHERE c.active = 1
             ORDER BY c.company_name, cs.service_name"
        );
        
        // Grupuj usługi po klientach dla lepszego widoku
        $grouped_services = array();
        foreach ($services as $service) {
            if (!isset($grouped_services[$service->client_id])) {
                $grouped_services[$service->client_id] = array(
                    'company_name' => $service->company_name,
                    'services' => array()
                );
            }
            $grouped_services[$service->client_id]['services'][] = $service;
        }
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Usługi</h1>
            <a href="<?php echo admin_url('admin.php?page=time-tracker-clients&action=add'); ?>" class="page-title-action">
                Dodaj nowego klienta
            </a>
            
            <hr class="wp-header-end">
            
            <?php if (isset($_GET['service_saved']) && $_GET['service_saved'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Usługa została zapisana.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['service_deleted']) && $_GET['service_deleted'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Usługa została usunięta.</p>
                </div>
            <?php endif; ?>
            
            <div class="time-tracker-services-overview">
                <?php if (empty($grouped_services)): ?>
                    <p>Brak usług w systemie. <a href="<?php echo admin_url('admin.php?page=time-tracker-clients'); ?>">Dodaj klienta</a>, a następnie dodaj jego usługi.</p>
                <?php else: ?>
                    <?php foreach ($grouped_services as $client_id => $client_data): ?>
                    <div class="client-services-section">
                        <h2><?php echo esc_html($client_data['company_name']); ?></h2>
                        <a href="<?php echo admin_url('admin.php?page=time-tracker-client-services&client_id=' . $client_id); ?>" 
                           class="button button-small" style="margin-bottom: 15px;">
                            Zarządzaj usługami klienta
                        </a>
                        
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Nazwa usługi</th>
                                    <th>Typ</th>
                                    <th>Stawka</th>
                                    <th>Utworzono</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($client_data['services'] as $service): ?>
                                <tr>
                                    <td><?php echo esc_html($service->service_name); ?></td>
                                    <td>
                                        <?php echo $service->is_fixed_price == 1 ? 'Ryczałt' : 'Godzinowa'; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($service->is_fixed_price == 1) {
                                            echo 'Ryczałt';
                                        } else {
                                            echo number_format(floatval($service->hourly_rate), 2) . ' zł/h';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('d.m.Y', strtotime($service->created_at)); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=time-tracker-client-services&action=edit&client_id=' . $client_id . '&id=' . $service->id); ?>" 
                                           class="button button-small">Edytuj</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-client-services&time_tracker_action=delete_service&client_id=' . $client_id . '&id=' . $service->id), 'delete_service_' . $service->id); ?>" 
                                           class="button button-small button-danger" 
                                           onclick="return confirm('Czy na pewno chcesz usunąć tę usługę?');">Usuń</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="services-stats">
                <h3>Statystyki usług</h3>
                <?php
                $total_services = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}time_tracker_client_services");
                $hourly_services = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}time_tracker_client_services WHERE is_fixed_price = 0");
                $fixed_services = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}time_tracker_client_services WHERE is_fixed_price = 1");
                ?>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_services; ?></div>
                        <div class="stat-label">Łączna liczba usług</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $hourly_services; ?></div>
                        <div class="stat-label">Usługi godzinowe</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $fixed_services; ?></div>
                        <div class="stat-label">Usługi ryczałtowe</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($grouped_services); ?></div>
                        <div class="stat-label">Klientów z usługami</div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .time-tracker-services-overview {
            margin-top: 20px;
        }
        
        .client-services-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
        }
        
        .client-services-section h2 {
            margin-top: 0;
            color: #0073aa;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .services-stats {
            margin-top: 40px;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .services-stats h3 {
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    private function save_service() {
        if (!wp_verify_nonce($_POST['service_nonce'], 'save_service')) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        global $wpdb;
        
        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        $data = array(
            'client_id' => $client_id,
            'service_name' => isset($_POST['service_name']) ? sanitize_text_field($_POST['service_name']) : '',
            'is_fixed_price' => (isset($_POST['service_type']) && $_POST['service_type'] == 'fixed') ? 1 : 0,
            'hourly_rate' => (isset($_POST['service_type']) && $_POST['service_type'] == 'hourly' && isset($_POST['hourly_rate'])) ? floatval($_POST['hourly_rate']) : null
        );
        
        if (empty($data['service_name'])) {
            wp_die('Nazwa usługi nie może być pusta');
        }
        
        if ($service_id > 0) {
            $wpdb->update("{$wpdb->prefix}time_tracker_client_services", $data, array('id' => $service_id));
        } else {
            $wpdb->insert("{$wpdb->prefix}time_tracker_client_services", $data);
        }
        
        wp_redirect(admin_url('admin.php?page=time-tracker-client-services&client_id=' . $client_id . '&service_saved=1'));
        exit;
    }
    
    private function delete_service() {
        $service_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_service_' . $service_id)) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        if ($service_id > 0) {
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}time_tracker_client_services", array('id' => $service_id));
        }
        
        wp_redirect(admin_url('admin.php?page=time-tracker-client-services&client_id=' . $client_id . '&service_deleted=1'));
        exit;
    }

    private function save_project() {
        if (!wp_verify_nonce($_POST['project_nonce'], 'save_project')) {
            wp_die('Błąd bezpieczeństwa');
        }

        global $wpdb;

        if (!$this->ensure_project_schema()) {
            wp_die('Nie udało się przygotować struktury projektów w bazie danych.');
        }

        $client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $month = isset($_POST['month']) ? intval($_POST['month']) : intval(date('n'));
        $year = isset($_POST['year']) ? intval($_POST['year']) : intval(date('Y'));
        $project_name = isset($_POST['project_name']) ? sanitize_text_field($_POST['project_name']) : '';

        if (!$client_id || empty($project_name) || $month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            wp_die('Nieprawidłowe dane projektu');
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}time_tracker_projects WHERE client_id = %d AND project_name = %s AND month = %d AND year = %d",
            $client_id,
            $project_name,
            $month,
            $year
        ));

        if (!$exists) {
            $wpdb->insert(
                "{$wpdb->prefix}time_tracker_projects",
                array(
                    'client_id' => $client_id,
                    'project_name' => $project_name,
                    'month' => $month,
                    'year' => $year,
                    'created_by' => get_current_user_id()
                )
            );
        }

        wp_redirect(admin_url('admin.php?page=time-tracker-projects&project_saved=1&month=' . $month . '&year=' . $year));
        exit;
    }

    private function delete_project() {
        $project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_project_' . $project_id)) {
            wp_die('Błąd bezpieczeństwa');
        }

        if ($project_id > 0) {
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}time_tracker_projects", array('id' => $project_id));
        }

        wp_redirect(admin_url('admin.php?page=time-tracker-projects&project_deleted=1'));
        exit;
    }
    
    public function admin_entries_page() {
        if (!current_user_can('view_all_time_entries')) {
            wp_die('Brak uprawnień');
        }
        
        // Pobierz parametry filtrowania
        $filter_month = isset($_GET['filter_month']) ? intval($_GET['filter_month']) : date('n');
        $filter_year = isset($_GET['filter_year']) ? intval($_GET['filter_year']) : date('Y');
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        $filter_client = isset($_GET['filter_client']) ? intval($_GET['filter_client']) : 0;
        $filter_employee = isset($_GET['filter_employee']) ? intval($_GET['filter_employee']) : 0;
        
        global $wpdb;
        
        // Pobierz pracowników dla filtra
        $employees = $wpdb->get_results(
            "SELECT u.ID, u.display_name 
             FROM {$wpdb->prefix}users u
             INNER JOIN {$wpdb->prefix}usermeta um ON u.ID = um.user_id
             WHERE um.meta_key = 'wp_capabilities'
             AND (um.meta_value LIKE '%\"add_time_entries\"%' OR um.meta_value LIKE '%\"manage_time_tracker\"%')
             GROUP BY u.ID
             ORDER BY u.display_name"
        );
        
        // Pobierz klientów dla filtra
        $clients = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}time_tracker_clients WHERE active = 1 ORDER BY company_name"
        );
        ?>
        <div class="wrap">
            <h1>Wpisy godzinowe</h1>
            
            <!-- Formularz filtrów -->
            <div class="time-tracker-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="time-tracker-entries">
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Miesiąc:</label>
                            <select name="filter_month">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($i, $filter_month); ?>>
                                        <?php echo date_i18n('F', mktime(0, 0, 0, $i, 1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Rok:</label>
                            <select name="filter_year">
                                <?php for ($i = date('Y') - 1; $i <= date('Y') + 1; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($i, $filter_year); ?>>
                                        <?php echo $i; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Status:</label>
                            <select name="filter_status">
                                <option value="">Wszystkie</option>
                                <option value="draft" <?php selected($filter_status, 'draft'); ?>>Szkic</option>
                                <option value="submitted" <?php selected($filter_status, 'submitted'); ?>>Zgłoszony</option>
                                <option value="approved" <?php selected($filter_status, 'approved'); ?>>Zatwierdzony</option>
                                <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>Odrzucony</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>Pracownik:</label>
                            <select name="filter_employee">
                                <option value="">Wszyscy</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee->ID; ?>" <?php selected($filter_employee, $employee->ID); ?>>
                                        <?php echo esc_html($employee->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label>Klient:</label>
                            <select name="filter_client">
                                <option value="">Wszyscy</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client->id; ?>" <?php selected($filter_client, $client->id); ?>>
                                        <?php echo esc_html($client->company_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <input type="submit" value="Filtruj" class="button button-primary">
                            <a href="<?php echo admin_url('admin.php?page=time-tracker-entries'); ?>" class="button">Wyczyść</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <?php if (isset($_GET['entry_approved']) && $_GET['entry_approved'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Wpis został zatwierdzony.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['entry_rejected']) && $_GET['entry_rejected'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Wpis został odrzucony.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['entry_deleted']) && $_GET['entry_deleted'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Wpis został usunięty.</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['entry_updated']) && $_GET['entry_updated'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Wpis został zaktualizowany.</p>
                </div>
            <?php endif; ?>
            
            <?php
            global $wpdb;
            
            // Zbuduj zapytanie z filtrami
            $where_conditions = array("1=1");
            $query_params = array();
            
            // Filtruj po miesiącu i roku
            if ($filter_month && $filter_year) {
                $where_conditions[] = "MONTH(te.entry_date) = %d AND YEAR(te.entry_date) = %d";
                $query_params[] = $filter_month;
                $query_params[] = $filter_year;
            }
            
            // Filtruj po statusie
            if ($filter_status) {
                $where_conditions[] = "te.status = %s";
                $query_params[] = $filter_status;
            }
            
            // Filtruj po kliencie
            if ($filter_client > 0) {
                $where_conditions[] = "te.client_id = %d";
                $query_params[] = $filter_client;
            }
            
            // Filtruj po pracowniku
            if ($filter_employee > 0) {
                $where_conditions[] = "te.employee_id = %d";
                $query_params[] = $filter_employee;
            }
            
            $where_clause = implode(" AND ", $where_conditions);
            
            $query = $wpdb->prepare(
                "SELECT te.*, 
                        c.company_name,
                        cs.service_name,
                        p.project_name,
                        u.display_name as employee_name
                 FROM {$wpdb->prefix}time_tracker_entries te
                 LEFT JOIN {$wpdb->prefix}time_tracker_clients c ON te.client_id = c.id
                 LEFT JOIN {$wpdb->prefix}time_tracker_client_services cs ON te.service_id = cs.id
                 LEFT JOIN {$wpdb->prefix}time_tracker_projects p ON te.project_id = p.id
                 LEFT JOIN {$wpdb->prefix}users u ON te.employee_id = u.ID
                 WHERE $where_clause
                 ORDER BY te.entry_date DESC, te.created_at DESC
                 LIMIT 100",
                $query_params
            );
            
            $entries = $wpdb->get_results($query);
            
            if (empty($entries)): ?>
                <p>Brak wpisów godzinowych dla wybranych filtrów.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Pracownik</th>
                            <th>Klient</th>
                            <th>Projekt</th>
                            <th>Usługa</th>
                            <th>Godziny/Kwota</th>
                            <th>Status</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td><?php echo date('d.m.Y', strtotime($entry->entry_date)); ?></td>
                            <td><?php echo esc_html($entry->employee_name); ?></td>
                            <td><?php echo esc_html($entry->company_name); ?></td>
                            <td><?php echo !empty($entry->project_name) ? esc_html($entry->project_name) : '-'; ?></td>
                            <td><?php echo esc_html($entry->service_name); ?></td>
                            <td>
                                <?php 
                                if (!empty($entry->fixed_amount)) {
                                    echo number_format(floatval($entry->fixed_amount), 2) . ' zł';
                                } elseif (!empty($entry->hours)) {
                                    echo number_format(floatval($entry->hours), 2) . ' h';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo esc_attr($entry->status); ?>">
                                    <?php 
                                    $status_labels = array(
                                        'draft' => 'Szkic',
                                        'submitted' => 'Zgłoszony',
                                        'approved' => 'Zatwierdzony',
                                        'rejected' => 'Odrzucony'
                                    );
                                    echo isset($status_labels[$entry->status]) ? $status_labels[$entry->status] : $entry->status;
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($entry->status == 'submitted'): ?>
                                    <div class="entry-actions">
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-entries&time_tracker_action=approve_entry&entry_id=' . $entry->id), 'approve_entry_' . $entry->id); ?>" 
                                           class="button button-small button-success">Zatwierdź</a>
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-entries&time_tracker_action=reject_entry&entry_id=' . $entry->id), 'reject_entry_' . $entry->id); ?>" 
                                           class="button button-small button-danger">Odrzuć</a>
                                        <a href="<?php echo admin_url('admin.php?page=time-tracker-edit-entry&id=' . $entry->id); ?>" 
                                           class="button button-small">Edytuj</a>
                                    </div>
                                <?php elseif ($entry->status == 'approved'): ?>
                                    <span class="approved-text">Zatwierdzono</span>
                                    <br>
                                    <small><?php echo date('d.m.Y H:i', strtotime($entry->approved_date)); ?></small>
                                <?php elseif ($entry->status == 'rejected'): ?>
                                    <span class="rejected-text">Odrzucono</span>
                                    <?php if (!empty($entry->rejection_reason)): ?>
                                        <br>
                                        <small>Powód: <?php echo esc_html($entry->rejection_reason); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="draft-text">Szkic</span>
                                    <a href="<?php echo admin_url('admin.php?page=time-tracker-edit-entry&id=' . $entry->id); ?>" 
                                       class="button button-small" style="margin-left: 5px;">Edytuj</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function admin_edit_entry_page() {
        if (!current_user_can('edit_time_entries') && !current_user_can('manage_time_tracker')) {
            wp_die('Brak uprawnień do edycji wpisów.');
        }

        $entry_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if (!$entry_id) {
            wp_die('Nieprawidłowy wpis');
        }

        // Obsługa formularza - jeśli formularz został wysłany, nie wyświetlaj już nic
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_entry'])) {
            $this->update_time_entry($entry_id);
            return; // To zatrzyma dalsze wykonywanie kodu
        }

        global $wpdb;
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT te.*, 
                    c.company_name,
                    cs.service_name,
                    cs.is_fixed_price,
                    cs.hourly_rate,
                    u.display_name as employee_name
             FROM {$wpdb->prefix}time_tracker_entries te
             LEFT JOIN {$wpdb->prefix}time_tracker_clients c ON te.client_id = c.id
             LEFT JOIN {$wpdb->prefix}time_tracker_client_services cs ON te.service_id = cs.id
             LEFT JOIN {$wpdb->prefix}time_tracker_projects p ON te.project_id = p.id
             LEFT JOIN {$wpdb->prefix}users u ON te.employee_id = u.ID
             WHERE te.id = %d",
            $entry_id
        ));

        if (!$entry) {
            wp_die('Wpis nie istnieje');
        }

        // Pobierz wszystkich aktywnych klientów
        $clients = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}time_tracker_clients 
             WHERE active = 1 
             ORDER BY company_name"
        );

        // Pobierz usługi dla danego klienta
        $services = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}time_tracker_client_services 
             WHERE client_id = %d 
             ORDER BY service_name",
            $entry->client_id
        ));
        ?>
        
        <div class="wrap">
            <h1 class="wp-heading-inline">Edytuj wpis godzinowy</h1>
            <a href="<?php echo admin_url('admin.php?page=time-tracker-entries'); ?>" class="page-title-action">
                Wróć do listy wpisów
            </a>
            
            <hr class="wp-header-end">
            
            <?php if (isset($_GET['entry_updated']) && $_GET['entry_updated'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Wpis został zaktualizowany.</p>
                </div>
            <?php endif; ?>
            
            <div class="entry-info">
                <p><strong>Pracownik:</strong> <?php echo esc_html($entry->employee_name); ?></p>
                <p><strong>Utworzono:</strong> <?php echo date('d.m.Y H:i', strtotime($entry->created_at)); ?></p>
                <?php if ($entry->submission_date): ?>
                    <p><strong>Zgłoszono:</strong> <?php echo date('d.m.Y H:i', strtotime($entry->submission_date)); ?></p>
                <?php endif; ?>
            </div>
            
            <form method="post" action="" id="edit-entry-form">
                <?php wp_nonce_field('save_entry', 'entry_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="client_id">Klient *</label></th>
                        <td>
                            <select name="client_id" id="client_id" required>
                                <option value="">Wybierz klienta...</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client->id; ?>" <?php selected($client->id, $entry->client_id); ?>>
                                        <?php echo esc_html($client->company_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="entry_date">Data *</label></th>
                        <td>
                            <input type="date" name="entry_date" id="entry_date" 
                                   value="<?php echo date('Y-m-d', strtotime($entry->entry_date)); ?>" 
                                   required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="service_id">Usługa *</label></th>
                        <td>
                            <select name="service_id" id="service_id" required>
                                <option value="">Wybierz usługę...</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?php echo $service->id; ?>" 
                                            data-is-fixed="<?php echo $service->is_fixed_price; ?>"
                                            <?php selected($service->id, $entry->service_id); ?>>
                                        <?php echo esc_html($service->service_name); ?>
                                        <?php if ($service->is_fixed_price == 0): ?>
                                            (<?php echo number_format($service->hourly_rate, 2); ?> zł/h)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr id="hours-field" style="<?php echo $entry->is_fixed_price == 1 ? 'display: none;' : ''; ?>">
                        <th scope="row"><label for="hours">Liczba godzin *</label></th>
                        <td>
                            <input type="number" name="hours" id="hours" 
                                   value="<?php echo $entry->hours ? number_format($entry->hours, 2) : ''; ?>" 
                                   step="0.25" min="0.25" max="24" <?php echo $entry->is_fixed_price == 0 ? 'required' : ''; ?>>
                            <p class="description">Liczba godzin (np. 1.5)</p>
                        </td>
                    </tr>
                    
                    <tr id="fixed-amount-field" style="<?php echo $entry->is_fixed_price == 0 ? 'display: none;' : ''; ?>">
                        <th scope="row"><label for="fixed_amount">Kwota ryczałtowa *</label></th>
                        <td>
                            <input type="number" name="fixed_amount" id="fixed_amount" 
                                   value="<?php echo $entry->fixed_amount ? number_format($entry->fixed_amount, 2) : ''; ?>" 
                                   step="0.01" min="0" <?php echo $entry->is_fixed_price == 1 ? 'required' : ''; ?>>
                            <p class="description">Kwota dla usługi ryczałtowej</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="description">Opis zadania</label></th>
                        <td>
                            <textarea name="description" id="description" rows="4" class="regular-text"><?php 
                                echo esc_textarea($entry->description); 
                            ?></textarea>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="status">Status</label></th>
                        <td>
                            <select name="status" id="status">
                                <option value="draft" <?php selected($entry->status, 'draft'); ?>>Szkic</option>
                                <option value="submitted" <?php selected($entry->status, 'submitted'); ?>>Zgłoszony</option>
                                <option value="approved" <?php selected($entry->status, 'approved'); ?>>Zatwierdzony</option>
                                <option value="rejected" <?php selected($entry->status, 'rejected'); ?>>Odrzucony</option>
                            </select>
                        </td>
                    </tr>
                    
                    <?php if ($entry->status == 'rejected' && !empty($entry->rejection_reason)): ?>
                    <tr>
                        <th scope="row"><label>Powód odrzucenia</label></th>
                        <td>
                            <p><?php echo esc_html($entry->rejection_reason); ?></p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr id="rejection_reason_row" style="<?php echo $entry->status == 'rejected' ? '' : 'display: none;'; ?>">
                        <th scope="row"><label for="rejection_reason">Nowy powód odrzucenia</label></th>
                        <td>
                            <textarea name="rejection_reason" id="rejection_reason" rows="2" class="regular-text"><?php 
                                echo $entry->status == 'rejected' ? esc_textarea($entry->rejection_reason) : ''; 
                            ?></textarea>
                            <p class="description">Wypełnij tylko jeśli zmieniasz status na "Odrzucony"</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_entry" class="button button-primary" value="Zapisz zmiany">
                    <a href="<?php echo admin_url('admin.php?page=time-tracker-entries'); ?>" class="button">Anuluj</a>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-edit-entry&time_tracker_action=delete_entry&id=' . $entry_id), 'delete_entry_' . $entry_id); ?>" 
                       class="button button-danger" 
                       onclick="return confirm('Czy na pewno chcesz usunąć ten wpis?');">Usuń wpis</a>
                </p>
            </form>
        </div>
        <?php
    }
    
    private function approve_time_entry() {
        $entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'approve_entry_' . $entry_id)) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        if ($entry_id > 0) {
            global $wpdb;
            $wpdb->update(
                "{$wpdb->prefix}time_tracker_entries",
                array(
                    'status' => 'approved',
                    'approved_by' => get_current_user_id(),
                    'approved_date' => current_time('mysql')
                ),
                array('id' => $entry_id)
            );
            
            // Wyślij powiadomienie
            $this->send_entry_notification($entry_id, 'approved');
        }
        
        wp_redirect(admin_url('admin.php?page=time-tracker-entries&entry_approved=1'));
        exit;
    }
    
    private function reject_time_entry() {
        $entry_id = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'reject_entry_' . $entry_id)) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        if ($entry_id > 0) {
            global $wpdb;
            $wpdb->update(
                "{$wpdb->prefix}time_tracker_entries",
                array(
                    'status' => 'rejected',
                    'approved_by' => get_current_user_id(),
                    'approved_date' => current_time('mysql')
                ),
                array('id' => $entry_id)
            );
            
            // Wyślij powiadomienie
            $this->send_entry_notification($entry_id, 'rejected');
        }
        
        wp_redirect(admin_url('admin.php?page=time-tracker-entries&entry_rejected=1'));
        exit;
    }
    
    private function update_time_entry($entry_id) {
        if (!wp_verify_nonce($_POST['entry_nonce'], 'save_entry')) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        global $wpdb;
        
        // Pobierz oryginalny wpis
        $original_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}time_tracker_entries WHERE id = %d",
            $entry_id
        ));
        
        if (!$original_entry) {
            wp_die('Wpis nie istnieje');
        }
        
        $data = array(
            'client_id' => isset($_POST['client_id']) ? intval($_POST['client_id']) : $original_entry->client_id,
            'service_id' => isset($_POST['service_id']) ? intval($_POST['service_id']) : $original_entry->service_id,
            'entry_date' => isset($_POST['entry_date']) ? sanitize_text_field($_POST['entry_date']) : $original_entry->entry_date,
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : $original_entry->description,
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : $original_entry->status,
            'updated_at' => current_time('mysql')
        );
        
        // Sprawdź czy usługa ma stawkę godzinową czy ryczałt
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT is_fixed_price FROM {$wpdb->prefix}time_tracker_client_services WHERE id = %d",
            $data['service_id']
        ));
        
        if ($service && $service->is_fixed_price == 1) {
            $data['fixed_amount'] = isset($_POST['fixed_amount']) ? floatval($_POST['fixed_amount']) : 0;
            $data['hours'] = null;
        } else {
            $data['hours'] = isset($_POST['hours']) ? floatval($_POST['hours']) : 0;
            $data['fixed_amount'] = null;
        }
        
        // Jeśli status zmienia się na "submitted" i był inny, ustaw datę zgłoszenia
        if ($data['status'] === 'submitted' && $original_entry->status !== 'submitted') {
            $data['submission_date'] = current_time('mysql');
            $this->send_entry_notification($entry_id, 'submitted');
        }
        
        // Jeśli status zmienia się na "approved" i był inny, ustaw datę zatwierdzenia
        if ($data['status'] === 'approved' && $original_entry->status !== 'approved') {
            $data['approved_by'] = get_current_user_id();
            $data['approved_date'] = current_time('mysql');
            $data['rejection_reason'] = null;
            $this->send_entry_notification($entry_id, 'approved');
        }
        
        // Jeśli status zmienia się na "rejected" i był inny, ustaw powód
        if ($data['status'] === 'rejected' && $original_entry->status !== 'rejected') {
            $data['approved_by'] = get_current_user_id();
            $data['approved_date'] = current_time('mysql');
            $data['rejection_reason'] = isset($_POST['rejection_reason']) ? sanitize_textarea_field($_POST['rejection_reason']) : null;
            $this->send_entry_notification($entry_id, 'rejected');
        }
        
        // Jeśli status się nie zmienia, ale był rejected, zachowaj rejection_reason
        if ($data['status'] === 'rejected' && $original_entry->status === 'rejected') {
            $data['rejection_reason'] = isset($_POST['rejection_reason']) ? sanitize_textarea_field($_POST['rejection_reason']) : $original_entry->rejection_reason;
        }
        
        $result = $wpdb->update(
            "{$wpdb->prefix}time_tracker_entries",
            $data,
            array('id' => $entry_id)
        );
        
        if ($result !== false) {
            // Dodaj warunek, który sprawdza czy już nie przekierowano
            if (!headers_sent()) {
                wp_redirect(admin_url('admin.php?page=time-tracker-edit-entry&id=' . $entry_id . '&entry_updated=1'));
                exit;
            } else {
                echo '<script>window.location.href="' . admin_url('admin.php?page=time-tracker-edit-entry&id=' . $entry_id . '&entry_updated=1') . '";</script>';
                echo '<noscript><meta http-equiv="refresh" content="0;url=' . admin_url('admin.php?page=time-tracker-edit-entry&id=' . $entry_id . '&entry_updated=1') . '"></noscript>';
                return;
            }
        } else {
            wp_die('Wystąpił błąd podczas aktualizacji wpisu.');
        }
    }
    
    private function delete_time_entry() {
        $entry_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'delete_entry_' . $entry_id)) {
            wp_die('Błąd bezpieczeństwa');
        }
        
        if ($entry_id > 0) {
            global $wpdb;
            $wpdb->delete("{$wpdb->prefix}time_tracker_entries", array('id' => $entry_id));
        }
        
        wp_redirect(admin_url('admin.php?page=time-tracker-entries&entry_deleted=1'));
        exit;
    }
    
    public function admin_projects_page() {
        if (!current_user_can('manage_clients')) {
            wp_die('Brak uprawnień');
        }

        if (!$this->ensure_project_schema()) {
            echo '<div class="notice notice-error"><p>Nie udało się zainicjalizować tabel projektów. Sprawdź uprawnienia użytkownika bazy danych.</p></div>';
            return;
        }

        $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

        global $wpdb;
        $clients = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}time_tracker_clients WHERE active = 1 ORDER BY company_name");

        $projects = $wpdb->get_results($wpdb->prepare(
            "SELECT p.*, c.company_name
             FROM {$wpdb->prefix}time_tracker_projects p
             LEFT JOIN {$wpdb->prefix}time_tracker_clients c ON p.client_id = c.id
             WHERE p.month = %d AND p.year = %d
             ORDER BY c.company_name, p.project_name",
            $month,
            $year
        ));
        ?>
        <div class="wrap">
            <h1>Projekty klientów</h1>

            <?php if (isset($_GET['project_saved']) && $_GET['project_saved'] == '1'): ?>
                <div class="notice notice-success is-dismissible"><p>Projekt został zapisany.</p></div>
            <?php endif; ?>
            <?php if (isset($_GET['project_deleted']) && $_GET['project_deleted'] == '1'): ?>
                <div class="notice notice-success is-dismissible"><p>Projekt został usunięty.</p></div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('save_project', 'project_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="project_client_id">Klient</label></th>
                        <td>
                            <select name="client_id" id="project_client_id" required>
                                <option value="">Wybierz klienta...</option>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?php echo $client->id; ?>"><?php echo esc_html($client->company_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="project_name">Nazwa projektu</label></th>
                        <td><input type="text" name="project_name" id="project_name" class="regular-text" required></td>
                    </tr>
                    <tr>
                        <th><label for="project_month">Miesiąc</label></th>
                        <td>
                            <select name="month" id="project_month" required>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($i, $month); ?>><?php echo date_i18n('F', mktime(0, 0, 0, $i, 1)); ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="project_year">Rok</label></th>
                        <td>
                            <select name="year" id="project_year" required>
                                <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($i, $year); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <p><input type="submit" name="save_project" class="button button-primary" value="Dodaj projekt"></p>
            </form>

            <hr>
            <h2>Lista projektów: <?php echo date_i18n('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h2>
            <?php if (empty($projects)): ?>
                <p>Brak projektów dla wybranego okresu.</p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Klient</th><th>Projekt</th><th>Miesiąc/Rok</th><th>Akcje</th></tr></thead>
                    <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?php echo esc_html($project->company_name); ?></td>
                            <td><?php echo esc_html($project->project_name); ?></td>
                            <td><?php echo intval($project->month) . '/' . intval($project->year); ?></td>
                            <td>
                                <a class="button button-small button-danger" href="<?php echo wp_nonce_url(admin_url('admin.php?page=time-tracker-projects&time_tracker_action=delete_project&id=' . $project->id), 'delete_project_' . $project->id); ?>" onclick="return confirm('Czy na pewno chcesz usunąć ten projekt?');">Usuń</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function admin_reports_page() {
        if (!current_user_can('generate_reports')) {
            wp_die('Brak uprawnień');
        }
        
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $client_id = isset($_GET['client_id']) ? $_GET['client_id'] : 'all';

        global $wpdb;
        $clients = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}time_tracker_clients WHERE active = 1 ORDER BY company_name");
        
        ?>
        <div class="wrap">
            <h1>Zestawienia</h1>
            <p><a class="button" href="<?php echo admin_url('admin.php?page=time-tracker-projects'); ?>">Przejdź do modułu Projektów</a></p>

            <?php if (isset($_GET['project_saved']) && $_GET['project_saved'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Projekt został zapisany.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['project_deleted']) && $_GET['project_deleted'] == '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Projekt został usunięty.</p>
                </div>
            <?php endif; ?>

            <div class="report-generator">
                <h3>Generuj zestawienie miesięczne</h3>
                <form method="get" action="">
                    <input type="hidden" name="page" value="time-tracker-reports">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="month">Miesiąc</label></th>
                            <td>
                                <select name="month" id="month">
                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php selected($i, $month); ?>>
                                            <?php echo date_i18n('F', mktime(0, 0, 0, $i, 1)); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="year">Rok</label></th>
                            <td>
                                <select name="year" id="year">
                                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php selected($i, $year); ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="client_id">Klient</label></th>
                            <td>
                                <select name="client_id" id="client_id">
                                    <option value="all" <?php selected($client_id, 'all'); ?>>Wszyscy klienci</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client->id; ?>" <?php selected($client_id, $client->id); ?>>
                                            <?php echo esc_html($client->company_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="generate_report" class="button button-primary" value="Generuj zestawienie">
                    </p>
                </form>
                
                <?php if (isset($_GET['generate_report'])): ?>
                    <?php $this->generate_monthly_report($month, $year, $client_id); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private function generate_monthly_report($month, $year, $client_id) {
        global $wpdb;
        
        $where_clause = "WHERE te.status = 'approved' 
                         AND MONTH(te.entry_date) = %d 
                         AND YEAR(te.entry_date) = %d";
        
        $params = array($month, $year);
        
        if ($client_id != 'all') {
            $where_clause .= " AND te.client_id = %d";
            $params[] = intval($client_id);
        }
        
        $query = $wpdb->prepare(
            "SELECT te.*, 
                    c.company_name,
                    c.nip,
                    c.address,
                    cs.service_name,
                    cs.hourly_rate,
                    cs.is_fixed_price,
                    p.project_name,
                    u.display_name as employee_name
             FROM {$wpdb->prefix}time_tracker_entries te
             LEFT JOIN {$wpdb->prefix}time_tracker_clients c ON te.client_id = c.id
             LEFT JOIN {$wpdb->prefix}time_tracker_client_services cs ON te.service_id = cs.id
             LEFT JOIN {$wpdb->prefix}time_tracker_projects p ON te.project_id = p.id
             LEFT JOIN {$wpdb->prefix}users u ON te.employee_id = u.ID
             $where_clause
             ORDER BY c.company_name, te.entry_date, u.display_name",
            $params
        );
        
        $entries = $wpdb->get_results($query);
        
        if (empty($entries)) {
            echo '<p>Brak zatwierdzonych wpisów dla wybranego okresu.</p>';
            return;
        }
        
        // Grupuj wpisy po kliencie
        $grouped_entries = array();
        $grand_total_hours = 0;
        $grand_total_amount = 0;
        
        foreach ($entries as $entry) {
            if (!isset($grouped_entries[$entry->client_id])) {
                $grouped_entries[$entry->client_id] = array(
                    'client' => $entry,
                    'entries' => array(),
                    'total_hours' => 0,
                    'total_amount' => 0,
                    'services_summary' => array(),
                    'projects_summary' => array()
                );
            }
            $grouped_entries[$entry->client_id]['entries'][] = $entry;
            
            // Oblicz kwotę
            if ($entry->is_fixed_price == 1) {
                $amount = floatval($entry->fixed_amount);
            } else {
                $amount = floatval($entry->hours) * floatval($entry->hourly_rate);
            }
            
            $hours = ($entry->is_fixed_price == 0) ? floatval($entry->hours) : 0;
            
            $grouped_entries[$entry->client_id]['total_hours'] += $hours;
            $grouped_entries[$entry->client_id]['total_amount'] += $amount;
            
            // Grupuj też po usłudze dla podsumowania
            $service_key = $entry->service_name . '|' . ($entry->is_fixed_price ? 'fixed' : 'hourly');
            if (!isset($grouped_entries[$entry->client_id]['services_summary'][$service_key])) {
                $grouped_entries[$entry->client_id]['services_summary'][$service_key] = array(
                    'service_name' => $entry->service_name,
                    'type' => $entry->is_fixed_price == 1 ? 'Ryczałt' : 'Godzinowa',
                    'rate' => $entry->hourly_rate,
                    'total_hours' => 0,
                    'total_amount' => 0,
                    'entries_count' => 0
                );
            }
            $grouped_entries[$entry->client_id]['services_summary'][$service_key]['total_hours'] += $hours;
            $grouped_entries[$entry->client_id]['services_summary'][$service_key]['total_amount'] += $amount;
            $grouped_entries[$entry->client_id]['services_summary'][$service_key]['entries_count']++;

            // Grupowanie po projekcie
            $project_name = !empty($entry->project_name) ? $entry->project_name : 'Bez projektu';
            if (!isset($grouped_entries[$entry->client_id]['projects_summary'][$project_name])) {
                $grouped_entries[$entry->client_id]['projects_summary'][$project_name] = array(
                    'project_name' => $project_name,
                    'entries_count' => 0,
                    'total_hours' => 0,
                    'total_amount' => 0
                );
            }
            $grouped_entries[$entry->client_id]['projects_summary'][$project_name]['entries_count']++;
            $grouped_entries[$entry->client_id]['projects_summary'][$project_name]['total_hours'] += $hours;
            $grouped_entries[$entry->client_id]['projects_summary'][$project_name]['total_amount'] += $amount;
            
            // Sumy ogólne
            $grand_total_hours += $hours;
            $grand_total_amount += $amount;
        }
        ?>
        
        <div class="monthly-report">
            <h2>Zestawienie za: <?php echo date_i18n('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></h2>
            
            <?php foreach ($grouped_entries as $client_data): ?>
            <div class="client-report">
                <div class="client-header">
                    <h3><?php echo esc_html($client_data['client']->company_name); ?></h3>
                    <p><strong>NIP:</strong> <?php echo esc_html($client_data['client']->nip); ?></p>
                    <?php if (!empty($client_data['client']->address)): ?>
                        <p><strong>Adres:</strong> <?php echo nl2br(esc_html($client_data['client']->address)); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="summary-section">
                    <h4>Podsumowanie usług</h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Usługa</th>
                                <th>Typ</th>
                                <th>Stawka</th>
                                <th>Liczba wpisów</th>
                                <th>Łączna liczba godzin</th>
                                <th>Łączna kwota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_data['services_summary'] as $service_summary): ?>
                            <tr>
                                <td><?php echo esc_html($service_summary['service_name']); ?></td>
                                <td><?php echo esc_html($service_summary['type']); ?></td>
                                <td>
                                    <?php 
                                    if ($service_summary['type'] == 'Godzinowa') {
                                        echo number_format($service_summary['rate'], 2) . ' zł/h';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo $service_summary['entries_count']; ?></td>
                                <td><?php echo $service_summary['type'] == 'Godzinowa' ? number_format($service_summary['total_hours'], 2) . ' h' : '-'; ?></td>
                                <td><?php echo number_format($service_summary['total_amount'], 2); ?> zł</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3"><strong>Razem dla klienta:</strong></td>
                                <td><strong><?php echo count($client_data['entries']); ?></strong></td>
                                <td><strong><?php echo number_format($client_data['total_hours'], 2); ?> h</strong></td>
                                <td><strong><?php echo number_format($client_data['total_amount'], 2); ?> zł</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="project-summary-section">
                    <h4>Podsumowanie projektów</h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Projekt</th>
                                <th>Liczba wpisów</th>
                                <th>Łączna liczba godzin</th>
                                <th>Łączna kwota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_data['projects_summary'] as $project_summary): ?>
                            <tr>
                                <td><?php echo esc_html($project_summary['project_name']); ?></td>
                                <td><?php echo intval($project_summary['entries_count']); ?></td>
                                <td><?php echo number_format(floatval($project_summary['total_hours']), 2); ?> h</td>
                                <td><?php echo number_format(floatval($project_summary['total_amount']), 2); ?> zł</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="details-section">
                    <h4>Szczegółowe wpisy <small>(kliknij aby rozwinąć)</small></h4>
                    <div class="details-collapse">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Pracownik</th>
                                    <th>Projekt</th>
                                    <th>Usługa</th>
                                    <th>Godziny</th>
                                    <th>Stawka</th>
                                    <th>Kwota</th>
                                    <th>Opis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($client_data['entries'] as $entry): 
                                    $hours = ($entry->is_fixed_price == 0) ? floatval($entry->hours) : 0;
                                    $rate = ($entry->is_fixed_price == 0) ? floatval($entry->hourly_rate) : 0;
                                    $amount = ($entry->is_fixed_price == 1) ? floatval($entry->fixed_amount) : $hours * $rate;
                                ?>
                                <tr>
                                    <td><?php echo date('d.m.Y', strtotime($entry->entry_date)); ?></td>
                                    <td><?php echo esc_html($entry->employee_name); ?></td>
                                    <td><?php echo !empty($entry->project_name) ? esc_html($entry->project_name) : '-'; ?></td>
                                    <td><?php echo esc_html($entry->service_name); ?></td>
                                    <td><?php echo $hours > 0 ? number_format($hours, 2) . ' h' : '-'; ?></td>
                                    <td><?php echo $rate > 0 ? number_format($rate, 2) . ' zł/h' : 'Ryczałt'; ?></td>
                                    <td><?php echo number_format($amount, 2); ?> zł</td>
                                    <td><?php echo !empty($entry->description) ? esc_html($entry->description) : '-'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="report-actions">
                    <button class="button button-primary button-print">Drukuj zestawienie</button>
                    <button class="button button-export-csv" 
                            data-client-name="<?php echo esc_attr($client_data['client']->company_name); ?>"
                            data-month="<?php echo $month; ?>"
                            data-year="<?php echo $year; ?>">
                        Eksportuj do CSV
                    </button>
                </div>
                
                <hr style="margin: 30px 0;">
            </div>
            <?php endforeach; ?>
            
            <?php if (count($grouped_entries) > 1): ?>
            <div class="grand-total">
                <h3>Podsumowanie ogólne</h3>
                <table class="wp-list-table widefat fixed striped">
                    <tbody>
                        <tr>
                            <td><strong>Łączna liczba klientów:</strong></td>
                            <td><strong><?php echo count($grouped_entries); ?></strong></td>
                        </tr>
                        <tr>
                            <td><strong>Łączna liczba godzin:</strong></td>
                            <td><strong><?php echo number_format($grand_total_hours, 2); ?> h</strong></td>
                        </tr>
                        <tr>
                            <td><strong>Łączna kwota:</strong></td>
                            <td><strong><?php echo number_format($grand_total_amount, 2); ?> zł</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <style>
        .client-report {
            margin-bottom: 40px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 5px;
        }
        .client-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .summary-section {
            margin-bottom: 20px;
        }
        .details-section h4 {
            cursor: pointer;
            color: #0073aa;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 3px;
            margin-bottom: 10px;
        }
        .details-section h4:hover {
            background: #e5e5e5;
        }
        .details-section h4 small {
            color: #666;
            font-weight: normal;
        }
        .report-actions {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .grand-total {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        </style>
        <?php
    }
    
    public function admin_invoices_page() {
        if (!current_user_can('manage_time_tracker')) {
            wp_die('Brak uprawnień');
        }
        
        echo '<div class="wrap"><h1>Faktury (w budowie)</h1><p>Ta funkcja jest w trakcie implementacji.</p></div>';
    }
    
    public function admin_settings_page() {
        if (!current_user_can('manage_time_tracker')) {
            wp_die('Brak uprawnień');
        }
        
        // Zapisz ustawienia
        if (isset($_POST['save_settings']) && isset($_POST['settings_nonce'])) {
            if (wp_verify_nonce($_POST['settings_nonce'], 'save_settings')) {
                update_option('time_tracker_notify_on_submit', isset($_POST['notify_on_submit']) ? 1 : 0);
                update_option('time_tracker_notify_on_approve', isset($_POST['notify_on_approve']) ? 1 : 0);
                update_option('time_tracker_notify_on_reject', isset($_POST['notify_on_reject']) ? 1 : 0);
                update_option('time_tracker_send_weekly_reminders', isset($_POST['send_weekly_reminders']) ? 1 : 0);
                update_option('time_tracker_from_email', sanitize_email($_POST['from_email']));
                update_option('time_tracker_from_name', sanitize_text_field($_POST['from_name']));
                
                echo '<div class="notice notice-success is-dismissible"><p>Ustawienia zostały zapisane.</p></div>';
            }
        }
        
        $notify_on_submit = get_option('time_tracker_notify_on_submit', 1);
        $notify_on_approve = get_option('time_tracker_notify_on_approve', 1);
        $notify_on_reject = get_option('time_tracker_notify_on_reject', 1);
        $send_weekly_reminders = get_option('time_tracker_send_weekly_reminders', 1);
        $from_email = get_option('time_tracker_from_email', get_option('admin_email'));
        $from_name = get_option('time_tracker_from_name', get_bloginfo('name'));
        ?>
        <div class="wrap">
            <h1>Ustawienia Time Tracker</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('save_settings', 'settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label>Powiadomienia email</label></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Powiadomienia email</span></legend>
                                <label>
                                    <input type="checkbox" name="notify_on_submit" value="1" <?php checked($notify_on_submit, 1); ?>>
                                    Wyślij powiadomienie gdy pracownik zgłosi wpis
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="notify_on_approve" value="1" <?php checked($notify_on_approve, 1); ?>>
                                    Wyślij powiadomienie gdy wpis zostanie zatwierdzony
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="notify_on_reject" value="1" <?php checked($notify_on_reject, 1); ?>>
                                    Wyślij powiadomienie gdy wpis zostanie odrzucony
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="send_weekly_reminders" value="1" <?php checked($send_weekly_reminders, 1); ?>>
                                    Wysyłaj cotygodniowe przypomnienia
                                </label>
                                <p class="description">Przypomnienia są wysyłane w poniedziałki o 9:00</p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="from_email">Email nadawcy</label></th>
                        <td>
                            <input type="email" name="from_email" id="from_email" 
                                   value="<?php echo esc_attr($from_email); ?>" class="regular-text">
                            <p class="description">Adres email, z którego będą wysyłane powiadomienia</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="from_name">Nazwa nadawcy</label></th>
                        <td>
                            <input type="text" name="from_name" id="from_name" 
                                   value="<?php echo esc_attr($from_name); ?>" class="regular-text">
                            <p class="description">Nazwa, która będzie wyświetlana jako nadawca</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_settings" class="button button-primary" value="Zapisz ustawienia">
                </p>
            </form>
        </div>
        <?php
    }
    
    // Shortcode: Formularz dodawania wpisów
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
        global $wpdb;
        $clients = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}time_tracker_clients 
             WHERE active = 1 
             ORDER BY company_name"
        );
        
        // Obsługa formularza
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_time_entry'])) {
            if (!wp_verify_nonce($_POST['time_entry_nonce'], 'add_time_entry')) {
                echo '<div class="error"><p>Błąd bezpieczeństwa.</p></div>';
            } else {
                $this->save_time_entry($_POST);
            }
        }
        ?>
        
        <div class="time-tracker-form">
            <h2>Dodaj nowy wpis godzinowy</h2>
            
            <?php if (empty($clients)): ?>
                <div class="error">
                    <p>Brak aktywnych klientów w systemie. Skontaktuj się z administratorem.</p>
                </div>
            <?php else: ?>
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
                        <label for="project_id">Projekt: *</label>
                        <select name="project_id" id="project_id" disabled>
                            <option value="">Najpierw wybierz klienta i datę</option>
                        </select>
                        <small>Możesz wybrać istniejący projekt lub podać nowy poniżej.</small>
                    </div>

                    <div class="form-group">
                        <label for="new_project_name">Nowy projekt (opcjonalnie)</label>
                        <input type="text" name="new_project_name" id="new_project_name" maxlength="255" placeholder="np. Wdrożenie CRM - marzec">
                        <small>Jeśli wpiszesz nazwę, projekt zostanie utworzony dla wybranego klienta i miesiąca daty wpisu.</small>
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
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            function getSelectedMonth() {
                var dateVal = $('#entry_date').val();
                if (!dateVal) {
                    var d = new Date();
                    return {month: d.getMonth() + 1, year: d.getFullYear()};
                }
                var parts = dateVal.split('-');
                return {year: parseInt(parts[0], 10), month: parseInt(parts[1], 10)};
            }

            function loadProjects() {
                var clientId = $('#client_id').val();
                var selected = getSelectedMonth();

                if (!clientId) {
                    $('#project_id').prop('disabled', true).html('<option value="">Najpierw wybierz klienta i datę</option>');
                    return;
                }

                $('#project_id').prop('disabled', false).html('<option value="">Ładowanie projektów...</option>');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'get_client_projects',
                        client_id: clientId,
                        month: selected.month,
                        year: selected.year,
                        nonce: '<?php echo wp_create_nonce("get_services_nonce"); ?>'
                    },
                    success: function(response) {
                        var options = '<option value="">Wybierz projekt...</option>';
                        if (response.success && response.data.length) {
                            $.each(response.data, function(index, project) {
                                options += '<option value="' + project.id + '">' + project.project_name + '</option>';
                            });
                        } else {
                            options += '<option value="" disabled>Brak projektów dla tego miesiąca</option>';
                        }
                        $('#project_id').html(options);
                    },
                    error: function() {
                        $('#project_id').html('<option value="">Błąd ładowania projektów</option>');
                    }
                });
            }

            // Dynamiczne ładowanie usług dla klienta
            $('#client_id').change(function() {
                var clientId = $(this).val();
                if (clientId) {
                    $('#service_id').prop('disabled', false);
                    loadProjects();

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
                    $('#project_id').prop('disabled', true).html('<option value="">Najpierw wybierz klienta i datę</option>');
                }
            });

            $('#entry_date').change(function() {
                if ($('#client_id').val()) {
                    loadProjects();
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
        </script>        <?php
        return ob_get_clean();
    }
    
    private function save_time_entry($data) {
        // Walidacja danych wejściowych
        if (empty($data) || !is_array($data)) {
            echo '<div class="error"><p>Nieprawidłowe dane formularza.</p></div>';
            return false;
        }
        
        $required_fields = ['client_id', 'service_id', 'entry_date', 'time_entry_nonce'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                echo '<div class="error"><p>Wypełnij wszystkie wymagane pola.</p></div>';
                return false;
            }
        }
        
        if (!wp_verify_nonce($data['time_entry_nonce'], 'add_time_entry')) {
            echo '<div class="error"><p>Błąd bezpieczeństwa.</p></div>';
            return false;
        }
        
        global $wpdb;
        
        $current_user = wp_get_current_user();
        
        // Walidacja
        if (empty($data['client_id']) || empty($data['service_id']) || empty($data['entry_date'])) {
            echo '<div class="error"><p>Wypełnij wszystkie wymagane pola.</p></div>';
            return false;
        }
        
        // Sprawdź czy data nie jest z przyszłości
        if (strtotime($data['entry_date']) > current_time('timestamp')) {
            echo '<div class="error"><p>Data nie może być z przyszłości.</p></div>';
            return false;
        }
        
        $entry_data = array(
            'employee_id' => $current_user->ID,
            'client_id' => intval($data['client_id']),
            'service_id' => intval($data['service_id']),
            'entry_date' => sanitize_text_field($data['entry_date']),
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : 'draft'
        );

        if (!$this->ensure_project_schema()) {
            echo '<div class="error"><p>Brak struktury projektów w bazie danych (tabela/kolumna). Skontaktuj się z administratorem.</p></div>';
            return false;
        }

        // Projekt: istniejący lub nowy (tworzony per klient/miesiąc/rok)
        $project_id = isset($data['project_id']) ? intval($data['project_id']) : 0;
        $new_project_name = isset($data['new_project_name']) ? sanitize_text_field($data['new_project_name']) : '';

        $entry_month = intval(date('n', strtotime($entry_data['entry_date'])));
        $entry_year = intval(date('Y', strtotime($entry_data['entry_date'])));

        if (!empty($new_project_name)) {
            $existing_project_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}time_tracker_projects WHERE client_id = %d AND project_name = %s AND month = %d AND year = %d",
                $entry_data['client_id'],
                $new_project_name,
                $entry_month,
                $entry_year
            ));

            if ($existing_project_id) {
                $project_id = intval($existing_project_id);
            } else {
                $project_inserted = $wpdb->insert(
                    "{$wpdb->prefix}time_tracker_projects",
                    array(
                        'client_id' => $entry_data['client_id'],
                        'project_name' => $new_project_name,
                        'month' => $entry_month,
                        'year' => $entry_year,
                        'created_by' => $current_user->ID
                    )
                );

                if (!$project_inserted) {
                    echo '<div class="error"><p>Nie udało się utworzyć projektu.</p></div>';
                    return false;
                }

                $project_id = intval($wpdb->insert_id);
            }
        }

        if ($project_id <= 0) {
            echo '<div class="error"><p>Wybierz projekt lub podaj nazwę nowego projektu.</p></div>';
            return false;
        }

        $project_valid = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}time_tracker_projects WHERE id = %d AND client_id = %d AND month = %d AND year = %d",
            $project_id,
            $entry_data['client_id'],
            $entry_month,
            $entry_year
        ));

        if (!$project_valid) {
            echo '<div class="error"><p>Wybrany projekt nie pasuje do klienta lub miesiąca wpisu.</p></div>';
            return false;
        }

        $entry_data['project_id'] = $project_id;
        
        // Sprawdź czy usługa ma stawkę godzinową czy ryczałt
        $service = $wpdb->get_row($wpdb->prepare(
            "SELECT is_fixed_price FROM {$wpdb->prefix}time_tracker_client_services WHERE id = %d",
            $entry_data['service_id']
        ));
        
        if ($service && $service->is_fixed_price == 1) {
            if (empty($data['fixed_amount'])) {
                echo '<div class="error"><p>Wprowadź kwotę ryczałtową.</p></div>';
                return false;
            }
            $entry_data['fixed_amount'] = floatval($data['fixed_amount']);
            $entry_data['hours'] = null;
        } else {
            if (empty($data['hours'])) {
                echo '<div class="error"><p>Wprowadź liczbę godzin.</p></div>';
                return false;
            }
            $entry_data['hours'] = floatval($data['hours']);
            $entry_data['fixed_amount'] = null;
        }
        
        // Jeśli status to "submitted", ustaw datę zgłoszenia
        if ($entry_data['status'] === 'submitted') {
            $entry_data['submission_date'] = current_time('mysql');
        }
        
        $result = $wpdb->insert("{$wpdb->prefix}time_tracker_entries", $entry_data);
        
        if ($result) {
            // Wyślij powiadomienie jeśli status to submitted
            if ($entry_data['status'] === 'submitted') {
                $this->send_entry_notification($wpdb->insert_id, 'submitted');
            }
            
            echo '<div class="success"><p>Wpis został dodany.</p></div>';
            
            // Wyczyść tylko niektóre pola, zachowaj klienta
            echo '<script>
            jQuery(document).ready(function($) {
                $("#entry_date").val("' . date('Y-m-d') . '");
                $("#hours").val("");
                $("#fixed_amount").val("");
                $("#description").val("");
                $("#service_id").html(\'<option value="">Wybierz usługę...</option>\');
                $("#hours-field").show();
                $("#fixed-amount-field").hide();
            });
            </script>';
            
            return true;
        } else {
            $db_error = !empty($wpdb->last_error) ? $wpdb->last_error : 'Nieznany błąd bazy danych';
            echo '<div class="error"><p>Wystąpił błąd podczas dodawania wpisu: ' . esc_html($db_error) . '.</p></div>';
            return false;
        }
    }
    
    // Shortcode: Podsumowanie pracownika
    public function employee_summary_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>Musisz być zalogowany, aby zobaczyć podsumowanie.</p>';
        }
        
        $current_user = wp_get_current_user();
        $current_month = date('n');
        $current_year = date('Y');
        
        global $wpdb;
        
        $summary = $wpdb->get_results($wpdb->prepare(
            "SELECT c.company_name,
                    p.project_name,
                    SUM(CASE WHEN cs.is_fixed_price = 0 THEN te.hours ELSE 0 END) as total_hours,
                    COUNT(*) as entry_count
             FROM {$wpdb->prefix}time_tracker_entries te
             LEFT JOIN {$wpdb->prefix}time_tracker_clients c ON te.client_id = c.id
             LEFT JOIN {$wpdb->prefix}time_tracker_client_services cs ON te.service_id = cs.id
             LEFT JOIN {$wpdb->prefix}time_tracker_projects p ON te.project_id = p.id
             WHERE te.employee_id = %d
               AND MONTH(te.entry_date) = %d
               AND YEAR(te.entry_date) = %d
               AND te.status = 'approved'
             GROUP BY te.client_id, te.project_id
             ORDER BY c.company_name ASC, p.project_name ASC",
            $current_user->ID, $current_month, $current_year
        ));
        
        ob_start();
        ?>
        
        <div class="employee-summary">
            <h2>Podsumowanie miesiąca: <?php echo date_i18n('F Y'); ?></h2>
            
            <?php 
            $total_hours = 0;
            
            if (empty($summary)): ?>
                <p>Brak zatwierdzonych godzin w tym miesiącu.</p>
            <?php else: ?>
                <table class="time-tracker-table">
                    <thead>
                        <tr>
                            <th>Klient</th>
                            <th>Projekt</th>
                            <th>Łączna liczba godzin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($summary as $item): 
                            $total_hours += floatval($item->total_hours);
                        ?>
                        <tr>
                            <td><?php echo esc_html($item->company_name); ?></td>
                            <td><?php echo !empty($item->project_name) ? esc_html($item->project_name) : '-'; ?></td>
                            <td><?php echo number_format(floatval($item->total_hours), 2); ?> h</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td><strong>Razem:</strong></td>
                            <td><strong><?php echo number_format(floatval($total_hours), 2); ?> h</strong></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
            
            <div class="summary-stats">
                <div class="stat-box">
                    <h3><?php echo number_format(floatval($total_hours), 2); ?></h3>
                    <p>Łączna liczba godzin</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo !empty($summary) ? count($summary) : 0; ?></h3>
                    <p>Liczba klientów</p>
                </div>
            </div>
        </div>
        
        <style>
        .employee-summary {
            max-width: 1000px;
            margin: 0 auto;
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
        return ob_get_clean();
    }
    
    private function send_entry_notification($entry_id, $type = 'submitted') {
        // Sprawdź czy powiadomienia są włączone
        $option_name = 'time_tracker_notify_on_' . $type;
        $notify_enabled = get_option($option_name, 1);
        
        if (!$notify_enabled) {
            return false;
        }
        
        global $wpdb;
        
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT te.*, 
                    c.company_name,
                    cs.service_name,
                    u.display_name as employee_name,
                    u.user_email as employee_email
             FROM {$wpdb->prefix}time_tracker_entries te
             LEFT JOIN {$wpdb->prefix}time_tracker_clients c ON te.client_id = c.id
             LEFT JOIN {$wpdb->prefix}time_tracker_client_services cs ON te.service_id = cs.id
             LEFT JOIN {$wpdb->prefix}time_tracker_projects p ON te.project_id = p.id
             LEFT JOIN {$wpdb->prefix}users u ON te.employee_id = u.ID
             WHERE te.id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return false;
        }
        
        // Pobierz adresy email adminów i managerów
        $admin_emails = array();
        
        // Administratorzy
        $admins = get_users(array(
            'role' => 'administrator',
            'fields' => array('user_email')
        ));
        
        foreach ($admins as $admin) {
            $admin_emails[] = $admin->user_email;
        }
        
        // Managerzy Time Tracker
        $managers = get_users(array(
            'role' => 'time_tracker_manager',
            'fields' => array('user_email')
        ));
        
        foreach ($managers as $manager) {
            $admin_emails[] = $manager->user_email;
        }
        
        // Usuń duplikaty
        $admin_emails = array_unique($admin_emails);
        
        if (empty($admin_emails)) {
            $admin_emails[] = get_option('admin_email');
        }
        
        // Przygotuj temat i treść w zależności od typu
        $site_name = get_bloginfo('name');
        $from_email = get_option('time_tracker_from_email', get_option('admin_email'));
        $from_name = get_option('time_tracker_from_name', $site_name);
        
        switch ($type) {
            case 'submitted':
                $subject = sprintf('[%s] Nowy wpis godzinowy do zatwierdzenia', $site_name);
                
                $message = sprintf("Nowy wpis godzinowy został zgłoszony przez %s.\n\n", $entry->employee_name);
                $message .= "Szczegóły wpisu:\n";
                $message .= sprintf("Klient: %s\n", $entry->company_name);
                $message .= sprintf("Usługa: %s\n", $entry->service_name);
                $message .= sprintf("Data: %s\n", date('d.m.Y', strtotime($entry->entry_date)));
                
                if (!empty($entry->hours)) {
                    $message .= sprintf("Liczba godzin: %s h\n", number_format($entry->hours, 2));
                }
                
                if (!empty($entry->fixed_amount)) {
                    $message .= sprintf("Kwota ryczałtowa: %s zł\n", number_format($entry->fixed_amount, 2));
                }
                
                if (!empty($entry->description)) {
                    $message .= sprintf("Opis: %s\n", $entry->description);
                }
                
                $message .= sprintf("\nData zgłoszenia: %s\n", date('d.m.Y H:i', strtotime($entry->submission_date)));
                $message .= "\nAkcje:\n";
                $message .= sprintf("- Zatwierdź: %s\n", 
                    admin_url('admin.php?page=time-tracker-entries&time_tracker_action=approve_entry&entry_id=' . $entry_id));
                $message .= sprintf("- Odrzuć: %s\n", 
                    admin_url('admin.php?page=time-tracker-entries&time_tracker_action=reject_entry&entry_id=' . $entry_id));
                $message .= sprintf("- Zobacz wszystkie wpisy: %s\n", 
                    admin_url('admin.php?page=time-tracker-entries'));
                break;
                
            case 'approved':
                $subject = sprintf('[%s] Wpis godzinowy został zatwierdzony', $site_name);
                
                $message = sprintf("Twój wpis godzinowy z dnia %s został zatwierdzony.\n\n", 
                    date('d.m.Y', strtotime($entry->entry_date)));
                $message .= "Szczegóły wpisu:\n";
                $message .= sprintf("Klient: %s\n", $entry->company_name);
                $message .= sprintf("Usługa: %s\n", $entry->service_name);
                
                if (!empty($entry->hours)) {
                    $message .= sprintf("Liczba godzin: %s h\n", number_format($entry->hours, 2));
                }
                
                if (!empty($entry->fixed_amount)) {
                    $message .= sprintf("Kwota ryczałtowa: %s zł\n", number_format($entry->fixed_amount, 2));
                }
                
                $message .= sprintf("\nData zatwierdzenia: %s\n", date('d.m.Y H:i', strtotime($entry->approved_date)));
                break;
                
            case 'rejected':
                $subject = sprintf('[%s] Wpis godzinowy został odrzucony', $site_name);
                
                $message = sprintf("Twój wpis godzinowy z dnia %s został odrzucony.\n\n", 
                    date('d.m.Y', strtotime($entry->entry_date)));
                $message .= "Szczegóły wpisu:\n";
                $message .= sprintf("Klient: %s\n", $entry->company_name);
                $message .= sprintf("Usługa: %s\n", $entry->service_name);
                
                if (!empty($entry->hours)) {
                    $message .= sprintf("Liczba godzin: %s h\n", number_format($entry->hours, 2));
                }
                
                if (!empty($entry->fixed_amount)) {
                    $message .= sprintf("Kwota ryczałtowa: %s zł\n", number_format($entry->fixed_amount, 2));
                }
                
                if (!empty($entry->rejection_reason)) {
                    $message .= sprintf("\nPowód odrzucenia: %s\n", $entry->rejection_reason);
                }
                
                $message .= sprintf("\nData odrzucenia: %s\n", date('d.m.Y H:i', strtotime($entry->approved_date)));
                $message .= "\nMożesz edytować wpis i zgłosić go ponownie:\n";
                $message .= sprintf("- Edytuj wpis: %s\n", 
                    admin_url('admin.php?page=time-tracker-edit-entry&id=' . $entry_id));
                break;
                
            default:
                return false;
        }
        
        // Nagłówki emaila
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            sprintf('From: %s <%s>', $from_name, $from_email)
        );
        
        // Wyślij email do adminów dla zgłoszeń
        if ($type === 'submitted') {
            foreach ($admin_emails as $email) {
                wp_mail($email, $subject, $message, $headers);
            }
        }
        
        // Wyślij email do pracownika dla zatwierdzenia/odrzucenia
        if (($type === 'approved' || $type === 'rejected') && !empty($entry->employee_email)) {
            wp_mail($entry->employee_email, $subject, $message, $headers);
        }
        
        return true;
    }
    
    private function setup_cron_jobs() {
        if (!wp_next_scheduled('time_tracker_weekly_reminder')) {
            wp_schedule_event(strtotime('next monday 09:00'), 'weekly', 'time_tracker_weekly_reminder');
        }
        
        add_action('time_tracker_weekly_reminder', array($this, 'send_weekly_reminders'));
    }
    
    public function send_weekly_reminders() {
        // Sprawdź czy cotygodniowe przypomnienia są włączone
        $send_weekly_reminders = get_option('time_tracker_send_weekly_reminders', 1);
        if (!$send_weekly_reminders) {
            return;
        }
        
        global $wpdb;
        
        // Znajdź pracowników, którzy nie mają wpisów w tym tygodniu
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_end = date('Y-m-d', strtotime('sunday this week'));
        
        $all_employees = $wpdb->get_results(
            "SELECT u.ID, u.user_email, u.display_name
             FROM {$wpdb->prefix}users u
             INNER JOIN {$wpdb->prefix}usermeta um ON u.ID = um.user_id
             WHERE um.meta_key = 'wp_capabilities'
             AND (um.meta_value LIKE '%\"add_time_entries\"%' OR um.meta_value LIKE '%\"manage_time_tracker\"%')
             GROUP BY u.ID"
        );
        
        $from_email = get_option('time_tracker_from_email', get_option('admin_email'));
        $from_name = get_option('time_tracker_from_name', get_bloginfo('name'));
        
        foreach ($all_employees as $employee) {
            $has_entries = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->prefix}time_tracker_entries 
                 WHERE employee_id = %d 
                 AND entry_date BETWEEN %s AND %s",
                $employee->ID, $week_start, $week_end
            ));
            
            if ($has_entries == 0) {
                $subject = sprintf('[%s] Przypomnienie o wpisach godzinowych', get_bloginfo('name'));
                
                $message = sprintf("Cześć %s,\n\n", $employee->display_name);
                $message .= "To jest automatyczne przypomnienie o dodaniu wpisów godzinowych za bieżący tydzień.\n\n";
                $message .= sprintf("Nie odnotowaliśmy żadnych wpisów od Ciebie w tym tygodniu (od %s do %s).\n\n",
                    date('d.m.Y', strtotime($week_start)), date('d.m.Y', strtotime($week_end)));
                $message .= "Jeśli pracowałeś/aś nad projektami, pamiętaj o dodaniu godzin w systemie Time Tracker.\n\n";
                $message .= "Link do formularza dodawania wpisów:\n";
                $message .= get_site_url() . "\n\n";
                $message .= "Dzięki,\n";
                $message .= "Zespół " . get_bloginfo('name');
                
                $headers = array(
                    'Content-Type: text/plain; charset=UTF-8',
                    sprintf('From: %s <%s>', $from_name, $from_email)
                );
                
                wp_mail($employee->user_email, $subject, $message, $headers);
            }
        }
    }
}

// Inicjalizacja wtyczki
function time_tracker_pro() {
    return TimeTrackerPro::get_instance();
}

// Zakończenie buforowania
if (ob_get_level() > 0) {
    ob_end_flush();
}

// Uruchomienie
time_tracker_pro();
