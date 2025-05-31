<?php
// Adresse email de test
$to = "test-myrtille@yopmail.com"; // Remplacez par votre adresse email
$subject = "Test de la fonction mail()";
$message = "Ceci est un test pour vérifier la configuration de la fonction mail().";
$headers = "From: test@example.com\r\n";
$headers .= "Reply-To: test@example.com\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Envoi de l'email
if (mail($to, $subject, $message, $headers)) {
    echo "Email envoyé avec succès à $to.";
} else {
    echo "Échec de l'envoi de l'email. Vérifiez la configuration de votre serveur.";
}
?>
