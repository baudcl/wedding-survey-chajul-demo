function toggleDates(show) {
    document.getElementById('dates-section').style.display = show ? 'block' : 'none';
}

function toggleChildren(show) {
    const section = document.getElementById('children-section');
    const inputs = section.querySelectorAll('input, select');
    if (show) {
        section.style.display = 'block';
        inputs.forEach(input => input.disabled = false);
        if (!document.querySelector('.child-entry')) addChild();
    } else {
        section.style.display = 'none';
        inputs.forEach(input => input.disabled = true);
        document.getElementById('children-entries').innerHTML = '';
        document.getElementById('enfants_non').checked = true;
    }
}

function addChild() {
    const container = document.getElementById('children-entries');
    const div = document.createElement('div');
    div.className = 'child-entry';
    div.innerHTML = `
        <input type="text" name="prenom_enfant[]" placeholder="Prénom" required>
        <div class="custom-select">
            <select name="age_enfant[]" class="age-select" required>
                <option value="">-- Âge --</option>
                ${Array.from({ length: 19 }, (_, i) => `<option value="${i}">${i} ans</option>`).join('')}
            </select>
        </div>
        <button type="button" class="remove-child-btn" onclick="removeChild(this)">×</button>
    `;
    container.appendChild(div);

    // Apply custom select logic to the new child entry
    initCustomSelect(div.querySelector('.custom-select'));
}

function initCustomSelect(customSelect) {
    const select = customSelect.querySelector('select');
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
}

function removeChild(button) {
    const container = document.getElementById('children-entries');
    const childEntry = button.closest('.child-entry');
    
    if (childEntry && container.contains(childEntry)) {
        childEntry.remove();
        
        // Si plus d'enfants, ajouter automatiquement un enfant vide
        if (container.children.length === 0) {
            addChild();
        }
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
    // Ne pas valider les champs vides (la validation HTML5 s'en chargera)
    if (input.value.trim() === '') {
        input.classList.remove('invalid');
        removeErrorMessage(input);
        return;
    }
    
    const valueLength = input.value.trim().length;
    const isInvalid = valueLength < 3; // Active l'erreur uniquement si entre 1 et 2 caractères
    input.classList.toggle('invalid', isInvalid);
    isInvalid
        ? createErrorMessage(input, 'Veuillez entrer au moins 3 caractères.')
        : removeErrorMessage(input);
}

function validateEmail(input) {
    // Ne pas valider les champs vides (la validation HTML5 s'en chargera)
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
    // Ne pas valider les champs vides (la validation HTML5 s'en chargera)
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
    // Créer ou utiliser un élément de feedback existant
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
    
    // Faire défiler vers le message
    feedbackEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function confirmSubmission(event) {
    if (event) event.preventDefault();
    
    const form = document.getElementById('weddingForm');

    if (!form.checkValidity()) {
        // Trouver le premier champ invalide et le mettre en évidence
        const invalidFields = form.querySelectorAll(':invalid');
        if (invalidFields.length > 0) {
            invalidFields[0].focus();
            showFeedback("Veuillez remplir tous les champs obligatoires.", true);
            return false;
        }
    }

    // Vérifier les validations personnalisées
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

    // Masquer tout message de feedback précédent
    const feedbackEl = document.getElementById('form-feedback');
    if (feedbackEl) feedbackEl.style.display = 'none';

    // Simuler l'envoi réussi en mode DEBUG pour tester l'interface
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

    // Conversion du FormData en JSON
    // Certains serveurs PHP ont du mal avec multipart/form-data
    let requestOptions = {
        method: 'POST',
        body: formData,
        headers: {} // N'ajoutez PAS Content-Type pour FormData
    };

    fetch(form.action, requestOptions)
    .then(response => {
        console.log("Réponse reçue:", response.status, response.statusText);
        return response.text().then(text => {
            console.log("Texte brut de la réponse:", text);
            
            // Vérifier si la réponse est un HTML au lieu d'un JSON (signe d'erreur PHP)
            if (text.includes('<!DOCTYPE html>') || text.includes('<html')) {
                throw new Error("Le serveur a rencontré une erreur interne");
            }
            
            // Si la réponse n'est pas OK
            if (!response.ok) {
                throw new Error(`Erreur serveur : ${response.status}`);
            }
            
            // Si la réponse est vide
            if (!text.trim()) {
                throw new Error("Le serveur n'a pas renvoyé de réponse");
            }
            
            // Essayer de parser en JSON
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
            showFeedback("Merci ! Votre réponse a bien été prise en compte.");
            form.reset();
            
            // Redirection après un délai
            setTimeout(() => {
                window.location.href = '../index.html';
            }, 3000);
        } else {
            throw new Error(data.message || "Une erreur s'est produite");
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        
        // Message d'erreur plus convivial
        let errorMessage = "Une erreur est survenue lors de l'envoi du formulaire. ";
        
        // Si c'est une erreur réseau (pas de connexion)
        if (error.message === "Failed to fetch" || error.message.includes("NetworkError")) {
            errorMessage += "Veuillez vérifier votre connexion internet.";
        } 
        // Si c'est une erreur 500 du serveur
        else if (error.message.includes("500")) {
            errorMessage += "Le serveur rencontre actuellement des difficultés. Veuillez réessayer plus tard ou contacter l'assistance.";
        } 
        // Autre erreur
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
    // Vérifier si le style existe déjà
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

    // Initialiser les selects personnalisés
    document.querySelectorAll('.custom-select').forEach(initCustomSelect);

    // Attacher le gestionnaire d'événements au formulaire
    const form = document.getElementById('weddingForm');
    if (form) {
        form.addEventListener('submit', confirmSubmission);
    }
});