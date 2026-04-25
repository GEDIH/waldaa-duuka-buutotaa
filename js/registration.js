/**
 * WDB Member Registration System - Modern JavaScript Implementation
 * Multi-language support, form validation, step navigation, and API integration
 */

// Global variables
let currentStep = 1;
let currentLanguage = 'or'; // Default to Oromo
let isSubmitting = false;

// Multi-language translations
const translations = {
    or: {
        'registration-title': '👥 Miseensa Ta\'uuf Galmaa\'i',
        'back-home': '← Duubatti Deebi\'i',
        'welcome-title': '👥 Baga Nagaan Dhuftan',
        'welcome-subtitle': 'Waldaa Duuka Bu\'ootaa keessatti miseensa ta\'uuf foormii armaan gadii guuti.',
        'spiritual-opening': 'Maqaa Abbaa, Kan Ilmaa, Kan Hafuura Qulqulluu, Waaqa Tokko. Ameen.',
        'spiritual-translation': 'In the Name of the Father, the Son, and the Holy Spirit, One God. Amen.',
        'step-personal': 'Odeeffannoo',
        'step-faith': 'Amantaa',
        'step-review': 'Mirkaneessi',
        'personal-info': '👤 Odeeffannoo Dhuunfaa',
        'first-name': 'Maqaa Jalqabaa',
        'last-name': 'Maqaa Abbaa',
        'gender': 'Saala',
        'select-gender': 'Filadhu...',
        'birth-date': 'Guyyaa Dhalootaa',
        'phone': 'Lakkoofsa Bilbilaa',
        'email': 'Imeelii',
        'address': 'Teessoo (Magaalaa/Aanaa)',
        'faith-info': '✝️ Odeeffannoo Amantaa',
        'current-church': 'Mana Kiristaanaa Ammaa',
        'baptized': 'Cuuphaa Fudhattee?',
        'select-option': 'Filadhu...',
        'service-interest': 'Tajaajila Waldaa Keessatti Barbaaddu',
        'how-heard': 'Waa\'ee Waldichaa Akkamitti Dhagahatte?',
        'additional-notes': 'Yaada Dabalataa',
        'review-info': '✅ Odeeffannoo Mirkaneessi',
        'privacy-title': '🔒 Nageenya Odeeffannoo',
        'privacy-text': 'Odeeffannoon keessan nageenya qabeessa ta\'ee eegama. Waldaa Duuka Bu\'ootaa qofaaf fayyadamama.',
        'next': 'Itti Aanaa',
        'previous': 'Duubatti',
        'review': 'Mirkaneessi',
        'submit': '📋 Galmaa\'i ✓',
        'success-title': '🎉 Baga Galmoofte!',
        'success-message': 'Baga nagaan Gara Waldaa Duuka Bu\'ootaatti dhufte. Galmaa\'inni keessan milkaa\'inaan xumurame.',
        'member-id-title': 'Lakkoofsa Miseensummaa Keessan:',
        'save-id-message': 'Lakkoofsa galmaa\'inaa kana olkaa\'i. Gaaffii yoo qabaatte nu quunnamuu dandeessa.',
        'back-home': '🏠 Mana Deebi\'i',
        'print': '🖨️ Maxxansi',
        'processing': 'Adeemsifamaa jira...'
    },
    en: {
        'registration-title': '👥 Member Registration',
        'back-home': '← Back Home',
        'welcome-title': '👥 Welcome',
        'welcome-subtitle': 'Fill out the form below to become a member of Waldaa Duuka Bu\'ootaa.',
        'spiritual-opening': 'In the Name of the Father, the Son, and the Holy Spirit, One God. Amen.',
        'spiritual-translation': 'Maqaa Abbaa, Kan Ilmaa, Kan Hafuura Qulqulluu, Waaqa Tokko. Ameen.',
        'step-personal': 'Personal',
        'step-faith': 'Faith',
        'step-review': 'Review',
        'personal-info': '👤 Personal Information',
        'first-name': 'First Name',
        'last-name': 'Last Name',
        'gender': 'Gender',
        'select-gender': 'Select...',
        'birth-date': 'Date of Birth',
        'phone': 'Phone Number',
        'email': 'Email',
        'address': 'Address (City/Region)',
        'faith-info': '✝️ Faith Information',
        'current-church': 'Current Church',
        'baptized': 'Are you baptized?',
        'select-option': 'Select...',
        'service-interest': 'Service Interest in the Association',
        'how-heard': 'How did you hear about us?',
        'additional-notes': 'Additional Notes',
        'review-info': '✅ Review Information',
        'privacy-title': '🔒 Privacy & Security',
        'privacy-text': 'Your information is securely protected and used only for Waldaa Duuka Bu\'ootaa purposes.',
        'next': 'Next',
        'previous': 'Previous',
        'review': 'Review',
        'submit': '📋 Submit ✓',
        'success-title': '🎉 Registration Successful!',
        'success-message': 'Welcome to Waldaa Duuka Bu\'ootaa. Your registration has been completed successfully.',
        'member-id-title': 'Your Member ID:',
        'save-id-message': 'Please save this registration ID. Contact us if you have any questions.',
        'back-home': '🏠 Back Home',
        'print': '🖨️ Print',
        'processing': 'Processing...'
    },
    am: {
        'registration-title': '👥 የአባልነት ምዝገባ',
        'back-home': '← ወደ ቤት ተመለስ',
        'welcome-title': '👥 እንኳን ደህና መጡ',
        'welcome-subtitle': 'የዋልዳ ዱካ ቡኦታ አባል ለመሆን ከታች ያለውን ቅጽ ይሙሉ።',
        'spiritual-opening': 'በአብ ስም በወልድ ስም በመንፈስ ቅዱስ ስም አንድ አምላክ። አሜን።',
        'spiritual-translation': 'Maqaa Abbaa, Kan Ilmaa, Kan Hafuura Qulqulluu, Waaqa Tokko. Ameen.',
        'step-personal': 'የግል መረጃ',
        'step-faith': 'እምነት',
        'step-review': 'ግምገማ',
        'personal-info': '👤 የግል መረጃ',
        'first-name': 'የመጀመሪያ ስም',
        'last-name': 'የአባት ስም',
        'gender': 'ጾታ',
        'select-gender': 'ይምረጡ...',
        'birth-date': 'የትውልድ ቀን',
        'phone': 'ስልክ ቁጥር',
        'email': 'ኢሜይል',
        'address': 'አድራሻ (ከተማ/ክልል)',
        'faith-info': '✝️ የእምነት መረጃ',
        'current-church': 'አሁን ያለው ቤተክርስቲያን',
        'baptized': 'ተጠምቀዋል?',
        'select-option': 'ይምረጡ...',
        'service-interest': 'በማህበሩ ውስጥ የአገልግሎት ፍላጎት',
        'how-heard': 'ስለእኛ እንዴት ሰሙ?',
        'additional-notes': 'ተጨማሪ ማስታወሻዎች',
        'review-info': '✅ መረጃ ይገምግሙ',
        'privacy-title': '🔒 ግላዊነት እና ደህንነት',
        'privacy-text': 'መረጃዎ በደህንነት የተጠበቀ ሲሆን ለዋልዳ ዱካ ቡኦታ ዓላማዎች ብቻ ይጠቅማል።',
        'next': 'ቀጣይ',
        'previous': 'ቀዳሚ',
        'review': 'ግምገማ',
        'submit': '📋 ላክ ✓',
        'success-title': '🎉 ምዝገባ ተሳክቷል!',
        'success-message': 'ወደ ዋልዳ ዱካ ቡኦታ እንኳን በደህና መጡ። ምዝገባዎ በተሳካ ሁኔታ ተጠናቅቋል።',
        'member-id-title': 'የአባልነት መለያዎ:',
        'save-id-message': 'እባክዎ ይህንን የምዝገባ መለያ ያስቀምጡ። ጥያቄ ካለዎት እኛን ያነጋግሩን።',
        'back-home': '🏠 ወደ ቤት',
        'print': '🖨️ አትም',
        'processing': 'በሂደት ላይ...'
    },
    ti: {
        'registration-title': '👥 ናይ መልእኽቲ ምዝገባ',
        'back-home': '← ናብ ገዛ ተመለስ',
        'welcome-title': '👥 እንቋዕ ብደሓን መጻእኩም',
        'welcome-subtitle': 'ናይ ዋልዳ ዱካ ቡኦታ መልእኽቲ ንምዃን ኣብ ታሕቲ ዘሎ ቅጥዒ ምልእዎ።',
        'spiritual-opening': 'ብስም ኣቦ ወወልድ ወመንፈስ ቅዱስ ሓደ ኣምላኽ። ኣሜን።',
        'spiritual-translation': 'Maqaa Abbaa, Kan Ilmaa, Kan Hafuura Qulqulluu, Waaqa Tokko. Ameen.',
        'step-personal': 'ውልቃዊ',
        'step-faith': 'እምነት',
        'step-review': 'ግምገማ',
        'personal-info': '👤 ውልቃዊ ሓበሬታ',
        'first-name': 'ቀዳማይ ስም',
        'last-name': 'ናይ ኣቦ ስም',
        'gender': 'ጾታ',
        'select-gender': 'ምረጹ...',
        'birth-date': 'ዕለት ልደት',
        'phone': 'ቁጽሪ ተሌፎን',
        'email': 'ኢመይል',
        'address': 'ኣድራሻ (ከተማ/ክልል)',
        'faith-info': '✝️ ናይ እምነት ሓበሬታ',
        'current-church': 'ሕጂ ዘሎ ቤተክርስቲያን',
        'baptized': 'ተጠሚቕኩም ዲኹም?',
        'select-option': 'ምረጹ...',
        'service-interest': 'ኣብ ማሕበር ናይ ኣገልግሎት ድሌት',
        'how-heard': 'ብኸመይ ሰሚዕኩምና?',
        'additional-notes': 'ተወሳኺ መዘኻኸሪ',
        'review-info': '✅ ሓበሬታ ግምገሙ',
        'privacy-title': '🔒 ፕራይቫሲን ድሕንነትን',
        'privacy-text': 'ሓበሬታኹም ብድሕንነት ተሓሊዩ ንዋልዳ ዱካ ቡኦታ ዕላማታት ጥራይ ይጥቀመሉ።',
        'next': 'ቀጻሊ',
        'previous': 'ቀዳሚ',
        'review': 'ግምገማ',
        'submit': '📋 ስደድ ✓',
        'success-title': '🎉 ምዝገባ ተዓወተ!',
        'success-message': 'ናብ ዋልዳ ዱካ ቡኦታ እንቋዕ ብደሓን መጻእኩም። ምዝገባኹም ብዓወት ተዛዚሙ።',
        'member-id-title': 'ናይ መልእኽቲ መለለይኹም:',
        'save-id-message': 'በጃኹም ነዚ ናይ ምዝገባ መለለዪ ኣቐምጥዎ። ሕቶ እንተሃልዩኩም ርኸቡና።',
        'back-home': '🏠 ናብ ገዛ',
        'print': '🖨️ ሕትመት',
        'processing': 'ኣብ መስርሕ...'
    }
};

