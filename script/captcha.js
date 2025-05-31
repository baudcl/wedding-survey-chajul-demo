let targetUrl = '';
let captchaCode = generateCaptchaCode();

function generateCaptchaCode() {
    return Math.floor(100000 + Math.random() * 900000).toString();
}

function openCaptchaModal(url) {
    targetUrl = url;
    document.getElementById('captcha-code').textContent = captchaCode;
    document.getElementById('captcha-modal').style.display = 'flex';
}

function closeCaptchaModal() {
    document.getElementById('captcha-modal').style.display = 'none';
    document.getElementById('captcha-input').value = '';
    document.getElementById('captcha-error').style.display = 'none';
    captchaCode = generateCaptchaCode(); // Refresh captcha code
}

function verifyCaptcha() {
    const userInput = document.getElementById('captcha-input').value;
    if (userInput === captchaCode) {
        window.location.href = targetUrl;
    } else {
        document.getElementById('captcha-error').style.display = 'block';
    }
}

// Refresh captcha when the user leaves the page
window.addEventListener('beforeunload', () => {
    document.getElementById('captcha-input').value = '';
    captchaCode = generateCaptchaCode();
});
