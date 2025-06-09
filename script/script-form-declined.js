function toggleChildrenAbsent(show) {
    const section = document.getElementById('children-absent-section');
    const select = section.querySelector('select');
    if (show) {
        section.style.display = 'block';
        select.disabled = false;
    } else {
        section.style.display = 'none';
        select.disabled = true;
        select.value = '';
    }
}

function toggleMotifAutre() {
    const motifRadios = document.querySelectorAll('input[name="motif"]');
    const autreSection = document.getElementById('motif-autre-section');
    const autreTextarea = autreSection.querySelector('textarea');
    
    let showAutre = false;
    motifRadios.forEach(radio => {
        if (radio.checked && radio.value === 'autre') {
            showAutre = true;
        }
    });
    
    if (showAutre) {
        autreSection.style.display = 'block';
        autreTextarea.disabled = false;
    } else {
        autreSection.style.display = 'none';
        autreTextarea.disabled = true;
        autreTextarea.value = '';
    }
}

function toggleParticipationDetails() {
    const checkboxes = document.querySelectorAll('input[name="participation[]"]');
    const videoDetails = document.getElementById('video-details');
    const celebrationDetails = document.getElementById('celebration-details');
    
    let showVideo = false;
    let showCelebration = false;
    
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            if (checkbox.value === 'video_message') {
                showVideo = true;
            } else if (checkbox.value === 'celebration_retour') {
                showCelebration = true;
            }
        }
    });
    
    // Gestion des détails vidéo
    if (showVideo) {
        videoDetails.style.display = 'block';
        videoDetails.querySelector('input').disabled = false;
    } else {
        videoDetails.style.display = 'none';
        videoDetails.querySelector('input').disabled = true;
        videoDetails.querySelector('input').value = '';
    }
    
    // Gestion des détails de célébration
    if (showCelebration) {
        celebrationDetails.style.display = 'block';
        celebrationDetails.querySelector('textarea').disabled = false;
    } else {
        celebrationDetails.style.display = 'none';
        celebrationDetails.querySelector('textarea').disabled = true;
        celebrationDetails.querySelector('textarea').value = '';
    }
}

function createErrorMessage(input, message) {
    removeErrorMessage(input);
    const span = document.createElement('span');
    span.className = 'error-message';
    span.textContent = message;
    input.insertAdjacentElement('afterend', span);
}

function removeErrorMessage(input) {
    const sibling = input.nextElementSibling;
    if (sibling && sibling.classList.contains('error-message')) {
        sibling.remove();
    }
}

function validateText(input) {
    if (input.value.trim() === '') {
        input.classList.remove('invalid');
        removeErrorMessage(input);
        return;
    }
    
    const valueLength = input.value.trim().length;
    const isInvalid = valueLength < 3;
    input.classList.toggle('invalid', isInvalid);
    isInvalid
        ? createErrorMessage(input, 'Veuillez entrer au moins 3 caractères.')
        : removeErrorMessage(input);
}

function validateEmail(input) {
    if (input.value.trim() === '') {
        input.classList.remove('invalid');
        removeErrorMessage(input);
        return;
    }
    
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value);
    input.classList.toggle('invalid', !valid);
    !valid
        ? createErrorMessage(input, 'Veuillez entrer une adresse e-mail valide.')
        : removeErrorMessage(input);
}

function validatePhone(input) {
    if (input.value.trim() === '') {
        input.classList.remove('invalid');
        removeErrorMessage(input);
        return;
    }
    
    const valid = /^0[1-9](\.[0-9]{2}){4}$/.test(input.value);
    input.classList.toggle('invalid', !valid);
    !valid
        ? createErrorMessage(input, 'Format attendu : 06.00.00.00.00')
        : removeErrorMessage(input);
}

