<?php
class TimeTracker_Admin_Menu {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function add_admin_menus() {
        // Główne menu
        add_menu_page(
            'Time Tracker',
            'Time Tracker',
            'manage_time_tracker',
            'time-tracker',
            array($this, 'dashboard_page'),
            'dashicons-clock',
            30
        );
        
        // Podmenu
        add_submenu_page(
            'time-tracker',
            'Dashboard',
            'Dashboard',
            'manage_time_tracker',
            'time-tracker',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'time-tracker',
            'Klienci',
            'Klienci',
            'manage_clients',
            'time-tracker-clients',
            array($this, 'clients_page')
        );
        
        add_submenu_page(
            'time-tracker',
            'Wpisy godzinowe',
            'Wpisy godzinowe',
            'view_all_time_entries',
            'time-tracker-entries',
            array($this, 'entries_page')
        );
        
        add_submenu_page(
            'time-tracker',
            'Zestawienia',
            'Zestawienia',
            'generate_reports',
            'time-tracker-reports',
            array($this, 'reports_page')
        );
        
        add_submenu_page(
            'time-tracker',
            'Ustawienia',
            'Ustawienia',
            'manage_options',
            'time-tracker-settings',
            array($this, 'settings_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'time-tracker') !== false) {
            // Najpierw jQuery (jako zależność)
            wp_enqueue_script('jquery');
            
            // DataTables
            wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css', array(), '1.13.4');
            wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js', array('jquery'), '1.13.4', true);
            
            // DataTables Polish language
            wp_enqueue_script('datatables-polish', 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/pl.json', array('datatables'), null, true);
            
            // Nasze style i skrypty
            wp_enqueue_style('time-tracker-admin', TIME_TRACKER_PLUGIN_URL . 'admin/assets/css/admin-style.css', array(), TIME_TRACKER_VERSION);
            wp_enqueue_script('time-tracker-admin', TIME_TRACKER_PLUGIN_URL . 'admin/assets/js/admin-script.js', array('jquery', 'datatables'), TIME_TRACKER_VERSION, true);
            
            // Lokalizacja danych dla AJAX
            wp_localize_script('time-tracker-admin', 'timeTrackerAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('get_services_nonce')
            ));
        }
    }
    
    public function dashboard_page() {
        if (!current_user_can('manage_time_tracker')) {
            wp_die(__('Brak uprawnień.', 'time-tracker'));
        }
        
        include TIME_TRACKER_PLUGIN_DIR . 'admin/pages/dashboard.php';
    }
    
    public function clients_page() {
        if (!current_user_can('manage_clients')) {
            wp_die(__('Brak uprawnień.', 'time-tracker'));
        }
        
        include TIME_TRACKER_PLUGIN_DIR . 'admin/pages/clients.php';
    }
    
    public function entries_page() {
        if (!current_user_can('view_all_time_entries')) {
            wp_die(__('Brak uprawnień.', 'time-tracker'));
        }
        
        include TIME_TRACKER_PLUGIN_DIR . 'admin/pages/entries.php';
    }
    
    public function reports_page() {
        if (!current_user_can('generate_reports')) {
            wp_die(__('Brak uprawnień.', 'time-tracker'));
        }
        
        include TIME_TRACKER_PLUGIN_DIR . 'admin/pages/reports.php';
    }
    
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Brak uprawnień.', 'time-tracker'));
        }
        
        include TIME_TRACKER_PLUGIN_DIR . 'admin/pages/settings.php';
    }
}