// ── Shared storage key (must match admin-dashboard.html) ──
const MEMBERS_STORE = 'wdb_member_accounts';

function getMembersFromStorage() {
    return JSON.parse(localStorage.getItem(MEMBERS_STORE) || '[]');
}
function saveMembersToStorage(data) {
    localStorage.setItem(MEMBERS_STORE, JSON.stringify(data));
}

/**
 * Save a new registration directly to localStorage so the admin
 * dashboard can read it immediately — works on static hosting
 * (Cloudflare Pages, GitHub Pages) without a PHP backend.
 */
function saveRegistrationToLocalStorage(formData, memberId) {
    const accounts = getMembersFromStorage();

    // Avoid duplicates by phone
    const exists = accounts.some(a => a.phone === formData.phone);
    if (exists) return;

    accounts.unshift({
        memberId:   memberId,
        firstName:  formData.fname,
        lastName:   formData.lname,
        username:   (formData.fname + '.' + formData.lname).toLowerCase().replace(/\s+/g, '.'),
        phone:      formData.phone,
        email:      formData.email      || '',
        address:    formData.address    || '',
        gender:     formData.gender     || '',
        dob:        formData.dob        || '',
        church:     formData.currentChurch || '',
        baptized:   formData.baptized === 'eeyyee' ? 'Yes' : 'No',
        service:    formData.service    || '',
        howHeard:   formData.howHeard   || '',
        notes:      formData.notes      || '',
        status:     'pending',
        createdAt:  new Date().toISOString().split('T')[0],
        // No password — member must create one via member-login.html Register tab
        password:   ''
    });

    saveMembersToStorage(accounts);
}

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // Initialize AOS animations
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
    }
    
    // Set initial language
    switchLanguage(currentLanguage);
    
    // Initialize form
    initializeForm();
    
    // Set initial step
    setStep(1);
}