function showFeedback(message, isError = false) {
    let feedbackEl = document.getElementById('form-feedback');
    
    if (!feedbackEl) {
        feedbackEl = document.createElement('div');
        feedbackEl.id = 'form-feedback';
        const form = document.querySelector('form');
        form.insertAdjacentElement('beforebegin', feedbackEl);
    }
    
    feedbackEl.className = isError ? 'feedback-error' : 'feedback-success';
    feedbackEl.textContent = message;
    feedbackEl.style.display = 'block';
    
    feedbackEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function initCustomSelect(customSelect) {
    const select = customSelect.querySelector('select');
    if (!select) return;
    
    // Vérifier si le custom select est déjà initialisé
    if (customSelect.querySelector('.select-selected')) return;
    
    const selectedDiv = document.createElement('div');
    selectedDiv.className = 'select-selected';
    selectedDiv.textContent = select.options[select.selectedIndex].textContent;
    customSelect.appendChild(selectedDiv);

    const itemsDiv = document.createElement('div');
    itemsDiv.className = 'select-items';
    Array.from(select.options).forEach((option, index) => {
        const itemDiv = document.createElement('div');
        itemDiv.textContent = option.textContent;
        itemDiv.addEventListener('click', () => {
            select.selectedIndex = index;
            selectedDiv.textContent = option.textContent;
            itemsDiv.style.display = 'none';
            selectedDiv.classList.remove('select-arrow-active');
            
            // Déclencher l'événement change pour les validations
            select.dispatchEvent(new Event('change'));
        });
        itemsDiv.appendChild(itemDiv);
    });
    customSelect.appendChild(itemsDiv);

    selectedDiv.addEventListener('click', () => {
        itemsDiv.style.display = itemsDiv.style.display === 'block' ? 'none' : 'block';
        selectedDiv.classList.toggle('select-arrow-active');
    });

    document.addEventListener('click', (e) => {
        if (!customSelect.contains(e.target)) {
            itemsDiv.style.display = 'none';
            selectedDiv.classList.remove('select-arrow-active');
        }
    });
    
    // Masquer le select natif seulement après avoir créé le custom select
    select.style.display = 'none';
}

function confirmSubmission(event) {
    if (event) event.preventDefault();
    
    const form = document.getElementById('weddingDeclineForm');

    if (!form.checkValidity()) {
        const invalidFields = form.querySelectorAll(':invalid');
        if (invalidFields.length > 0) {
            invalidFields[0].focus();
            showFeedback("Veuillez remplir tous les champs obligatoires.", true);
            return false;
        }
    }

    const invalidInputs = form.querySelectorAll('.invalid');
    if (invalidInputs.length > 0) {
        invalidInputs[0].focus();
        showFeedback("Veuillez corriger les erreurs dans le formulaire.", true);
        return false;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Envoi en cours...';

    const feedbackEl = document.getElementById('form-feedback');
    if (feedbackEl) feedbackEl.style.display = 'none';

    // Mode DEBUG pour tester l'interface
    const DEBUG_MODE = false;
    
    if (DEBUG_MODE) {
        setTimeout(() => {
            showFeedback("Merci ! Votre réponse a bien été prise en compte.");
            form.reset();
            setTimeout(() => {
                window.location.href = '../index.html';
            }, 3000);
        }, 1000);
        return false;
    }

    const formData = new FormData(form);

    let requestOptions = {
        method: 'POST',
        body: formData,
        headers: {}
    };

    fetch(form.action, requestOptions)
    .then(response => {
        console.log("Réponse reçue:", response.status, response.statusText);
        return response.text().then(text => {
            console.log("Texte brut de la réponse:", text);
            
            if (text.includes('<!DOCTYPE html>') || text.includes('<html')) {
                throw new Error("Le serveur a rencontré une erreur interne");
            }
            
            if (!response.ok) {
                throw new Error(`Erreur serveur : ${response.status}`);
            }
            
            if (!text.trim()) {
                throw new Error("Le serveur n'a pas renvoyé de réponse");
            }
            
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("Erreur de parsing JSON:", e);
                throw new Error(`Réponse du serveur invalide`);
            }
        });
    })
    .then(data => {
        if (data && data.success) {
            showFeedback("Merci ! Votre réponse a bien été enregistrée. Nous espérons vous voir bientôt !");
            form.reset();
            
            setTimeout(() => {
                window.location.href = '../index.html';
            }, 3000);
        } else {
            throw new Error(data.message || "Une erreur s'est produite");
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        
        let errorMessage = "Une erreur est survenue lors de l'envoi du formulaire. ";
        
        if (error.message === "Failed to fetch" || error.message.includes("NetworkError")) {
            errorMessage += "Veuillez vérifier votre connexion internet.";
        } 
        else if (error.message.includes("500")) {
            errorMessage += "Le serveur rencontre actuellement des difficultés. Veuillez réessayer plus tard ou contacter l'assistance.";
        } 
        else {
            errorMessage += error.message;
        }
        
        showFeedback(errorMessage, true);
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
    });

    return false;
}

// Ajouter du CSS pour les messages de feedback
function addFeedbackStyles() {
    if (document.getElementById('feedback-styles')) return;
    
    const style = document.createElement('style');
    style.id = 'feedback-styles';
    style.textContent = `
        #form-feedback {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        
        .feedback-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .feedback-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
    `;
    document.head.appendChild(style);
}

document.addEventListener('DOMContentLoaded', () => {
    // Ajouter le CSS pour les messages de feedback
    addFeedbackStyles();
    
    // Initialiser le champ timestamp pour anti-spam
    const timestampField = document.getElementById('timestamp');
    if (timestampField) {
        timestampField.value = Date.now();
    }

    // Initialiser les selects personnalisés en premier
    setTimeout(() => {
        document.querySelectorAll('.custom-select').forEach(initCustomSelect);
    }, 100);

    // Initialiser les validations de champ
    document.querySelectorAll('input[type="text"], textarea').forEach(field => {
        if (field.required) {
            field.addEventListener('blur', () => validateText(field));
            field.addEventListener('input', () => validateText(field));
        }
    });
    
    document.querySelectorAll('input[type="email"]').forEach(field => {
        field.addEventListener('blur', () => validateEmail(field));
        field.addEventListener('input', () => validateEmail(field));
    });
    
    document.querySelectorAll('input[type="tel"]').forEach(field => {
        field.addEventListener('blur', () => validatePhone(field));
        field.addEventListener('input', () => validatePhone(field));
    });

    // Gestionnaires d'événements pour les sections conditionnelles
    document.querySelectorAll('input[name="motif"]').forEach(radio => {
        radio.addEventListener('change', toggleMotifAutre);
    });

    document.querySelectorAll('input[name="participation[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', toggleParticipationDetails);
    });

    // Attacher le gestionnaire d'événements au formulaire
    const form = document.getElementById('weddingDeclineForm');
    if (form) {
        form.addEventListener('submit', confirmSubmission);
    }
});