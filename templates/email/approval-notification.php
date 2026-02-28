<?php
/**
 * Szablon emaila z powiadomieniem o zatwierdzeniu/odrzuceniu wpisów
 * 
 * @param string $employee_name Imię i nazwisko pracownika
 * @param string $status Status (approved/rejected)
 * @param int $entries_count Liczba wpisów
 * @param string $manager_name Imię i nazwisko managera
 * @param string $comments Komentarze (opcjonalnie)
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
            background: <?php echo $status === 'approved' ? '#5cb85c' : '#d9534f'; ?>;
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
        .status-box {
            background: white;
            border: 2px solid <?php echo $status === 'approved' ? '#5cb85c' : '#d9534f'; ?>;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
        .status-icon {
            font-size: 48px;
            margin: 10px 0;
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
        .comments {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo $status === 'approved' ? 'Twoje wpisy zostały zatwierdzone' : 'Twoje wpisy wymagają poprawki'; ?></h1>
    </div>
    
    <div class="content">
        <p>Witaj <strong><?php echo $employee_name; ?></strong>,</p>
        
        <div class="status-box">
            <div class="status-icon">
                <?php if ($status === 'approved'): ?>
                    ✓
                <?php else: ?>
                    ✗
                <?php endif; ?>
            </div>
            
            <h2>
                <?php if ($status === 'approved'): ?>
                    Gratulacje! Twoje wpisy zostały zatwierdzone.
                <?php else: ?>
                    Niestety, Twoje wpisy zostały odrzucone.
                <?php endif; ?>
            </h2>
            
            <p>
                <?php echo $manager_name; ?> <?php echo $status === 'approved' ? 'zatwierdził' : 'odrzucił'; ?> 
                <strong><?php echo $entries_count; ?></strong> Twoich wpisów godzinowych.
            </p>
            
            <?php if ($status === 'rejected' && $comments): ?>
            <div class="comments">
                <h3>Komentarz od managera:</h3>
                <p><?php echo nl2br($comments); ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <p>Kliknij poniższy przycisk, aby przejść do systemu i zobaczyć szczegóły:</p>
        
        <p style="text-align: center;">
            <a href="<?php echo $dashboard_url; ?>" class="button">Przejdź do systemu</a>
        </p>
        
        <?php if ($status === 'rejected'): ?>
        <p>Po wprowadzeniu poprawek, pamiętaj o ponownym zgłoszeniu wpisów do zatwierdzenia.</p>
        <?php endif; ?>
        
        <p>Pozdrawiamy,<br>
        Zespół <?php echo get_bloginfo('name'); ?></p>
    </div>
    
    <div class="footer">
        <p>Wiadomość wygenerowana automatycznie przez system Time Tracker Pro.<br>
        Prosimy nie odpowiadać na ten email.</p>
    </div>
</body>
</html>