function initializeForm() {
    const form = document.getElementById('registrationForm');
    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }
    
    // Add input event listeners for real-time validation
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('blur', validateField);
        input.addEventListener('input', clearFieldError);
    });
}

// Language switching functionality
function switchLanguage(lang) {
    if (!translations[lang]) return;
    
    currentLanguage = lang;
    
    // Update language buttons
    document.querySelectorAll('.language-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.lang === lang) {
            btn.classList.add('active');
        }
    });
    
    // Update document language
    document.documentElement.lang = lang;
    
    // Translate all elements with data-translate attribute
    document.querySelectorAll('[data-translate]').forEach(element => {
        const key = element.getAttribute('data-translate');
        if (translations[lang][key]) {
            if (element.tagName === 'INPUT' && element.type !== 'submit') {
                element.placeholder = translations[lang][key];
            } else {
                element.textContent = translations[lang][key];
            }
        }
    });
}

// Step navigation
function setStep(step) {
    currentStep = step;
    
    // Hide all steps
    document.querySelectorAll('.registration-step').forEach(stepEl => {
        stepEl.classList.remove('active');
    });
    
    // Show current step
    const currentStepEl = document.getElementById(getStepId(step));
    if (currentStepEl) {
        currentStepEl.classList.add('active');
    }
    
    // Update step indicators
    updateStepIndicators(step);
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function getStepId(step) {
    const stepIds = ['', 'personalStep', 'faithStep', 'reviewStep', 'successStep'];
    return stepIds[step] || 'personalStep';
}

function updateStepIndicators(currentStep) {
    for (let i = 1; i <= 3; i++) {
        const stepEl = document.getElementById(`step${i}`);
        if (stepEl) {
            const circle = stepEl.querySelector('.w-12');
            
            stepEl.classList.remove('completed');
            circle.classList.remove('bg-yellow-400', 'text-gray-800', 'bg-green-400');
            circle.classList.add('bg-white', 'bg-opacity-30', 'text-white');
            
            if (i < currentStep) {
                stepEl.classList.add('completed');
                circle.classList.remove('bg-white', 'bg-opacity-30', 'text-white');
                circle.classList.add('bg-green-400', 'text-white');
            } else if (i === currentStep) {
                circle.classList.remove('bg-white', 'bg-opacity-30', 'text-white');
                circle.classList.add('bg-yellow-400', 'text-gray-800');
            }
        }
    }
}

// Navigation functions
function nextStep(step) {
    if (validateCurrentStep()) {
        if (step === 3) {
            buildReviewContent();
        }
        setStep(step);
    }
}

function previousStep(step) {
    setStep(step);
}

// Form validation
function validateCurrentStep() {
    switch (currentStep) {
        case 1:
            return validatePersonalInfo();
        case 2:
            return validateFaithInfo();
        default:
            return true;
    }
}

function validatePersonalInfo() {
    let isValid = true;
    
    const fields = [
        { id: 'fname', validator: val => val.length >= 2, message: 'First name is required (min 2 characters)' },
        { id: 'lname', validator: val => val.length >= 2, message: 'Last name is required (min 2 characters)' },
        { id: 'gender', validator: val => val !== '', message: 'Please select gender' },
        { id: 'phone', validator: validatePhone, message: 'Please enter a valid Ethiopian phone number' },
        { id: 'address', validator: val => val.length >= 3, message: 'Address is required (min 3 characters)' }
    ];
    
    fields.forEach(field => {
        if (!validateField(document.getElementById(field.id), field.validator, field.message)) {
            isValid = false;
        }
    });
    
    // Validate email if provided
    const emailField = document.getElementById('email');
    if (emailField.value.trim() && !validateEmail(emailField.value.trim())) {
        showFieldError(emailField, 'Please enter a valid email address');
        isValid = false;
    }
    
    return isValid;
}

function validateFaithInfo() {
    const baptizedField = document.getElementById('baptized');
    return validateField(baptizedField, val => val !== '', 'Please select baptism status');
}

function validateField(field, validator, message) {
    if (!field) return true;
    
    const value = field.value.trim();
    const isValid = typeof validator === 'function' ? validator(value) : true;
    
    if (!isValid) {
        showFieldError(field, message);
        return false;
    } else {
        clearFieldError(field);
        return true;
    }
}

function validatePhone(phone) {
    // Ethiopian phone number validation
    const phoneRegex = /^(\+251|0)?[79]\d{8}$/;
    return phoneRegex.test(phone.replace(/\s+/g, ''));
}

function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function showFieldError(field, message) {
    field.classList.add('error');
    
    let errorEl = field.parentNode.querySelector('.error-message');
    if (!errorEl) {
        errorEl = document.createElement('div');
        errorEl.className = 'error-message';
        field.parentNode.appendChild(errorEl);
    }
    
    errorEl.textContent = message;
    errorEl.classList.add('show');
}

function clearFieldError(field) {
    if (typeof field === 'object' && field.target) {
        field = field.target;
    }
    
    field.classList.remove('error');
    const errorEl = field.parentNode.querySelector('.error-message');
    if (errorEl) {
        errorEl.classList.remove('show');
    }
}

// Review content builder
function buildReviewContent() {
    const data = collectFormData();
    const reviewContent = document.getElementById('reviewContent');
    
    if (!reviewContent) return;
    
    const reviewHTML = `
        <div class="space-y-4">
            <div class="glass-card p-4 rounded-xl">
                <h4 class="text-white font-semibold mb-3 flex items-center">
                    <i class="fas fa-user text-yellow-400 mr-2"></i>
                    ${translations[currentLanguage]['personal-info']}
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    ${createReviewRow('👤 Full Name', `${data.fname} ${data.lname}`)}
                    ${createReviewRow('⚧ Gender', data.gender)}
                    ${createReviewRow('📅 Date of Birth', data.dob || '—')}
                    ${createReviewRow('📞 Phone', data.phone)}
                    ${createReviewRow('📧 Email', data.email || '—')}
                    ${createReviewRow('📍 Address', data.address)}
                </div>
            </div>
            
            <div class="glass-card p-4 rounded-xl">
                <h4 class="text-white font-semibold mb-3 flex items-center">
                    <i class="fas fa-cross text-yellow-400 mr-2"></i>
                    ${translations[currentLanguage]['faith-info']}
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    ${createReviewRow('⛪ Current Church', data.currentChurch || '—')}
                    ${createReviewRow('✝️ Baptized', data.baptized === 'eeyyee' ? 'Yes' : 'No')}
                    ${createReviewRow('🙏 Service Interest', data.service || '—')}
                    ${createReviewRow('📣 How Heard', data.howHeard || '—')}
                </div>
                ${data.notes ? `<div class="mt-3 pt-3 border-t border-white border-opacity-20">
                    <p class="text-white opacity-90 text-sm"><strong>Notes:</strong> ${data.notes}</p>
                </div>` : ''}
            </div>
        </div>
    `;
    
    reviewContent.innerHTML = reviewHTML;
}

function createReviewRow(label, value) {
    return `
        <div class="flex justify-between items-center py-1">
            <span class="text-white opacity-70">${label}:</span>
            <span class="text-white font-medium">${value}</span>
        </div>
    `;
}

// Form data collection
function collectFormData() {
    return {
        fname: document.getElementById('fname')?.value.trim() || '',
        lname: document.getElementById('lname')?.value.trim() || '',
        gender: document.getElementById('gender')?.value || '',
        dob: document.getElementById('dob')?.value || '',
        phone: document.getElementById('phone')?.value.trim() || '',
        email: document.getElementById('email')?.value.trim() || '',
        address: document.getElementById('address')?.value.trim() || '',
        currentChurch: document.getElementById('currentChurch')?.value.trim() || '',
        baptized: document.getElementById('baptized')?.value || '',
        service: document.getElementById('service')?.value || '',
        howHeard: document.getElementById('howHeard')?.value || '',
        notes: document.getElementById('notes')?.value.trim() || ''
    };
}

// Form submission
async function handleFormSubmit(event) {
    event.preventDefault();
    
    if (isSubmitting) return;
    
    if (!validateCurrentStep()) {
        showNotification('error', 'Validation Error', 'Please fix the errors and try again.');
        return;
    }
    
    isSubmitting = true;
    showLoading(true);
    
    try {
        const formData = collectFormData();
        const response = await submitToAPI(formData);
        
        if (response.success) {
            showSuccessStep(response.member_id);
        } else {
            throw new Error(response.message || 'Registration failed');
        }
    } catch (error) {
        console.error('Registration error:', error);
        showNotification('error', 'Registration Failed', error.message || 'Please try again later.');
    } finally {
        isSubmitting = false;
        showLoading(false);
    }
}

async function submitToAPI(formData) {
    // ── Step 1: Generate a member ID locally ──────────────────────────────
    const accounts  = getMembersFromStorage();
    const year      = new Date().getFullYear();
    const nextNum   = String(accounts.length + 1).padStart(4, '0');
    const memberId  = `WDB-${year}-${nextNum}`;

    // ── Step 2: Save to localStorage immediately (works on static hosting) ─
    saveRegistrationToLocalStorage(formData, memberId);

    // ── Step 3: Try PHP backend (optional — only works with a server) ──────
    try {
        const response = await fetch('api/register.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ ...formData, member_id: memberId }),
            signal:  AbortSignal.timeout(5000)   // 5 s timeout
        });
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.member_id) {
                // If the server returned a different ID, update localStorage
                const stored = getMembersFromStorage();
                const idx = stored.findIndex(a => a.memberId === memberId);
                if (idx > -1) {
                    stored[idx].memberId = data.member_id;
                    saveMembersToStorage(stored);
                }
                return { success: true, member_id: data.member_id };
            }
        }
    } catch (_) {
        // Server not available — that's fine, localStorage already saved
    }

    // ── Step 4: Return success with the locally-generated ID ──────────────
    return { success: true, member_id: memberId };
}

