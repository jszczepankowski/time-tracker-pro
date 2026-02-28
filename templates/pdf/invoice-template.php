<?php
/**
 * Szablon PDF dla faktury
 * 
 * @param object $client Dane klienta
 * @param array $entries Wpisy godzinowe
 * @param array $summary Podsumowanie
 * @param int $month Miesiąc
 * @param int $year Rok
 * @param string $invoice_number Numer faktury
 */

// To jest tylko przykładowy szablon HTML, który można konwertować do PDF
// W rzeczywistości potrzebna byłaby biblioteka do generowania PDF (np. DomPDF, TCPDF)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Faktura <?php echo $invoice_number; ?></title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .container {
            width: 210mm;
            margin: 0 auto;
            padding: 20mm;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 20px;
        }
        .header h1 {
            color: #0073aa;
            margin: 0;
            font-size: 24px;
        }
        .header .subtitle {
            color: #666;
            font-size: 14px;
        }
        .company-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .company-box {
            flex: 1;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .company-box h3 {
            margin-top: 0;
            color: #0073aa;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        .invoice-details {
            background: #0073aa;
            color: white;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        .invoice-details h2 {
            margin: 0;
            font-size: 18px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .table th {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        .table td {
            border: 1px solid #dee2e6;
            padding: 10px;
        }
        .table tfoot td {
            background: #f8f9fa;
            font-weight: bold;
        }
        .summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .summary-row.total {
            font-size: 16px;
            font-weight: bold;
            color: #0073aa;
            border-top: 2px solid #dee2e6;
            padding-top: 10px;
            margin-top: 10px;
        }
        .footer {
            text-align: center;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #666;
            font-size: 11px;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>FAKTURA VAT <?php echo $invoice_number; ?></h1>
            <div class="subtitle">Za okres: <?php echo date_i18n('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></div>
        </div>
        
        <div class="company-info">
            <div class="company-box">
                <h3>Sprzedawca</h3>
                <p>
                    <strong><?php echo get_bloginfo('name'); ?></strong><br>
                    <?php echo get_bloginfo('description'); ?><br>
                    NIP: [TWÓJ NIP]<br>
                    Adres: [TWÓJ ADRES]<br>
                    Email: [TWÓJ EMAIL]<br>
                    Telefon: [TWÓJ TELEFON]
                </p>
            </div>
            
            <div class="company-box">
                <h3>Nabywca</h3>
                <p>
                    <strong><?php echo esc_html($client->company_name); ?></strong><br>
                    NIP: <?php echo esc_html($client->nip); ?><br>
                    <?php if ($client->address): ?>
                        Adres: <?php echo esc_html($client->address); ?><br>
                    <?php endif; ?>
                    <?php if ($client->email): ?>
                        Email: <?php echo esc_html($client->email); ?><br>
                    <?php endif; ?>
                    <?php if ($client->phone): ?>
                        Telefon: <?php echo esc_html($client->phone); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="invoice-details">
            <h2>Zestawienie godzin pracy</h2>
            <p>Data wystawienia: <?php echo date_i18n('d.m.Y'); ?></p>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Lp.</th>
                    <th>Data</th>
                    <th>Pracownik</th>
                    <th>Usługa</th>
                    <th>Ilość</th>
                    <th>Cena jednostkowa</th>
                    <th>Wartość netto</th>
                </tr>
            </thead>
            <tbody>
                <?php $counter = 1; ?>
                <?php foreach ($entries as $entry): ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td><?php echo date('d.m.Y', strtotime($entry->entry_date)); ?></td>
                    <td><?php echo esc_html($entry->employee_name); ?></td>
                    <td><?php echo esc_html($entry->service_name); ?></td>
                    <td>
                        <?php if ($entry->is_fixed_price): ?>
                            1 szt.
                        <?php else: ?>
                            <?php echo number_format($entry->hours, 2); ?> h
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entry->is_fixed_price): ?>
                            <?php echo number_format($entry->fixed_amount, 2); ?> zł
                        <?php else: ?>
                            <?php echo number_format($entry->hourly_rate, 2); ?> zł/h
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entry->is_fixed_price): ?>
                            <?php echo number_format($entry->fixed_amount, 2); ?> zł
                        <?php else: ?>
                            <?php echo number_format($entry->hours * $entry->hourly_rate, 2); ?> zł
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="summary">
            <div class="summary-row">
                <span>Łączna liczba godzin:</span>
                <span><?php echo number_format($summary['total_hours'], 2); ?> h</span>
            </div>
            
            <?php if ($summary['hourly_amount'] > 0): ?>
            <div class="summary-row">
                <span>Wartość za godziny:</span>
                <span><?php echo number_format($summary['hourly_amount'], 2); ?> zł</span>
            </div>
            <?php endif; ?>
            
            <?php if ($summary['fixed_amount'] > 0): ?>
            <div class="summary-row">
                <span>Usługi ryczałtowe:</span>
                <span><?php echo number_format($summary['fixed_amount'], 2); ?> zł</span>
            </div>
            <?php endif; ?>
            
            <div class="summary-row total">
                <span>RAZEM DO ZAPŁATY:</span>
                <span><?php echo number_format($summary['total_amount'], 2); ?> zł</span>
            </div>
            
            <?php 
            $vat_rate = 0.23; // 23% VAT
            $netto = $summary['total_amount'] / (1 + $vat_rate);
            $vat = $summary['total_amount'] - $netto;
            ?>
            <div class="summary-row">
                <span>W tym VAT (23%):</span>
                <span><?php echo number_format($vat, 2); ?> zł</span>
            </div>
            <div class="summary-row">
                <span>Wartość netto:</span>
                <span><?php echo number_format($netto, 2); ?> zł</span>
            </div>
        </div>
        
        <div class="footer">
            <p>
                Faktura wygenerowana przez system Time Tracker Pro<br>
                Data generowania: <?php echo current_time('d.m.Y H:i'); ?>
            </p>
        </div>
    </div>
</body>
</html>