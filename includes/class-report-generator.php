<?php
class TimeTracker_Report_Generator {
    
    public function generate_monthly_report($client_id, $month, $year) {
        $time_tracker = time_tracker_pro();
        $database = $time_tracker->get_database();
        
        $entries = $database->get_monthly_report($client_id, $month, $year);
        
        if (empty($entries)) {
            return array('success' => false, 'message' => 'Brak danych dla wybranego okresu');
        }
        
        $report_data = array(
            'client' => $database->get_client($client_id),
            'month' => $month,
            'year' => $year,
            'entries' => $entries,
            'summary' => $this->calculate_summary($entries)
        );
        
        return array('success' => true, 'data' => $report_data);
    }
    
    private function calculate_summary($entries) {
        $summary = array(
            'total_hours' => 0,
            'total_amount' => 0,
            'fixed_amount' => 0,
            'hourly_amount' => 0,
            'by_employee' => array(),
            'by_service' => array()
        );
        
        foreach ($entries as $entry) {
            if ($entry->is_fixed_price) {
                $amount = $entry->fixed_amount;
                $summary['fixed_amount'] += $amount;
            } else {
                $hours = $entry->hours;
                $rate = $entry->hourly_rate;
                $amount = $hours * $rate;
                
                $summary['total_hours'] += $hours;
                $summary['hourly_amount'] += $amount;
            }
            
            $summary['total_amount'] += $amount;
            
            // Grupowanie po pracowniku
            if (!isset($summary['by_employee'][$entry->employee_name])) {
                $summary['by_employee'][$entry->employee_name] = array(
                    'hours' => 0,
                    'amount' => 0
                );
            }
            
            if (!$entry->is_fixed_price) {
                $summary['by_employee'][$entry->employee_name]['hours'] += $hours;
            }
            $summary['by_employee'][$entry->employee_name]['amount'] += $amount;
            
            // Grupowanie po usłudze
            if (!isset($summary['by_service'][$entry->service_name])) {
                $summary['by_service'][$entry->service_name] = array(
                    'hours' => 0,
                    'amount' => 0
                );
            }
            
            if (!$entry->is_fixed_price) {
                $summary['by_service'][$entry->service_name]['hours'] += $hours;
            }
            $summary['by_service'][$entry->service_name]['amount'] += $amount;
        }
        
        return $summary;
    }
    
    public function save_statement($client_id, $month, $year, $invoice_number = '', $invoice_status = 'draft') {
        $time_tracker = time_tracker_pro();
        $database = $time_tracker->get_database();
        
        $data = array(
            'client_id' => $client_id,
            'month' => $month,
            'year' => $year,
            'invoice_number' => sanitize_text_field($invoice_number),
            'invoice_status' => sanitize_text_field($invoice_status),
            'generated_at' => current_time('mysql')
        );
        
        return $database->save_statement($data);
    }
    
    public function get_statement($client_id, $month, $year) {
        $time_tracker = time_tracker_pro();
        $database = $time_tracker->get_database();
        
        return $database->get_statement($client_id, $month, $year);
    }
}