/**
 * Input validation utilities for WPMZF plugin
 *
 * @package WPMZF
 * @subpackage Assets/JS
 */

(function() {
    'use strict';

    // Global validation object
    window.WPMZFValidator = {
        
        /**
         * Validate email address
         */
        isValidEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        /**
         * Validate phone number (basic validation)
         */
        isValidPhone: function(phone) {
            const cleaned = phone.replace(/[^0-9+\-\s]/g, '');
            return cleaned.length >= 9;
        },

        /**
         * Validate URL
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Validate required field
         */
        isRequired: function(value) {
            return value && value.trim().length > 0;
        },

        /**
         * Validate minimum length
         */
        minLength: function(value, min) {
            return value && value.trim().length >= min;
        },

        /**
         * Validate maximum length
         */
        maxLength: function(value, max) {
            return !value || value.length <= max;
        },

        /**
         * Validate file size
         */
        isValidFileSize: function(file, maxSizeMB = 50) {
            const maxSizeBytes = maxSizeMB * 1024 * 1024;
            return file.size <= maxSizeBytes;
        },

        /**
         * Validate file type
         */
        isValidFileType: function(file, allowedTypes) {
            const fileExtension = file.name.split('.').pop().toLowerCase();
            return allowedTypes.includes(fileExtension);
        },

        /**
         * Show validation error message
         */
        showError: function(input, message) {
            this.clearError(input);
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'wpmzf-validation-error';
            errorDiv.textContent = message;
            errorDiv.style.color = '#dc3232';
            errorDiv.style.fontSize = '13px';
            errorDiv.style.marginTop = '5px';
            
            input.style.borderColor = '#dc3232';
            input.parentNode.appendChild(errorDiv);
        },

        /**
         * Clear validation error
         */
        clearError: function(input) {
            const existingError = input.parentNode.querySelector('.wpmzf-validation-error');
            if (existingError) {
                existingError.remove();
            }
            input.style.borderColor = '';
        },

        /**
         * Validate form
         */
        validateForm: function(form, rules) {
            let isValid = true;
            
            for (const fieldName in rules) {
                const field = form.querySelector(`[name="${fieldName}"]`);
                if (!field) continue;
                
                const value = field.value;
                const fieldRules = rules[fieldName];
                
                this.clearError(field);
                
                for (const rule of fieldRules) {
                    let ruleResult = true;
                    let errorMessage = '';
                    
                    switch (rule.type) {
                        case 'required':
                            ruleResult = this.isRequired(value);
                            errorMessage = rule.message || 'To pole jest wymagane';
                            break;
                        case 'email':
                            if (value) {
                                ruleResult = this.isValidEmail(value);
                                errorMessage = rule.message || 'Nieprawidłowy adres email';
                            }
                            break;
                        case 'phone':
                            if (value) {
                                ruleResult = this.isValidPhone(value);
                                errorMessage = rule.message || 'Nieprawidłowy numer telefonu';
                            }
                            break;
                        case 'url':
                            if (value) {
                                ruleResult = this.isValidUrl(value);
                                errorMessage = rule.message || 'Nieprawidłowy adres URL';
                            }
                            break;
                        case 'minLength':
                            if (value) {
                                ruleResult = this.minLength(value, rule.value);
                                errorMessage = rule.message || `Minimum ${rule.value} znaków`;
                            }
                            break;
                        case 'maxLength':
                            ruleResult = this.maxLength(value, rule.value);
                            errorMessage = rule.message || `Maksimum ${rule.value} znaków`;
                            break;
                    }
                    
                    if (!ruleResult) {
                        this.showError(field, errorMessage);
                        isValid = false;
                        break;
                    }
                }
            }
            
            return isValid;
        },

        /**
         * Sanitize HTML to prevent XSS
         */
        sanitizeHtml: function(str) {
            const temp = document.createElement('div');
            temp.textContent = str;
            return temp.innerHTML;
        },

        /**
         * Escape HTML entities
         */
        escapeHtml: function(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    // Auto-validate forms with data-wpmzf-validate attribute
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('[data-wpmzf-validate]');
        
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const rules = JSON.parse(form.getAttribute('data-wpmzf-validate') || '{}');
                
                if (!WPMZFValidator.validateForm(form, rules)) {
                    e.preventDefault();
                    
                    // Focus first error field
                    const firstError = form.querySelector('.wpmzf-validation-error');
                    if (firstError) {
                        const errorField = firstError.previousElementSibling;
                        if (errorField) {
                            errorField.focus();
                        }
                    }
                }
            });
        });
    });

})();
