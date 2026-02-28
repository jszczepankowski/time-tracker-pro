jQuery(document).ready(function($) {
    console.log('Time Tracker Admin Script loaded');
    
    // Inicjalizacja DataTables tylko dla tabel z klasą .datatable
    if ($.fn.DataTable) {
        $('.wp-list-table.datatable').DataTable({
            "pageLength": 25,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.4/i18n/pl.json"
            }
        });
        console.log('DataTables initialized');
    } else {
        console.warn('DataTables library not found');
    }
    
    // Toggle dla stawki godzinowej
    $('#is_fixed_price').on('change', function() {
        var isFixed = $(this).is(':checked');
        var rateRow = $('#hourly_rate_row');
        
        if (isFixed) {
            rateRow.hide();
            $('#hourly_rate').prop('required', false);
        } else {
            rateRow.show();
            $('#hourly_rate').prop('required', true);
        }
    });
    
    // Potwierdzenie usunięcia
    $(document).on('click', '.button-danger', function(e) {
        if (!confirm('Czy na pewno chcesz wykonać tę akcję?')) {
            e.preventDefault();
            return false;
        }
    });
    
    // Eksport do CSV dla ogólnych raportów
    $(document).on('click', '.export-csv', function(e) {
        e.preventDefault();
        
        var table = $(this).closest('.report-results').find('table').first();
        if (table.length === 0) return;
        
        var csv = [];
        var rows = table.find('tr');
        
        rows.each(function() {
            var row = [];
            $(this).find('th, td').each(function() {
                var text = $(this).text().trim();
                text = text.replace(/"/g, '""');
                row.push('"' + text + '"');
            });
            csv.push(row.join(','));
        });
        
        var csvContent = csv.join('\n');
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        
        if (link.download !== undefined) {
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'raport.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    });
    
    // Dynamiczne ładowanie usług dla klienta w formularzu edycji
    $(document).on('change', '#client_id', function() {
        var clientId = $(this).val();
        if (clientId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_client_services',
                    client_id: clientId,
                    nonce: timeTrackerAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var options = '<option value="">Wybierz usługę...</option>';
                        $.each(response.data, function(index, service) {
                            options += '<option value="' + service.id + '" data-is-fixed="' + service.is_fixed_price + '">' + 
                                       service.service_name;
                            if (service.is_fixed_price == 0) {
                                options += ' (' + parseFloat(service.hourly_rate).toFixed(2) + ' zł/h)';
                            }
                            options += '</option>';
                        });
                        $('#service_id').html(options);
                    }
                },
                error: function() {
                    alert('Błąd podczas ładowania usług.');
                }
            });
        } else {
            $('#service_id').html('<option value="">Wybierz klienta...</option>');
        }
    });
    
    // Przełączanie między godzinami a kwotą ryczałtową
    $(document).on('change', '#service_id', function() {
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
    
    // Przełączanie dla typu usługi w formularzu usług klienta
    $(document).on('change', '#service_type', function() {
        if ($(this).val() == 'hourly') {
            $('#hourly_rate_row').show();
            $('#hourly_rate').prop('required', true);
        } else {
            $('#hourly_rate_row').hide();
            $('#hourly_rate').prop('required', false);
        }
    });
    
    // Auto-ukrywanie pola powodu odrzucenia
    $(document).on('change', '#status', function() {
        if ($(this).val() == 'rejected') {
            $('#rejection_reason_row').show();
        } else {
            $('#rejection_reason_row').hide();
        }
    });
    
    // Rozwijanie/zwijanie szczegółów w raportach - POPRAWIONE
    $(document).on('click', '.details-section h4', function() {
        console.log('Clicked details header');
        var detailsSection = $(this).next('.details-collapse');
        detailsSection.slideToggle();
    });
    
    // Funkcja dla przycisku drukowania raportu
    $(document).on('click', '.button-print', function() {
        var report = $(this).closest('.client-report');
        var originalContents = document.body.innerHTML;
        var printContents = report.html();
        
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        window.location.reload();
    });
    
    // Funkcja dla eksportu CSV w raportach szczegółowych
    $(document).on('click', '.button-export-csv', function() {
        var button = $(this);
        var clientName = button.data('client-name') || 'raport';
        var month = button.data('month') || new Date().getMonth() + 1;
        var year = button.data('year') || new Date().getFullYear();
        
        var report = button.closest('.client-report');
        var csv = [];
        
        // Nagłówek klienta
        csv.push('"Klient: ' + clientName + '"');
        csv.push('');
        
        // Podsumowanie usług
        csv.push('"Podsumowanie usług"');
        var summaryTable = report.find('.summary-section table')[0];
        if (summaryTable) {
            var summaryRows = summaryTable.querySelectorAll('thead tr, tbody tr, tfoot tr');
            summaryRows.forEach(function(row) {
                var rowData = [];
                row.querySelectorAll('th, td').forEach(function(cell) {
                    rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
                });
                csv.push(rowData.join(','));
            });
        }
        
        csv.push('');
        
        // Szczegółowe wpisy
        csv.push('"Szczegółowe wpisy"');
        var detailsTable = report.find('.details-section table')[0];
        if (detailsTable) {
            var detailsRows = detailsTable.querySelectorAll('thead tr, tbody tr');
            detailsRows.forEach(function(row) {
                var rowData = [];
                row.querySelectorAll('th, td').forEach(function(cell) {
                    rowData.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
                });
                csv.push(rowData.join(','));
            });
        }
        
        // Tworzenie i pobieranie pliku
        var csvContent = csv.join('\n');
        var encodedUri = encodeURI('data:text/csv;charset=utf-8,' + csvContent);
        var link = document.createElement('a');
        link.setAttribute('href', encodedUri);
        link.setAttribute('download', clientName + '_' + month + '_' + year + '_szczegoly.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Inicjalizacja - ukryj szczegóły raportów na starcie
    $('.details-collapse').hide();
    console.log('Details sections hidden');
    
    // Inicjalizacja - ukryj pole powodu odrzucenia jeśli nie jest potrzebne
    if ($('#status').val() != 'rejected') {
        $('#rejection_reason_row').hide();
    }
});