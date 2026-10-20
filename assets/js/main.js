// SmartEcoTrack - Main JavaScript File

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    initTooltips();
    
    // Initialize smooth scrolling
    initSmoothScroll();
    
    // Initialize chatbot
    initChatbot();
    
    // Initialize animations
    initAnimations();
    
    // Initialize form validations
    initFormValidation();
    
    // Initialize map if on dashboard
    if (document.getElementById('map')) {
        initMap();
    }
});

// Tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', (e) => {
            const tip = document.createElement('div');
            tip.className = 'tooltip';
            tip.textContent = e.target.dataset.tooltip;
            document.body.appendChild(tip);
            
            const rect = e.target.getBoundingClientRect();
            tip.style.left = rect.left + (rect.width / 2) - (tip.offsetWidth / 2) + 'px';
            tip.style.top = rect.top - tip.offsetHeight - 10 + 'px';
        });
        
        tooltip.addEventListener('mouseleave', () => {
            const tip = document.querySelector('.tooltip');
            if (tip) tip.remove();
        });
    });
}

// Smooth Scrolling
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// Chatbot
function initChatbot() {
    const chatToggle = document.getElementById('chat-toggle');
    const chatContainer = document.getElementById('chat-container');
    const chatInput = document.getElementById('chat-input');
    const chatSend = document.getElementById('chat-send');
    const chatMessages = document.getElementById('chat-messages');
    
    if (!chatToggle || !chatContainer) return;
    
    chatToggle.addEventListener('click', () => {
        chatContainer.classList.toggle('d-none');
    });
    
    if (chatSend) {
        chatSend.addEventListener('click', sendMessage);
    }
    
    if (chatInput) {
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
    }
    
    function sendMessage() {
        const message = chatInput.value.trim();
        if (!message) return;
        
        // Add user message
        addMessage(message, 'user');
        chatInput.value = '';
        
        // Show typing indicator
        const typing = addTypingIndicator();
        
        // Send to API
        fetch('api/chatbot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ message: message })
        })
        .then(response => response.json())
        .then(data => {
            typing.remove();
            if (data.success) {
                addMessage(data.response, 'bot');
            } else {
                addMessage('Sorry, I encountered an error. Please try again.', 'bot');
            }
        })
        .catch(error => {
            typing.remove();
            addMessage('Sorry, I encountered an error. Please try again.', 'bot');
            console.error('Error:', error);
        });
    }
    
    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `message ${sender}-message`;
        messageDiv.innerHTML = `
            <div class="message-bubble ${sender}-bubble">
                ${text}
            </div>
        `;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    function addTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'typing-indicator';
        typingDiv.innerHTML = '<span></span><span></span><span></span>';
        chatMessages.appendChild(typingDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
        return typingDiv;
    }
}

// Form Validation
function initFormValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            showError(input, 'This field is required');
            isValid = false;
        } else {
            clearError(input);
            
            // Email validation
            if (input.type === 'email' && !validateEmail(input.value)) {
                showError(input, 'Please enter a valid email address');
                isValid = false;
            }
            
            // Password validation
            if (input.type === 'password' && input.value.length < 6) {
                showError(input, 'Password must be at least 6 characters');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function showError(input, message) {
    const formGroup = input.closest('.form-group');
    const error = formGroup.querySelector('.error-message') || document.createElement('div');
    error.className = 'error-message text-danger mt-1';
    error.textContent = message;
    if (!formGroup.querySelector('.error-message')) {
        formGroup.appendChild(error);
    }
    input.classList.add('is-invalid');
}

function clearError(input) {
    const formGroup = input.closest('.form-group');
    const error = formGroup.querySelector('.error-message');
    if (error) error.remove();
    input.classList.remove('is-invalid');
}

// Animations
function initAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fadeInUp');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1
    });
    
    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });
}

// Map Initialization
function initMap() {
    // Default coordinates (Manila, Philippines)
    const defaultLat = 14.5995;
    const defaultLng = 120.9842;
    
    const map = L.map('map').setView([defaultLat, defaultLng], 12);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    // Load active incidents
    loadIncidentsOnMap(map);
    
    // Add click handler
    map.on('click', function(e) {
        if (document.getElementById('latitude') && document.getElementById('longitude')) {
            document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
            document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
            
            // Remove existing marker
            if (window.clickMarker) {
                map.removeLayer(window.clickMarker);
            }
            
            // Add new marker
            window.clickMarker = L.marker([e.latlng.lat, e.latlng.lng])
                .addTo(map)
                .bindPopup('Selected Location')
                .openPopup();
        }
    });
}

function loadIncidentsOnMap(map) {
    fetch('api/get_active_incidents.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.incidents) {
                data.incidents.forEach(incident => {
                    const marker = L.marker([incident.latitude, incident.longitude])
                        .addTo(map)
                        .bindPopup(`
                            <strong>${incident.title}</strong><br>
                            Category: ${incident.category}<br>
                            Severity: ${incident.severity}<br>
                            <a href="incident_view.php?id=${incident.id}">View Details</a>
                        `);
                    
                    // Color code based on severity
                    const severityColors = {
                        'low': '#4CAF50',
                        'medium': '#FFA000',
                        'high': '#FF5722',
                        'critical': '#D32F2F'
                    };
                    
                    const circle = L.circle([incident.latitude, incident.longitude], {
                        color: severityColors[incident.severity] || '#4CAF50',
                        fillColor: severityColors[incident.severity] || '#4CAF50',
                        fillOpacity: 0.2,
                        radius: 500
                    }).addTo(map);
                });
            }
        })
        .catch(error => console.error('Error loading incidents:', error));
}

// Notification System
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas ${getNotificationIcon(type)}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function getNotificationIcon(type) {
    const icons = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    };
    return icons[type] || icons.info;
}

// Location Detection
function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    });
                },
                (error) => {
                    reject(error);
                }
            );
        } else {
            reject(new Error('Geolocation not supported'));
        }
    });
}

// Loader
function showLoader() {
    const loader = document.createElement('div');
    loader.className = 'loader-overlay';
    loader.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(loader);
}

function hideLoader() {
    const loader = document.querySelector('.loader-overlay');
    if (loader) loader.remove();
}

// Confirmation Dialog
function showConfirm(message) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';
        overlay.innerHTML = `
            <div class="confirm-dialog">
                <p>${message}</p>
                <div class="confirm-buttons">
                    <button class="btn btn-success" id="confirm-yes">Yes</button>
                    <button class="btn btn-danger" id="confirm-no">No</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(overlay);
        
        document.getElementById('confirm-yes').addEventListener('click', () => {
            overlay.remove();
            resolve(true);
        });
        
        document.getElementById('confirm-no').addEventListener('click', () => {
            overlay.remove();
            resolve(false);
        });
    });
}