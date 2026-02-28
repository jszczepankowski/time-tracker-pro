<?php
/**
 * Szablon emaila z przypomnieniem o uzupełnieniu godzin
 * 
 * @param string $employee_name Imię i nazwisko pracownika
 * @param int $draft_count Liczba wpisów w szkicach
 * @param string $dashboard_url URL do dashboardu pracownika
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: #0073aa;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background: #f8f9fa;
            padding: 30px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 5px 5px;
        }
        .button {
            display: inline-block;
            background: #0073aa;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            color: #666;
            font-size: 12px;
        }
        .stat-box {
            background: white;
            border: 2px solid #0073aa;
            border-radius: 5px;
            padding: 15px;
            text-align: center;
            margin: 20px 0;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #0073aa;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Przypomnienie o uzupełnieniu godzin</h1>
    </div>
    
    <div class="content">
        <p>Witaj <strong><?php echo $employee_name; ?></strong>,</p>
        
        <p>To jest automatyczne przypomnienie o uzupełnieniu godzin pracy za bieżący tydzień.</p>
        
        <?php if ($draft_count > 0): ?>
        <div class="stat-box">
            <p>Masz <span class="stat-number"><?php echo $draft_count; ?></span> wpisów w statusie szkicu.</p>
            <p>Pamiętaj, aby zgłosić je do zatwierdzenia!</p>
        </div>
        <?php endif; ?>
        
        <p>Kliknij poniższy przycisk, aby przejść do systemu i uzupełnić swoje godziny:</p>
        
        <p style="text-align: center;">
            <a href="<?php echo $dashboard_url; ?>" class="button">Uzupełnij godziny pracy</a>
        </p>
        
        <p>Przypominamy, że godziny powinny być uzupełniane na bieżąco, a zgłaszane do zatwierdzenia na koniec każdego tygodnia.</p>
        
        <p>Jeśli masz jakiekolwiek pytania, skontaktuj się ze swoim przełożonym.</p>
        
        <p>Pozdrawiamy,<br>
        Zespół <?php echo get_bloginfo('name'); ?></p>
    </div>
    
    <div class="footer">
        <p>Wiadomość wygenerowana automatycznie przez system Time Tracker Pro.<br>
        Prosimy nie odpowiadać na ten email.</p>
    </div>
</body>
</html>