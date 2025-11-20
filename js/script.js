// Admin Panel JavaScript functionality

document.addEventListener("DOMContentLoaded", function () {
    // Initialize all functionality
    initializeFormValidation();
    initializeFileUpload();
    initializeDeleteConfirmations();
    initializeNavigation();
    initializeResponsiveFeatures();
});

// Form validation
function initializeFormValidation() {
    const forms = document.querySelectorAll("form");

    forms.forEach((form) => {
        form.addEventListener("submit", function (e) {
            if (!validateForm(this)) {
                e.preventDefault();
                return false;
            }

            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "Processing...";
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll("[required]");

    requiredFields.forEach((field) => {
        if (!field.value.trim()) {
            showFieldError(field, "This field is required");
            isValid = false;
        } else {
            clearFieldError(field);
        }
    });

    // Special validation for file uploads
    const fileInput = form.querySelector('input[type="file"]');
    if (fileInput && fileInput.files.length > 0) {
        const file = fileInput.files[0];
        const maxSize = 10 * 1024 * 1024; // 10MB
        const allowedTypes = [
            "text/plain",
            "application/pdf",
            "application/msword",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
            "application/vnd.ms-excel",
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        ];

        if (file.size > maxSize) {
            showFieldError(fileInput, "File size must be less than 10MB");
            isValid = false;
        } else if (
            !allowedTypes.includes(file.type) &&
            !file.name.match(/\.(txt|pdf|doc|docx|xls|xlsx)$/i)
        ) {
            showFieldError(
                fileInput,
                "Please select a valid file type (TXT, PDF, DOC, DOCX, XLS, XLSX)"
            );
            isValid = false;
        } else {
            clearFieldError(fileInput);
        }
    }

    // Special validation for URLs
    const urlInputs = form.querySelectorAll('input[type="url"]');
    urlInputs.forEach((input) => {
        if (input.value && !isValidUrl(input.value)) {
            showFieldError(input, "Please enter a valid URL");
            isValid = false;
        } else {
            clearFieldError(input);
        }
    });

    return isValid;
}

function showFieldError(field, message) {
    clearFieldError(field);

    const errorDiv = document.createElement("div");
    errorDiv.className = "field-error";
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        color: #e53e3e;
        font-size: 0.875rem;
        margin-top: 0.25rem;
        font-weight: 500;
    `;

    field.style.borderColor = "#e53e3e";
    field.style.boxShadow = "0 0 0 3px rgba(229, 62, 62, 0.1)";

    field.parentNode.appendChild(errorDiv);
}

function clearFieldError(field) {
    field.style.borderColor = "";
    field.style.boxShadow = "";

    const existingError = field.parentNode.querySelector(".field-error");
    if (existingError) {
        existingError.remove();
    }
}

function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

// File upload functionality
function initializeFileUpload() {
    const fileInputs = document.querySelectorAll('input[type="file"]');

    fileInputs.forEach((input) => {
        input.addEventListener("change", function (e) {
            handleFileSelection(e.target);
        });
    });
}

function handleFileSelection(input) {
    const file = input.files[0];
    if (!file) return;

    // Update file info display
    const fileInfo = getOrCreateFileInfo(input);
    fileInfo.innerHTML = `
        <div style="margin-top: 0.5rem; padding: 0.5rem; background: #f7fafc; border-radius: 6px; border: 1px solid #e2e8f0;">
            <strong>${file.name}</strong><br>
            <small style="color: #718096;">
                Size: ${(file.size / 1024 / 1024).toFixed(2)} MB •
                Type: ${file.type || "Unknown"}
            </small>
        </div>
    `;
}

function getOrCreateFileInfo(input) {
    let fileInfo = input.parentNode.querySelector(".file-info");
    if (!fileInfo) {
        fileInfo = document.createElement("div");
        fileInfo.className = "file-info";
        input.parentNode.appendChild(fileInfo);
    }
    return fileInfo;
}

// Delete confirmations
function initializeDeleteConfirmations() {
    const deleteLinks = document.querySelectorAll('a[href*="delete"]');

    deleteLinks.forEach((link) => {
        link.addEventListener("click", function (e) {
            const confirmed = confirm(
                "Are you sure you want to delete this document? This action cannot be undone."
            );
            if (!confirmed) {
                e.preventDefault();
                return false;
            }

            // Show loading state
            this.textContent = "Deleting...";
            this.style.opacity = "0.6";
        });
    });
}

// Navigation functionality
function initializeNavigation() {
    // Highlight active navigation item
    const currentPath = window.location.pathname.split("/").pop();
    const navLinks = document.querySelectorAll("nav a");

    navLinks.forEach((link) => {
        const linkHref = link.getAttribute("href");
        if (
            linkHref === currentPath ||
            (currentPath === "" && linkHref === "index.php")
        ) {
            link.classList.add("active");
        }
    });

    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
        anchor.addEventListener("click", function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute("href"));
            if (target) {
                target.scrollIntoView({
                    behavior: "smooth",
                    block: "start",
                });
            }
        });
    });
}

// Responsive features
function initializeResponsiveFeatures() {
    // Mobile menu toggle (if needed in future)
    // For now, just handle window resize events
    window.addEventListener("resize", handleResize);
    handleResize(); // Initial call
}

function handleResize() {
    const width = window.innerWidth;

    // Adjust table display on mobile
    const tables = document.querySelectorAll(".documents-table");
    tables.forEach((table) => {
        if (width < 768) {
            table.classList.add("mobile-table");
        } else {
            table.classList.remove("mobile-table");
        }
    });
}

// Auto-hide messages after 5 seconds
function initializeMessageAutoHide() {
    const messages = document.querySelectorAll(".success, .error");

    messages.forEach((message) => {
        setTimeout(() => {
            message.style.transition = "opacity 0.5s ease";
            message.style.opacity = "0";
            setTimeout(() => {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 500);
        }, 5000);
    });
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function () {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}

// Form enhancement - add loading states
function enhanceForms() {
    const forms = document.querySelectorAll("form");

    forms.forEach((form) => {
        form.addEventListener("submit", function () {
            // Add loading class to form
            form.classList.add("submitting");
        });
    });
}

// Initialize message auto-hide
initializeMessageAutoHide();

// Add form enhancement
enhanceForms();

// Add keyboard navigation support
document.addEventListener("keydown", function (e) {
    // ESC key to close modals (if any)
    if (e.key === "Escape") {
        // Close any open modals or clear focus
        const focusedElement = document.activeElement;
        if (focusedElement && focusedElement.blur) {
            focusedElement.blur();
        }
    }
});

// Add touch support for mobile devices
if ("ontouchstart" in window) {
    document.body.classList.add("touch-device");
}

// Performance optimization - lazy load images if any
function lazyLoadImages() {
    const images = document.querySelectorAll("img[data-src]");

    if ("IntersectionObserver" in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove("lazy");
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach((img) => imageObserver.observe(img));
    } else {
        // Fallback for browsers without IntersectionObserver
        images.forEach((img) => {
            img.src = img.dataset.src;
        });
    }
}

// Initialize lazy loading
lazyLoadImages();

// Add accessibility improvements
function improveAccessibility() {
    // Add ARIA labels where needed
    const forms = document.querySelectorAll("form");
    forms.forEach((form) => {
        if (
            !form.getAttribute("aria-label") &&
            !form.getAttribute("aria-labelledby")
        ) {
            const heading = form.previousElementSibling;
            if (heading && heading.tagName === "H2") {
                form.setAttribute(
                    "aria-labelledby",
                    heading.id ||
                        (heading.id =
                            "form-heading-" +
                            Math.random().toString(36).substr(2, 9))
                );
            }
        }
    });

    // Improve button accessibility
    const buttons = document.querySelectorAll(".btn");
    buttons.forEach((btn) => {
        if (!btn.getAttribute("aria-label") && btn.textContent.trim() === "") {
            btn.setAttribute("aria-label", "Button");
        }
    });
}

// Initialize accessibility improvements
improveAccessibility();

// Export functions for potential use in other scripts
window.AdminPanel = {
    validateForm,
    showFieldError,
    clearFieldError,
    initializeFormValidation,
    initializeFileUpload,
    initializeDeleteConfirmations,
};
