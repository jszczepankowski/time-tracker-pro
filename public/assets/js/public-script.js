jQuery(document).ready(function($) {
    // Dynamiczne ładowanie usług dla klienta
    $('#client_id').on('change', function() {
        var clientId = $(this).val();
        var serviceSelect = $('#service_id');
        
        if (clientId) {
            serviceSelect.prop('disabled', false);
            
            $.ajax({
                url: timeTrackerAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_client_services',
                    client_id: clientId,
                    nonce: timeTrackerAjax.nonce
                },
                beforeSend: function() {
                    serviceSelect.html('<option value="">Ładowanie usług...</option>');
                },
                success: function(response) {
                    if (response.success) {
                        var options = '<option value="">Wybierz usługę...</option>';
                        $.each(response.data, function(index, service) {
                            options += '<option value="' + service.id + '" data-is-fixed="' + service.is_fixed_price + '">' + service.service_name + '</option>';
                        });
                        serviceSelect.html(options);
                    } else {
                        serviceSelect.html('<option value="">Błąd ładowania usług</option>');
                    }
                },
                error: function() {
                    serviceSelect.html('<option value="">Błąd połączenia</option>');
                }
            });
        } else {
            serviceSelect.prop('disabled', true).html('<option value="">Najpierw wybierz klienta</option>');
            $('#hours-field').show();
            $('#fixed-amount-field').hide();
        }
    });
    
    // Przełączanie między godzinami a kwotą ryczałtową
    $('#service_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var isFixed = selectedOption.data('is-fixed');
        var hoursField = $('#hours-field');
        var fixedField = $('#fixed-amount-field');
        var hoursInput = $('#hours');
        var fixedInput = $('#fixed_amount');
        
        if (isFixed == 1) {
            hoursField.hide();
            hoursInput.val('').prop('required', false);
            fixedField.show();
            fixedInput.prop('required', true);
        } else {
            hoursField.show();
            hoursInput.prop('required', true);
            fixedField.hide();
            fixedInput.val('').prop('required', false);
        }
    });
    
    // Walidacja daty (nie może być z przyszłości)
    $('#entry_date').on('change', function() {
        var selectedDate = new Date($(this).val());
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate > today) {
            alert('Data nie może być z przyszłości!');
            $(this).val('');
        }
    });
    
    // Zapisz jako szkic
    $('#save-draft').on('click', function() {
        $('#entry_status').val('draft');
        $('#time-entry-form').submit();
    });
    
    // Domyślnie zapisz jako zgłoszony
    $('input[name="submit_time_entry"]').on('click', function() {
        $('#entry_status').val('submitted');
    });
    
    // Auto-formatowanie godzin (co 0.25)
    $('#hours').on('blur', function() {
        var value = parseFloat($(this).val());
        if (!isNaN(value)) {
            var rounded = Math.round(value * 4) / 4;
            $(this).val(rounded.toFixed(2));
        }
    });
    
    // Auto-formatowanie kwoty
    $('#fixed_amount').on('blur', function() {
        var value = parseFloat($(this).val());
        if (!isNaN(value)) {
            $(this).val(value.toFixed(2));
        }
    });
    
    // Przewijanie do formularza po błędzie
    if ($('.error').length > 0) {
        $('html, body').animate({
            scrollTop: $('.time-tracker-form').offset().top - 100
        }, 500);
    }
});