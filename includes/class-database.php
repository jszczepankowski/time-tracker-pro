<?php
class TimeTracker_Database {
    
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = array();
        
        // Tabela klientów
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tt_clients (
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
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tt_client_services (
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
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tt_time_entries (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            client_id int(11) NOT NULL,
            service_id int(11) NOT NULL,
            entry_date date NOT NULL,
            hours decimal(4,2),
            fixed_amount decimal(10,2),
            description text,
            status enum('draft','submitted','approved','rejected') DEFAULT 'draft',
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
            KEY entry_date (entry_date),
            KEY status (status)
        ) $charset_collate;";
        
        // Tabela zestawień/faktur
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tt_client_statements (
            id int(11) NOT NULL AUTO_INCREMENT,
            client_id int(11) NOT NULL,
            month tinyint(2) NOT NULL,
            year year(4) NOT NULL,
            invoice_number varchar(50),
            invoice_status enum('draft','sent','paid','overdue') DEFAULT 'draft',
            pdf_file_path varchar(255),
            generated_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_monthly (client_id, month, year)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Dodaj przykładowe dane dla testów
        $this->add_sample_data();
    }
    
    private function add_sample_data() {
        global $wpdb;
        
        // Sprawdź czy już istnieją klientów
        $clients_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tt_clients");
        
        if ($clients_count == 0) {
            // Dodaj przykładowego klienta
            $wpdb->insert(
                "{$wpdb->prefix}tt_clients",
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
                    "{$wpdb->prefix}tt_client_services",
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
    
    public function get_clients($active_only = true) {
        global $wpdb;
        
        $where = $active_only ? "WHERE active = 1" : "";
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}tt_clients $where ORDER BY company_name"
        );
    }
    
    public function get_client($client_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tt_clients WHERE id = %d",
            $client_id
        ));
    }
    
    public function save_client($data, $client_id = 0) {
        global $wpdb;
        
        $table = "{$wpdb->prefix}tt_clients";
        
        if ($client_id > 0) {
            return $wpdb->update($table, $data, array('id' => $client_id));
        } else {
            return $wpdb->insert($table, $data);
        }
    }
    
    public function delete_client($client_id) {
        global $wpdb;
        
        return $wpdb->delete("{$wpdb->prefix}tt_clients", array('id' => $client_id));
    }
    
    public function get_client_services($client_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tt_client_services 
             WHERE client_id = %d 
             ORDER BY service_name",
            $client_id
        ));
    }
    
    public function get_service($service_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tt_client_services WHERE id = %d",
            $service_id
        ));
    }
    
    public function save_service($data, $service_id = 0) {
        global $wpdb;
        
        $table = "{$wpdb->prefix}tt_client_services";
        
        if ($service_id > 0) {
            return $wpdb->update($table, $data, array('id' => $service_id));
        } else {
            return $wpdb->insert($table, $data);
        }
    }
    
    public function delete_service($service_id) {
        global $wpdb;
        
        return $wpdb->delete("{$wpdb->prefix}tt_client_services", array('id' => $service_id));
    }
    
    public function add_time_entry($data) {
        global $wpdb;
        
        $defaults = array(
            'status' => 'draft',
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        return $wpdb->insert("{$wpdb->prefix}tt_time_entries", $data);
    }
    
    public function update_time_entry($data, $entry_id) {
        global $wpdb;
        
        return $wpdb->update("{$wpdb->prefix}tt_time_entries", $data, array('id' => $entry_id));
    }
    
    public function delete_time_entry($entry_id) {
        global $wpdb;
        
        return $wpdb->delete("{$wpdb->prefix}tt_time_entries", array('id' => $entry_id));
    }
    
    public function get_time_entry($entry_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tt_time_entries WHERE id = %d",
            $entry_id
        ));
    }
    
    public function get_time_entries($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'employee_id' => null,
            'client_id' => null,
            'status' => null,
            'date_from' => null,
            'date_to' => null,
            'limit' => 100,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $params = array();
        
        if ($args['employee_id']) {
            $where[] = "te.employee_id = %d";
            $params[] = $args['employee_id'];
        }
        
        if ($args['client_id']) {
            $where[] = "te.client_id = %d";
            $params[] = $args['client_id'];
        }
        
        if ($args['status']) {
            $where[] = "te.status = %s";
            $params[] = $args['status'];
        }
        
        if ($args['date_from']) {
            $where[] = "te.entry_date >= %s";
            $params[] = $args['date_from'];
        }
        
        if ($args['date_to']) {
            $where[] = "te.entry_date <= %s";
            $params[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where);
        
        $query = "SELECT te.*, 
                         c.company_name as client_name,
                         cs.service_name,
                         cs.hourly_rate,
                         cs.is_fixed_price,
                         u.display_name as employee_name
                  FROM {$wpdb->prefix}tt_time_entries te
                  LEFT JOIN {$wpdb->prefix}tt_clients c ON te.client_id = c.id
                  LEFT JOIN {$wpdb->prefix}tt_client_services cs ON te.service_id = cs.id
                  LEFT JOIN {$wpdb->prefix}users u ON te.employee_id = u.ID
                  WHERE $where_clause
                  ORDER BY te.entry_date DESC, te.created_at DESC
                  LIMIT %d OFFSET %d";
        
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }
        
        return $wpdb->get_results($query);
    }
    
    public function get_employee_summary($employee_id, $month, $year) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT c.company_name,
                    SUM(CASE WHEN cs.is_fixed_price = 0 THEN te.hours ELSE 0 END) as total_hours,
                    COUNT(DISTINCT te.client_id) as client_count
             FROM {$wpdb->prefix}tt_time_entries te
             LEFT JOIN {$wpdb->prefix}tt_clients c ON te.client_id = c.id
             LEFT JOIN {$wpdb->prefix}tt_client_services cs ON te.service_id = cs.id
             WHERE te.employee_id = %d
               AND MONTH(te.entry_date) = %d
               AND YEAR(te.entry_date) = %d
               AND te.status = 'approved'
             GROUP BY te.client_id
             ORDER BY total_hours DESC",
            $employee_id, $month, $year
        );
        
        return $wpdb->get_results($query);
    }
    
    public function get_monthly_report($client_id, $month, $year) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT te.*, 
                    cs.service_name,
                    cs.hourly_rate,
                    cs.is_fixed_price,
                    u.display_name as employee_name
             FROM {$wpdb->prefix}tt_time_entries te
             LEFT JOIN {$wpdb->prefix}tt_client_services cs ON te.service_id = cs.id
             LEFT JOIN {$wpdb->prefix}users u ON te.employee_id = u.ID
             WHERE te.client_id = %d
               AND MONTH(te.entry_date) = %d
               AND YEAR(te.entry_date) = %d
               AND te.status = 'approved'
             ORDER BY te.entry_date, u.display_name",
            $client_id, $month, $year
        );
        
        return $wpdb->get_results($query);
    }
    
    public function save_statement($data) {
        global $wpdb;
        
        $table = "{$wpdb->prefix}tt_client_statements";
        
        // Sprawdź czy już istnieje zestawienie dla tego miesiąca
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table 
             WHERE client_id = %d AND month = %d AND year = %d",
            $data['client_id'], $data['month'], $data['year']
        ));
        
        if ($existing) {
            return $wpdb->update($table, $data, array('id' => $existing));
        } else {
            return $wpdb->insert($table, $data);
        }
    }
    
    public function get_statement($client_id, $month, $year) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tt_client_statements 
             WHERE client_id = %d AND month = %d AND year = %d",
            $client_id, $month, $year
        ));
    }
}