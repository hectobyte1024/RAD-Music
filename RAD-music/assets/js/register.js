/**
 * RAD Music - Registration Page Controller
 */

document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('register-form');
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm-password');
    const errorDisplay = document.getElementById('register-error');

    // Initialize form validation
    initFormValidation();

    // Handle form submission
    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }

        try {
            showLoadingState(true);
            const response = await submitRegistrationForm();
            
            if (response.success) {
                handleSuccessfulRegistration(response);
            } else {
                showFormError(response.message || 'Registration failed. Please try again.');
            }
        } catch (error) {
            console.error('Registration error:', error);
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
        usernameInput.addEventListener('input', validateUsername);
        emailInput.addEventListener('input', validateEmail);
        passwordInput.addEventListener('input', validatePassword);
        confirmPasswordInput.addEventListener('input', validateConfirmPassword);
    }

    /**
     * Validate entire form
     */
    function validateForm() {
        const isUsernameValid = validateUsername();
        const isEmailValid = validateEmail();
        const isPasswordValid = validatePassword();
        const isConfirmPasswordValid = validateConfirmPassword();
        
        return isUsernameValid && isEmailValid && isPasswordValid && isConfirmPasswordValid;
    }

    /**
     * Validate username field
     */
    function validateUsername() {
        const username = usernameInput.value.trim();
        const isValid = username.length >= 3 && /^[a-zA-Z0-9_]+$/.test(username);
        
        if (!isValid && username.length > 0) {
            setFieldError(usernameInput, 'Username must be at least 3 characters (letters, numbers, underscores)');
            return false;
        } else {
            clearFieldError(usernameInput);
            return true;
        }
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
     * Validate password confirmation
     */
    function validateConfirmPassword() {
        const password = passwordInput.value.trim();
        const confirmPassword = confirmPasswordInput.value.trim();
        const isValid = confirmPassword === password;
        
        if (!isValid && confirmPassword.length > 0) {
            setFieldError(confirmPasswordInput, 'Passwords do not match');
            return false;
        } else {
            clearFieldError(confirmPasswordInput);
            return true;
        }
    }

    /**
     * Submit registration form to server
     */
    async function submitRegistrationForm() {
        const formData = {
            username: usernameInput.value.trim(),
            email: emailInput.value.trim(),
            password: passwordInput.value.trim(),
            confirm_password: confirmPasswordInput.value.trim()
        };

        try {
            const response = await fetch('/api/auth/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': getCSRFToken()
                },
                body: JSON.stringify(formData)
            });

            return await response.json();
        } catch (error) {
            throw error;
        }
    }

    /**
     * Handle successful registration
     */
    function handleSuccessfulRegistration(response) {
        // Show success message
        errorDisplay.style.display = 'none';
        
        // Create success message element if it doesn't exist
        let successDisplay = document.getElementById('register-success');
        if (!successDisplay) {
            successDisplay = document.createElement('div');
            successDisplay.id = 'register-success';
            successDisplay.className = 'alert alert-success mt-3';
            registerForm.parentNode.insertBefore(successDisplay, registerForm.nextSibling);
        }
        
        successDisplay.innerHTML = `
            <strong>Success!</strong> ${response.message || 'Account created successfully.'}
            <div class="mt-2">Redirecting to login page...</div>
        `;
        
        // Disable form and redirect
        registerForm.querySelector('button').disabled = true;
        setTimeout(() => {
            window.location.href = '/login';
        }, 3000);
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
        const submitButton = registerForm.querySelector('button[type="submit"]');
        
        if (isLoading) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating account...';
        } else {
            submitButton.disabled = false;
            submitButton.textContent = 'Create Account';
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

    /**
     * Password strength indicator (optional enhancement)
     */
    function initPasswordStrengthMeter() {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength++;
            // Contains numbers
            if (/\d/.test(password)) strength++;
            // Contains special chars
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
            // Contains mixed case
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            
            // Visual feedback could be added here
        });
    }
});