// script/captcha.js
let targetUrl = '';
let captchaCode = generateCaptchaCode();

function generateCaptchaCode() {
    return Math.floor(100000 + Math.random() * 900000).toString();
}

function openCaptchaModal(url) {
    targetUrl = url;
    captchaCode = generateCaptchaCode(); // Générer un nouveau code à chaque ouverture
    document.getElementById('captcha-code').textContent = captchaCode;
    document.getElementById('captcha-modal').style.display = 'flex';
    document.getElementById('captcha-input').value = '';
    document.getElementById('captcha-error').style.display = 'none';
    
    // Focus sur le champ de saisie
    setTimeout(() => {
        document.getElementById('captcha-input').focus();
    }, 100);
}

function closeCaptchaModal() {
    document.getElementById('captcha-modal').style.display = 'none';
    document.getElementById('captcha-input').value = '';
    document.getElementById('captcha-error').style.display = 'none';
}

function verifyCaptcha() {
    const userInput = document.getElementById('captcha-input').value;
    if (userInput === captchaCode) {
        // Succès - rediriger vers le formulaire
        closeCaptchaModal();
        window.location.href = targetUrl;
    } else {
        // Erreur - afficher le message d'erreur
        document.getElementById('captcha-error').style.display = 'block';
        document.getElementById('captcha-input').value = '';
        document.getElementById('captcha-input').focus();
    }
}

// Permettre la validation avec Enter
document.addEventListener('DOMContentLoaded', function() {
    const captchaInput = document.getElementById('captcha-input');
    if (captchaInput) {
        captchaInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                verifyCaptcha();
            }
        });
    }
});

// Fermer le modal si on clique en dehors
window.addEventListener('click', function(event) {
    const modal = document.getElementById('captcha-modal');
    if (event.target === modal) {
        closeCaptchaModal();
    }
});