<?php
class TimeTracker_Time_Entry {
    
    public function add_entry($data) {
        $time_tracker = time_tracker_pro();
        $database = $time_tracker->get_database();
        
        $current_user = wp_get_current_user();
        
        // Walidacja danych
        $errors = $this->validate_entry($data);
        if (!empty($errors)) {
            return array('success' => false, 'errors' => $errors);
        }
        
        $entry_data = array(
            'employee_id' => $current_user->ID,
            'client_id' => intval($data['client_id']),
            'service_id' => intval($data['service_id']),
            'entry_date' => sanitize_text_field($data['entry_date']),
            'description' => sanitize_textarea_field($data['description']),
            'status' => sanitize_text_field($data['status'])
        );
        
        // Sprawdź czy usługa ma stawkę godzinową czy ryczałt
        $service = $database->get_service($entry_data['service_id']);
        
        if ($service->is_fixed_price == 1) {
            $entry_data['fixed_amount'] = floatval($data['fixed_amount']);
            $entry_data['hours'] = null;
        } else {
            $entry_data['hours'] = floatval($data['hours']);
            $entry_data['fixed_amount'] = null;
        }
        
        // Jeśli status to "submitted", ustaw datę zgłoszenia
        if ($entry_data['status'] === 'submitted') {
            $entry_data['submission_date'] = current_time('mysql');
        }
        
        $result = $database->add_time_entry($entry_data);
        
        return array('success' => (bool)$result, 'entry_id' => $result);
    }
    
    private function validate_entry($data) {
        $errors = array();
        
        if (empty($data['client_id'])) {
            $errors[] = __('Wybierz klienta', 'time-tracker');
        }
        
        if (empty($data['service_id'])) {
            $errors[] = __('Wybierz usługę', 'time-tracker');
        }
        
        if (empty($data['entry_date'])) {
            $errors[] = __('Podaj datę', 'time-tracker');
        } elseif (strtotime($data['entry_date']) > current_time('timestamp')) {
            $errors[] = __('Data nie może być z przyszłości', 'time-tracker');
        }
        
        // Sprawdź czy użytkownik nie dodał już wpisu dla tego klienta w tym dniu (tylko dla tego samego pracownika)
        $time_tracker = time_tracker_pro();
        $database = $time_tracker->get_database();
        $current_user = wp_get_current_user();
        
        $existing = $database->get_time_entries(array(
            'employee_id' => $current_user->ID,
            'client_id' => intval($data['client_id']),
            'date_from' => $data['entry_date'],
            'date_to' => $data['entry_date']
        ));
        
        if (count($existing) > 0) {
            // Możemy pozwolić na wiele wpisów w tym samym dniu, więc to wykomentujemy
            // $errors[] = __('Masz już wpis dla tego klienta w tym dniu', 'time-tracker');
        }
        
        return $errors;
    }
    
    public function get_employee_entries($employee_id, $status = null, $date_from = null, $date_to = null) {
        $time_tracker = time_tracker_pro();
        return $time_tracker->get_database()->get_time_entries(array(
            'employee_id' => $employee_id,
            'status' => $status,
            'date_from' => $date_from,
            'date_to' => $date_to
        ));
    }
    
    public function approve_entry($entry_id, $manager_id) {
        $time_tracker = time_tracker_pro();
        $database = $time_tracker->get_database();
        
        $data = array(
            'status' => 'approved',
            'approved_by' => $manager_id,
            'approved_date' => current_time('mysql')
        );
        
        return $database->update_time_entry($data, $entry_id);
    }
    
    public function reject_entry($entry_id, $manager_id, $reason) {
        $time_tracker = time_tracker_pro();
        $database = $time_tracker->get_database();
        
        $data = array(
            'status' => 'rejected',
            'approved_by' => $manager_id,
            'rejection_reason' => sanitize_textarea_field($reason),
            'approved_date' => current_time('mysql')
        );
        
        return $database->update_time_entry($data, $entry_id);
    }
    
    public function submit_entries($entry_ids, $employee_id) {
        $time_tracker = time_tracker_pro();
        $database = $time_tracker->get_database();
        
        $success_count = 0;
        
        foreach ($entry_ids as $entry_id) {
            // Sprawdź czy wpis należy do pracownika
            $entry = $database->get_time_entry($entry_id);
            
            if ($entry && $entry->employee_id == $employee_id && $entry->status == 'draft') {
                $data = array(
                    'status' => 'submitted',
                    'submission_date' => current_time('mysql')
                );
                
                if ($database->update_time_entry($data, $entry_id)) {
                    $success_count++;
                }
            }
        }
        
        return $success_count;
    }
}