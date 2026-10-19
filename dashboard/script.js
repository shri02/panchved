/**
 * Ocayur Doctor Portal - Interactive Logic
 */

// ==========================================
// Global Toast / Snackbar Utility
// ==========================================
let toastTimer = null;
function showToast(message, type = 'info') {
  let toast = document.getElementById('toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    document.body.appendChild(toast);
  }

  clearTimeout(toastTimer);
  toast.textContent = message;
  toast.className = `toast show toast-${type}`;

  toastTimer = setTimeout(() => {
    toast.classList.remove('show');
  }, 3500);
}
window.showToast = showToast;

document.addEventListener('DOMContentLoaded', () => {
  // Page Loader Overlay Logic
  const pageLoader = document.getElementById('pageLoader');
  const hidePageLoader = () => {
    if (!pageLoader) return;
    pageLoader.classList.add('hidden');
    setTimeout(() => {
      pageLoader.style.display = 'none';
    }, 450);
  };
  setTimeout(hidePageLoader, 350);

  // Elements
  const loginForm = document.getElementById('loginForm');
  const phoneInput = document.getElementById('phoneNumber');
  const phoneContainer = document.getElementById('phoneContainer');
  const phoneError = document.getElementById('phoneError');
  
  const passwordInput = document.getElementById('password');
  const passwordContainer = document.getElementById('passwordContainer');
  const passwordError = document.getElementById('passwordError');
  const togglePasswordBtn = document.getElementById('togglePasswordBtn');
  const eyeOffIcon = togglePasswordBtn.querySelector('.eye-off-icon');
  const eyeOnIcon = togglePasswordBtn.querySelector('.eye-on-icon');
  
  const countrySelector = document.getElementById('countrySelector');
  const countryList = document.getElementById('countryList');
  const selectedCountryCode = document.getElementById('selectedCountryCode');
  
  const forgotPasswordLink = document.getElementById('forgotPasswordLink');
  const termsLink = document.getElementById('termsLink');
  const privacyLink = document.getElementById('privacyLink');
  
  const loginBtn = document.getElementById('loginBtn');
  const btnText = loginBtn.querySelector('.btn-text');
  const btnSpinner = loginBtn.querySelector('.btn-spinner');
  const toast = document.getElementById('toast');

  // ==========================================
  // 1. Password Visibility Toggle
  // ==========================================
  togglePasswordBtn.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';
    
    if (isPassword) {
      passwordInput.type = 'text';
      eyeOffIcon.classList.add('hidden');
      eyeOnIcon.classList.remove('hidden');
      togglePasswordBtn.setAttribute('title', 'Hide password');
      togglePasswordBtn.setAttribute('aria-label', 'Hide password');
    } else {
      passwordInput.type = 'password';
      eyeOffIcon.classList.remove('hidden');
      eyeOnIcon.classList.add('hidden');
      togglePasswordBtn.setAttribute('title', 'Show password');
      togglePasswordBtn.setAttribute('aria-label', 'Show password');
    }
  });

  // ==========================================
  // 2. Country Code Dropdown
  // ==========================================
  countrySelector.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = countrySelector.classList.contains('open');
    if (isOpen) {
      closeCountryDropdown();
    } else {
      openCountryDropdown();
    }
  });

  function openCountryDropdown() {
    countrySelector.classList.add('open');
    countrySelector.setAttribute('aria-expanded', 'true');
  }

  function closeCountryDropdown() {
    countrySelector.classList.remove('open');
    countrySelector.setAttribute('aria-expanded', 'false');
  }

  // Option selection
  countryList.querySelectorAll('li').forEach((item) => {
    item.addEventListener('click', (e) => {
      e.stopPropagation();
      const code = item.getAttribute('data-code');
      selectedCountryCode.textContent = code;
      
      countryList.querySelectorAll('li').forEach(li => li.classList.remove('active'));
      item.classList.add('active');
      
      closeCountryDropdown();
      phoneInput.focus();
    });
  });

  // Close dropdown on outside click
  document.addEventListener('click', () => {
    if (countrySelector.classList.contains('open')) {
      closeCountryDropdown();
    }
  });

  // Keyboard accessibility for country selector
  countrySelector.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      countrySelector.click();
    } else if (e.key === 'Escape') {
      closeCountryDropdown();
    }
  });

  // ==========================================
  // 3. Input Validation & Restrictions
  // ==========================================
  // Only allow numbers in phone input
  phoneInput.addEventListener('input', (e) => {
    phoneInput.value = phoneInput.value.replace(/\D/g, '');
    clearError(phoneContainer, phoneError);
  });

  passwordInput.addEventListener('input', () => {
    clearError(passwordContainer, passwordError);
  });

  function setError(container, errorElement, message) {
    container.classList.add('has-error');
    errorElement.textContent = message;
    errorElement.classList.add('show');
  }

  function clearError(container, errorElement) {
    container.classList.remove('has-error');
    errorElement.textContent = '';
    errorElement.classList.remove('show');
  }

  // ==========================================
  // 4. Feature Coming Soon Handlers (Forgot Password, Terms, Privacy)
  // ==========================================
  const showFeatureComingSoon = (e) => {
    if (e) e.preventDefault();
    showToast('This feature will be available soon.', 'info');
  };

  if (forgotPasswordLink) {
    forgotPasswordLink.addEventListener('click', showFeatureComingSoon);
  }

  if (termsLink) {
    termsLink.addEventListener('click', showFeatureComingSoon);
  }

  if (privacyLink) {
    privacyLink.addEventListener('click', showFeatureComingSoon);
  }

  document.querySelectorAll('.footer-link').forEach((link) => {
    link.addEventListener('click', showFeatureComingSoon);
  });

  // ==========================================
  // 6. Form Submit Handler
  // ==========================================
  loginForm.addEventListener('submit', (e) => {
    e.preventDefault();

    const phone = phoneInput.value.trim();
    const password = passwordInput.value.trim();
    let isValid = true;

    // Validate Phone
    if (!phone) {
      setError(phoneContainer, phoneError, 'Please enter your phone number.');
      isValid = false;
    } else if (phone.length < 10) {
      setError(phoneContainer, phoneError, 'Phone number must be at least 10 digits.');
      isValid = false;
    } else {
      clearError(phoneContainer, phoneError);
    }

    // Validate Password
    if (!password) {
      setError(passwordContainer, passwordError, 'Please enter your password.');
      isValid = false;
    } else if (password.length < 6) {
      setError(passwordContainer, passwordError, 'Password must be at least 6 characters.');
      isValid = false;
    } else {
      clearError(passwordContainer, passwordError);
    }

    if (!isValid) return;

    // Real Backend Login Request
    setLoadingState(true);
    const fullPhone = phone;

    fetch('api/login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        phoneNumber: fullPhone,
        password: password
      })
    })
    .then(async (response) => {
      const data = await response.json().catch(() => null);
      setLoadingState(false);

      if (response.ok && data && data.success) {
        // Save authenticated doctor & user profile to localStorage for seamless cross-page sync
        if (data.doctor) {
          localStorage.setItem('ocayur_doctor', JSON.stringify(data.doctor)); localStorage.setItem('panchved_doctor', JSON.stringify(data.doctor));
        }
        if (data.user) {
          localStorage.setItem('ocayur_user', JSON.stringify(data.user)); localStorage.setItem('panchved_user', JSON.stringify(data.user));
        }

        const doctorName = data.doctor?.full_name || data.user?.full_name || 'Doctor';
        showToast(`Welcome back, ${doctorName}! Redirecting to Dashboard...`, 'success');
        
        setTimeout(() => {
          window.location.href = 'dashboard/dashboard.html';
        }, 800);
      } else {
        const errorMsg = data?.message || 'Invalid credentials. Please try again.';
        setError(passwordContainer, passwordError, errorMsg);
        showToast(errorMsg, 'error');
      }
    })
    .catch((err) => {
      setLoadingState(false);
      console.error('Login network/server error:', err);
      showToast('Unable to connect to login server. Please check your network.', 'error');
    });
  });

  function setLoadingState(isLoading) {
    if (isLoading) {
      loginBtn.disabled = true;
      loginBtn.classList.add('is-loading');
      btnText.textContent = 'Logging in...';
      btnSpinner.classList.remove('hidden');
    } else {
      loginBtn.disabled = false;
      loginBtn.classList.remove('is-loading');
      btnText.textContent = 'Login';
      btnSpinner.classList.add('hidden');
    }
  }
});
