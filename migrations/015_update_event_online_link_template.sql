-- Migration: Update event_online_link template with improved design
-- Description: Update the email template for online meeting links to have better formatting and a call-to-action button

UPDATE email_templates 
SET 
    subject = 'La tua iscrizione è stata approvata! - {titolo}',
    body_html = '<h2>La tua iscrizione è stata approvata!</h2><p>Ciao <strong>{nome}</strong>,</p><p>La tua partecipazione all\'evento <strong>{titolo}</strong> è stata confermata!</p><h3>Dettagli Evento</h3><ul><li><strong>Data:</strong> {data}</li><li><strong>Ora:</strong> {ora}</li><li><strong>Piattaforma:</strong> {piattaforma}</li></ul><h3>Come Partecipare</h3><p>Clicca sul pulsante qui sotto per accedere alla riunione:</p><p style="text-align: center; margin: 20px 0;"><a href="{link}" style="background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">🎥 Partecipa Alla Riunione</a></p>{password_info}{istruzioni}<p>Ti aspettiamo!</p>',
    body_text = 'Gentile {nome} {cognome},\n\nLa tua iscrizione è stata approvata!\n\nLa tua partecipazione all\'evento {titolo} è stata confermata!\n\nDettagli Evento:\n- Data: {data}\n- Ora: {ora}\n- Piattaforma: {piattaforma}\n\nLink di accesso:\n{link}\n{password_info}{istruzioni}\n\nTi aspettiamo!\n\nCordiali saluti,\n{app_name}'
WHERE code = 'event_online_link';
