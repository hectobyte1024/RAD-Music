/**
 * RAD Music - Login Page Controller
 */

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const errorDisplay = document.getElementById('login-error');

    // Initialize form validation
    initFormValidation();

    // Handle form submission
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }

        try {
            showLoadingState(true);
            const response = await submitLoginForm();
            
            if (response.success) {
                handleSuccessfulLogin(response);
            } else {
                showFormError(response.message || 'Login failed. Please try again.');
            }
        } catch (error) {
            console.error('Login error:', error);
            showFormError('Network error. Please check your connection.');
        } finally {
            showLoadingState(false);
        }
    });

    /**
     * Initialize form validation
     */
    function initFormValidation() {
        // Real-time validation
        emailInput.addEventListener('input', validateEmail);
        passwordInput.addEventListener('input', validatePassword);
    }

    /**
     * Validate entire form
     */
    function validateForm() {
        const isEmailValid = validateEmail();
        const isPasswordValid = validatePassword();
        
        return isEmailValid && isPasswordValid;
    }

    /**
     * Validate email field
     */
    function validateEmail() {
        const email = emailInput.value.trim();
        const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        
        if (!isValid && email.length > 0) {
            setFieldError(emailInput, 'Please enter a valid email address');
            return false;
        } else {
            clearFieldError(emailInput);
            return true;
        }
    }

    /**
     * Validate password field
     */
    function validatePassword() {
        const password = passwordInput.value.trim();
        const isValid = password.length >= 8;
        
        if (!isValid && password.length > 0) {
            setFieldError(passwordInput, 'Password must be at least 8 characters');
            return false;
        } else {
            clearFieldError(passwordInput);
            return true;
        }
    }

    /**
     * Submit login form to server
     */
    async function submitLoginForm() {
        const formData = new FormData(loginForm);
        
        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: JSON.stringify({
                    email: formData.get('email'),
                    password: formData.get('password')
                })
            });

            return await response.json();
        } catch (error) {
            throw error;
        }
    }

    /**
     * Handle successful login
     */
    function handleSuccessfulLogin(response) {
        // Store tokens if using JWT
        if (response.token) {
            localStorage.setItem('authToken', response.token);
        }
        
        // Redirect to intended page or home
        const redirectTo = new URLSearchParams(window.location.search).get('redirect') || '/';
        window.location.href = redirectTo;
    }

    /**
     * Display form error message
     */
    function showFormError(message) {
        errorDisplay.textContent = message;
        errorDisplay.style.display = 'block';
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            errorDisplay.style.display = 'none';
        }, 5000);
    }

    /**
     * Set loading state
     */
    function showLoadingState(isLoading) {
        const submitButton = loginForm.querySelector('button[type="submit"]');
        
        if (isLoading) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Signing in...';
        } else {
            submitButton.disabled = false;
            submitButton.textContent = 'Sign In';
        }
    }

    /**
     * Helper to set field error state
     */
    function setFieldError(inputElement, message) {
        const formGroup = inputElement.closest('.form-group');
        let errorElement = formGroup.querySelector('.invalid-feedback');
        
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'invalid-feedback';
            formGroup.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        inputElement.classList.add('is-invalid');
    }

    /**
     * Helper to clear field error state
     */
    function clearFieldError(inputElement) {
        const formGroup = inputElement.closest('.form-group');
        const errorElement = formGroup.querySelector('.invalid-feedback');
        
        if (errorElement) {
            errorElement.remove();
        }
        
        inputElement.classList.remove('is-invalid');
    }

    /**
     * Get CSRF token from meta tag
     */
    function getCSRFToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }
});