function showSuccessStep(memberId) {
    // Hide all registration steps
    document.querySelectorAll('.registration-step').forEach(step => {
        step.classList.remove('active');
    });
    
    // Show success step
    const successStep = document.getElementById('successStep');
    if (successStep) {
        successStep.classList.add('active');
        
        // Update member ID display
        const memberIdDisplay = document.getElementById('memberIdDisplay');
        if (memberIdDisplay) {
            memberIdDisplay.textContent = memberId;
        }
    }
    
    // Update all step indicators to completed
    for (let i = 1; i <= 3; i++) {
        const stepEl = document.getElementById(`step${i}`);
        if (stepEl) {
            stepEl.classList.add('completed');
            const circle = stepEl.querySelector('.w-12');
            circle.classList.remove('bg-white', 'bg-opacity-30', 'text-white', 'bg-yellow-400', 'text-gray-800');
            circle.classList.add('bg-green-400', 'text-white');
        }
    }
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Show success notification
    showNotification('success', 'Registration Successful!', `Welcome to WDB! Your member ID is: ${memberId}`);
}

// Utility functions
function showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.classList.toggle('hidden', !show);
    }
    
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        if (show) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="loading-spinner mr-2"></div> Processing...';
        } else {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i><span data-translate="submit">📋 Galmaa\'i ✓</span>';
        }
    }
}

function showNotification(type, title, message) {
    const toast = document.getElementById('notificationToast');
    const icon = document.getElementById('toastIcon');
    const titleEl = document.getElementById('toastTitle');
    const messageEl = document.getElementById('toastMessage');
    
    if (!toast || !icon || !titleEl || !messageEl) return;
    
    // Set icon based on type
    icon.innerHTML = type === 'success' ? '<i class="fas fa-check-circle text-green-400"></i>' : 
                     type === 'error' ? '<i class="fas fa-exclamation-circle text-red-400"></i>' : 
                     '<i class="fas fa-info-circle text-blue-400"></i>';
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    
    toast.classList.remove('hidden');
    
    // Auto hide after 5 seconds
    setTimeout(() => {
        hideNotification();
    }, 5000);
}

function hideNotification() {
    const toast = document.getElementById('notificationToast');
    if (toast) {
        toast.classList.add('hidden');
    }
}

function printRegistration() {
    window.print();
}

// Global functions for HTML onclick handlers
window.switchLanguage = switchLanguage;
window.nextStep = nextStep;
window.previousStep = previousStep;
window.printRegistration = printRegistration;
window.hideNotification = hideNotification;