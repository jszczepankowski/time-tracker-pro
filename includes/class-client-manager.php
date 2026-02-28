<?php
class TimeTracker_Client_Manager {
    
    public function get_clients($active_only = true) {
        $time_tracker = time_tracker_pro();
        return $time_tracker->get_database()->get_clients($active_only);
    }
    
    public function get_client($client_id) {
        $time_tracker = time_tracker_pro();
        return $time_tracker->get_database()->get_client($client_id);
    }
    
    public function save_client($data, $client_id = 0) {
        $time_tracker = time_tracker_pro();
        
        $client_data = array(
            'company_name' => sanitize_text_field($data['company_name']),
            'nip' => sanitize_text_field($data['nip']),
            'address' => sanitize_textarea_field($data['address']),
            'email' => sanitize_email($data['email']),
            'phone' => sanitize_text_field($data['phone']),
            'active' => isset($data['active']) ? 1 : 0
        );
        
        return $time_tracker->get_database()->save_client($client_data, $client_id);
    }
    
    public function delete_client($client_id) {
        $time_tracker = time_tracker_pro();
        return $time_tracker->get_database()->delete_client($client_id);
    }
    
    public function get_client_services($client_id) {
        $time_tracker = time_tracker_pro();
        return $time_tracker->get_database()->get_client_services($client_id);
    }
    
    public function get_service($service_id) {
        $time_tracker = time_tracker_pro();
        return $time_tracker->get_database()->get_service($service_id);
    }
    
    public function save_service($data, $service_id = 0) {
        $time_tracker = time_tracker_pro();
        
        $service_data = array(
            'client_id' => intval($data['client_id']),
            'service_name' => sanitize_text_field($data['service_name']),
            'hourly_rate' => isset($data['hourly_rate']) ? floatval($data['hourly_rate']) : null,
            'is_fixed_price' => isset($data['is_fixed_price']) ? 1 : 0
        );
        
        return $time_tracker->get_database()->save_service($service_data, $service_id);
    }
    
    public function delete_service($service_id) {
        $time_tracker = time_tracker_pro();
        return $time_tracker->get_database()->delete_service($service_id);
    }
}