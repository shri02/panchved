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
  // 4. Feature Coming Soon Handlers (Terms, Privacy)
  // ==========================================
  const showFeatureComingSoon = (e) => {
    if (e) e.preventDefault();
    showToast('This feature will be available soon.', 'info');
  };

  if (termsLink) {
    termsLink.addEventListener('click', showFeatureComingSoon);
  }

  if (privacyLink) {
    privacyLink.addEventListener('click', showFeatureComingSoon);
  }

  document.querySelectorAll('.footer-link:not(#forgotPasswordLink)').forEach((link) => {
    if (link !== termsLink && link !== privacyLink) {
      link.addEventListener('click', showFeatureComingSoon);
    }
  });

  // ==========================================
  // 5. Forgot Password & OTP Flow
  // ==========================================
  const fpModal = document.getElementById('forgotPasswordModal');
  const fpCloseBtn = document.getElementById('fpCloseBtn');
  const fpStepRequest = document.getElementById('fpStepRequest');
  const fpStepVerify = document.getElementById('fpStepVerify');
  const fpStepSuccess = document.getElementById('fpStepSuccess');
  
  const fpRequestForm = document.getElementById('fpRequestForm');
  const fpIdentifier = document.getElementById('fpIdentifier');
  const fpSendOtpBtn = document.getElementById('fpSendOtpBtn');
  const fpRequestError = document.getElementById('fpRequestError');
  const fpRequestErrorText = document.getElementById('fpRequestErrorText');

  const fpResetForm = document.getElementById('fpResetForm');
  const fpMaskedEmailText = document.getElementById('fpMaskedEmailText');
  const fpOtpCode = document.getElementById('fpOtpCode');
  const fpNewPassword = document.getElementById('fpNewPassword');
  const fpConfirmPassword = document.getElementById('fpConfirmPassword');
  const fpSubmitResetBtn = document.getElementById('fpSubmitResetBtn');
  const fpResetError = document.getElementById('fpResetError');
  const fpResetErrorText = document.getElementById('fpResetErrorText');

  const fpToggleNewPw = document.getElementById('fpToggleNewPw');
  const fpToggleConfirmPw = document.getElementById('fpToggleConfirmPw');
  const fpBackToRequestBtn = document.getElementById('fpBackToRequestBtn');
  const fpResendOtpBtn = document.getElementById('fpResendOtpBtn');
  const fpResendCountdown = document.getElementById('fpResendCountdown');
  const fpTimerCount = document.getElementById('fpTimerCount');
  const fpSuccessDoneBtn = document.getElementById('fpSuccessDoneBtn');

  let fpCurrentEmail = '';
  let fpCurrentIdentifier = '';
  let fpResendInterval = null;

  const openFpModal = (e) => {
    if (e) e.preventDefault();
    // Pre-fill phone if user already entered in login form
    if (phoneInput && phoneInput.value.trim() && fpIdentifier) {
      fpIdentifier.value = phoneInput.value.trim();
    }
    showFpStep('request');
    hideFpError(fpRequestError);
    hideFpError(fpResetError);
    if (fpModal) {
      fpModal.classList.add('open');
      setTimeout(() => {
        if (fpIdentifier) fpIdentifier.focus();
      }, 150);
    }
  };

  const closeFpModal = () => {
    if (fpModal) fpModal.classList.remove('open');
    clearInterval(fpResendInterval);
  };

  const showFpStep = (step) => {
    if (fpStepRequest) fpStepRequest.style.display = (step === 'request') ? 'block' : 'none';
    if (fpStepVerify) fpStepVerify.style.display = (step === 'verify') ? 'block' : 'none';
    if (fpStepSuccess) fpStepSuccess.style.display = (step === 'success') ? 'block' : 'none';
  };

  const showFpError = (boxEl, textEl, message) => {
    if (textEl) textEl.textContent = message;
    if (boxEl) boxEl.classList.add('show');
  };

  const hideFpError = (boxEl) => {
    if (boxEl) boxEl.classList.remove('show');
  };

  const startResendTimer = (seconds = 60) => {
    clearInterval(fpResendInterval);
    if (!fpResendOtpBtn || !fpResendCountdown || !fpTimerCount) return;

    fpResendOtpBtn.style.display = 'none';
    fpResendCountdown.style.display = 'inline';
    fpTimerCount.textContent = seconds;

    let remaining = seconds;
    fpResendInterval = setInterval(() => {
      remaining -= 1;
      if (remaining <= 0) {
        clearInterval(fpResendInterval);
        fpResendCountdown.style.display = 'none';
        fpResendOtpBtn.style.display = 'inline';
      } else {
        fpTimerCount.textContent = remaining;
      }
    }, 1000);
  };

  if (forgotPasswordLink) {
    forgotPasswordLink.addEventListener('click', openFpModal);
  }
  if (fpCloseBtn) {
    fpCloseBtn.addEventListener('click', closeFpModal);
  }
  if (fpModal) {
    fpModal.addEventListener('click', (e) => {
      if (e.target === fpModal) closeFpModal();
    });
  }
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && fpModal && fpModal.classList.contains('open')) {
      closeFpModal();
    }
  });

  // Toggle password visibility in modal
  const setupToggle = (btn, input) => {
    if (!btn || !input) return;
    btn.addEventListener('click', () => {
      const isPass = input.type === 'password';
      input.type = isPass ? 'text' : 'password';
      btn.style.color = isPass ? '#723f1e' : '#8e877b';
    });
  };
  setupToggle(fpToggleNewPw, fpNewPassword);
  setupToggle(fpToggleConfirmPw, fpConfirmPassword);

  // Step 1: Send OTP
  if (fpRequestForm) {
    fpRequestForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      hideFpError(fpRequestError);
      const identifierVal = fpIdentifier.value.trim();
      if (!identifierVal) {
        showFpError(fpRequestError, fpRequestErrorText, 'Please enter your registered phone number, Doctor ID, or email.');
        return;
      }

      fpCurrentIdentifier = identifierVal;
      const btnTextEl = fpSendOtpBtn.querySelector('.btn-text');
      const btnSpinnerEl = fpSendOtpBtn.querySelector('.btn-spinner');
      if (btnTextEl) btnTextEl.textContent = 'Sending OTP...';
      if (btnSpinnerEl) btnSpinnerEl.classList.remove('hidden');
      fpSendOtpBtn.disabled = true;

      try {
        const res = await fetch('api/forgot_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'send_otp', identifier: identifierVal })
        });
        const data = await res.json().catch(() => ({ success: false, message: 'Server response error.' }));

        if (res.ok && data.success) {
          fpCurrentEmail = data.email || '';
          if (fpMaskedEmailText) fpMaskedEmailText.textContent = data.masked_email || fpCurrentEmail;
          showFpStep('verify');
          startResendTimer(60);
          showToast('OTP sent successfully to your registered email.', 'info');
          if (fpOtpCode) {
            fpOtpCode.value = '';
            setTimeout(() => fpOtpCode.focus(), 150);
          }
        } else {
          // If doctor doesn't have an email or has a flawed email, shows error to contact admin
          showFpError(fpRequestError, fpRequestErrorText, data.message || 'Unable to send OTP. Please try again.');
        }
      } catch (err) {
        showFpError(fpRequestError, fpRequestErrorText, 'Network error. Please check your connection and try again.');
      } finally {
        if (btnTextEl) btnTextEl.textContent = 'Send Verification Code';
        if (btnSpinnerEl) btnSpinnerEl.classList.add('hidden');
        fpSendOtpBtn.disabled = false;
      }
    });
  }

  // Resend OTP
  if (fpResendOtpBtn) {
    fpResendOtpBtn.addEventListener('click', async (e) => {
      e.preventDefault();
      if (!fpCurrentIdentifier) return;
      hideFpError(fpResetError);
      fpResendOtpBtn.textContent = 'Sending...';

      try {
        const res = await fetch('api/forgot_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'send_otp', identifier: fpCurrentIdentifier })
        });
        const data = await res.json().catch(() => null);
        if (res.ok && data && data.success) {
          showToast('A new OTP code has been sent to your email.', 'info');
          startResendTimer(60);
        } else {
          showFpError(fpResetError, fpResetErrorText, (data && data.message) || 'Failed to resend OTP.');
        }
      } catch (err) {
        showFpError(fpResetError, fpResetErrorText, 'Network error while resending OTP.');
      } finally {
        fpResendOtpBtn.textContent = 'Resend code';
      }
    });
  }

  if (fpBackToRequestBtn) {
    fpBackToRequestBtn.addEventListener('click', (e) => {
      e.preventDefault();
      showFpStep('request');
      hideFpError(fpResetError);
    });
  }

  // Step 2: Reset Password Form
  if (fpResetForm) {
    fpResetForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      hideFpError(fpResetError);

      const otp = fpOtpCode.value.trim();
      const newPw = fpNewPassword.value.trim();
      const confirmPw = fpConfirmPassword.value.trim();

      if (!otp || otp.length !== 6) {
        showFpError(fpResetError, fpResetErrorText, 'Please enter the 6-digit OTP code sent to your email.');
        return;
      }

      if (newPw.length < 6) {
        showFpError(fpResetError, fpResetErrorText, 'Password must be at least 6 characters long.');
        return;
      }

      if (newPw !== confirmPw) {
        showFpError(fpResetError, fpResetErrorText, 'Passwords do not match. Please re-enter.');
        return;
      }

      const btnTextEl = fpSubmitResetBtn.querySelector('.btn-text');
      const btnSpinnerEl = fpSubmitResetBtn.querySelector('.btn-spinner');
      if (btnTextEl) btnTextEl.textContent = 'Resetting Password...';
      if (btnSpinnerEl) btnSpinnerEl.classList.remove('hidden');
      fpSubmitResetBtn.disabled = true;

      try {
        const res = await fetch('api/forgot_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'reset_password',
            email: fpCurrentEmail,
            otp: otp,
            new_password: newPw,
            confirm_password: confirmPw
          })
        });
        const data = await res.json().catch(() => ({ success: false, message: 'Server response error.' }));

        if (res.ok && data.success) {
          showFpStep('success');
          showToast('Password reset successfully!', 'success');
        } else {
          showFpError(fpResetError, fpResetErrorText, data.message || 'Unable to reset password. Please check the OTP.');
        }
      } catch (err) {
        showFpError(fpResetError, fpResetErrorText, 'Network error. Please try again.');
      } finally {
        if (btnTextEl) btnTextEl.textContent = 'Reset Password';
        if (btnSpinnerEl) btnSpinnerEl.classList.add('hidden');
        fpSubmitResetBtn.disabled = false;
      }
    });
  }

  if (fpSuccessDoneBtn) {
    fpSuccessDoneBtn.addEventListener('click', () => {
      closeFpModal();
      if (passwordInput) {
        passwordInput.value = '';
        passwordInput.focus();
      }
    });
  }

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
