// Script JavaScript pour StarMarket

document.addEventListener('DOMContentLoaded', function() {
    // Gestion du dropdown menu
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.dropdown-menu');
        
        if (toggle && menu) {
            // Toggle sur clic
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
            });
            
            // Fermer quand on clique ailleurs
            document.addEventListener('click', function() {
                menu.style.display = 'none';
            });
            
            // Empêcher la fermeture quand on clique dans le menu
            menu.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });
    
    // Gestion des formulaires conditionnels (sell.php)
    const saleTypeInputs = document.querySelectorAll('input[name="sale_type"]');
    const realMoneyFields = document.getElementById('real-money-fields');
    const inGameFields = document.getElementById('in-game-fields');
    
    if (saleTypeInputs.length > 0) {
        saleTypeInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.value === 'REAL_MONEY') {
                    if (realMoneyFields) realMoneyFields.style.display = 'block';
                    if (inGameFields) inGameFields.style.display = 'none';
                } else if (this.value === 'IN_GAME') {
                    if (realMoneyFields) realMoneyFields.style.display = 'none';
                    if (inGameFields) inGameFields.style.display = 'block';
                }
            });
        });
        
        // Initialiser l'affichage
        const checkedInput = document.querySelector('input[name="sale_type"]:checked');
        if (checkedInput) {
            checkedInput.dispatchEvent(new Event('change'));
        }
    }
    
    // Gestion de la sélection de variantes (item.php)
    const variantSelect = document.getElementById('variant-select');
    const itemImage = document.getElementById('item-image');
    
    if (variantSelect && itemImage) {
        variantSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const imageUrl = selectedOption.dataset.image;
            
            if (imageUrl) {
                itemImage.src = imageUrl;
                itemImage.alt = selectedOption.text;
            }
        });
    }
    
    // Gestion des onglets (item.php)
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetTab = this.dataset.tab;
            
            // Retirer active de tous les boutons et contenus
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Ajouter active au bouton cliqué et son contenu
            this.classList.add('active');
            const targetContent = document.getElementById(targetTab);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });
    
    // Prévisualisation des images uploadées
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewId = this.dataset.preview;
            const preview = document.getElementById(previewId);
            
            if (file && preview) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // Confirmation pour les actions dangereuses
    const dangerButtons = document.querySelectorAll('.btn-danger, .btn-error');
    
    dangerButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const confirmMessage = this.dataset.confirm || 'Êtes-vous sûr de vouloir effectuer cette action ?';
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Auto-refresh pour les pages de messagerie (toutes les 30 secondes)
    if (window.location.pathname.includes('conversation.php') || window.location.pathname.includes('inbox.php')) {
        setInterval(function() {
            // Vérifier s'il y a de nouveaux messages
            const lastMessageTime = document.querySelector('.message:last-child')?.dataset.timestamp;
            
            if (lastMessageTime) {
                fetch(window.location.href + '&check_new=' + lastMessageTime)
                    .then(response => response.json())
                    .then(data => {
                        if (data.hasNew) {
                            location.reload();
                        }
                    })
                    .catch(error => console.error('Erreur lors de la vérification des nouveaux messages:', error));
            }
        }, 30000);
    }
    
    // Smooth scroll pour les ancres
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href').substring(1);
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Gestion du loading sur les boutons de formulaire
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitButton = this.querySelector('button[type="submit"], input[type="submit"]');
            
            if (submitButton) {
                submitButton.disabled = true;
                const originalText = submitButton.textContent || submitButton.value;
                
                if (submitButton.tagName === 'BUTTON') {
                    submitButton.textContent = 'Envoi en cours...';
                } else {
                    submitButton.value = 'Envoi en cours...';
                }
                
                // Réactiver après 10 secondes au cas où
                setTimeout(() => {
                    submitButton.disabled = false;
                    if (submitButton.tagName === 'BUTTON') {
                        submitButton.textContent = originalText;
                    } else {
                        submitButton.value = originalText;
                    }
                }, 10000);
            }
        });
    });
    
    // Masquer les alertes après 5 secondes
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
});

// Fonction utilitaire pour formater les prix
function formatPrice(price, currency) {
    if (currency === 'aUEC') {
        return new Intl.NumberFormat('fr-FR').format(price) + ' aUEC';
    }
    return new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency: currency || 'EUR'
    }).format(price);
}

// Fonction pour copier du texte dans le presse-papier
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copié dans le presse-papier !', 'success');
        });
    } else {
        // Fallback pour les navigateurs plus anciens
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        try {
            document.execCommand('copy');
            showNotification('Copié dans le presse-papier !', 'success');
        } catch (err) {
            showNotification('Impossible de copier le texte', 'error');
        }
        
        document.body.removeChild(textArea);
    }
}

// Fonction pour afficher des notifications temporaires
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.maxWidth = '300px';
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}