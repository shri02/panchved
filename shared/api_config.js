/**
 * Ocayur Doctor Portal - Shared API Configuration & Session Utilities
 */

(function () {
  const subfolders = ['dashboard', 'patients', 'appointments', 'packages', 'courses', 'profile', 'shared'];
  const isInSubfolder = subfolders.some(folder => window.location.pathname.includes('/' + folder + '/'));

  // Configure Base API path relative to current location
  window.API_BASE_URL = window.API_BASE_URL || (isInSubfolder ? '../api' : 'api');

  // Helper to build API endpoint URLs
  window.getApiPath = function (endpoint) {
    const cleanEndpoint = endpoint.replace(/^\/?(api\/)?/, '');
    return `${window.API_BASE_URL}/${cleanEndpoint}`;
  };

  // Helper to resolve asset URLs
  window.getAssetPath = function (assetPath) {
    if (!assetPath) return '';
    if (assetPath.startsWith('http://') || assetPath.startsWith('https://') || assetPath.startsWith('//') || assetPath.startsWith('data:')) {
      return assetPath;
    }
    const cleanPath = assetPath.replace(/^\/?(\.\.\/)?(assets\/)?/, '');
    return isInSubfolder ? `../assets/${cleanPath}` : `assets/${cleanPath}`;
  };

  // Global Doctor Portal Auth Helper
  window.OcayurAuth = {
    getDoctor: function () {
      try {
        const d = localStorage.getItem('ocayur_doctor') || localStorage.getItem('panchved_doctor');
        return d ? JSON.parse(d) : null;
      } catch (e) {
        return null;
      }
    },
    getUser: function () {
      try {
        const u = localStorage.getItem('ocayur_user') || localStorage.getItem('panchved_user');
        if (!u) return null;
        const parsed = JSON.parse(u);
        const role = (parsed && parsed.role ? String(parsed.role).toLowerCase() : '');
        if (role === 'admin' || role === 'super admin' || role === 'patient') {
          return null;
        }
        return parsed;
      } catch (e) {
        return null;
      }
    },
    isLoggedIn: function () {
      const doc = this.getDoctor();
      if (doc) return true;
      const u = this.getUser();
      return !!(u && (String(u.role).toLowerCase() === 'doctor' || u.doctorid));
    },
    logout: async function () {
      try {
        await fetch(window.getApiPath('logout.php'), { method: 'POST' });
      } catch (_) {}
      localStorage.removeItem('ocayur_doctor');
      localStorage.removeItem('panchved_doctor');
      localStorage.removeItem('ocayur_user');
      localStorage.removeItem('panchved_user');
      sessionStorage.clear();
      window.location.href = isInSubfolder ? '../index.html' : 'index.html';
    },
    showLogoutModal: function () {
      let modalOverlay = document.getElementById('logoutModalOverlay');
      if (!modalOverlay) {
        modalOverlay = document.createElement('div');
        modalOverlay.className = 'logout-modal-overlay';
        modalOverlay.id = 'logoutModalOverlay';
        modalOverlay.setAttribute('aria-hidden', 'true');
        modalOverlay.innerHTML = `
          <div class="logout-modal-dialog" id="logoutModal" role="dialog" aria-modal="true" aria-labelledby="logoutModalTitle">
            <div class="logout-modal-header">
              <div class="logout-modal-icon-wrap" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                  <polyline points="16 17 21 12 16 7"></polyline>
                  <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
              </div>
              <div class="logout-modal-heading-text">
                <h3 class="logout-modal-title" id="logoutModalTitle">Log Out</h3>
                <p class="logout-modal-subtitle">Are you sure you want to log out?</p>
              </div>
              <button type="button" class="logout-modal-close-btn" id="logoutModalCloseBtn" aria-label="Close modal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <line x1="18" y1="6" x2="6" y2="18"></line>
                  <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
              </button>
            </div>
            <div class="logout-modal-body">
              <p class="logout-modal-message">You will need to enter your phone number and password to sign back in to your doctor dashboard.</p>
            </div>
            <div class="logout-modal-footer">
              <button type="button" class="btn-logout-cancel" id="logoutCancelBtn">Cancel</button>
              <button type="button" class="btn-logout-confirm" id="logoutConfirmBtn">Log Out</button>
            </div>
          </div>
        `;
        document.body.appendChild(modalOverlay);

        const closeBtn = modalOverlay.querySelector('#logoutModalCloseBtn');
        const cancelBtn = modalOverlay.querySelector('#logoutCancelBtn');
        const confirmBtn = modalOverlay.querySelector('#logoutConfirmBtn');

        const closeModal = () => {
          modalOverlay.classList.remove('active');
          modalOverlay.setAttribute('aria-hidden', 'true');
        };

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        modalOverlay.addEventListener('click', (e) => {
          if (e.target === modalOverlay) closeModal();
        });
        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape' && modalOverlay.classList.contains('active')) {
            closeModal();
          }
        });

        if (confirmBtn) {
          confirmBtn.addEventListener('click', () => {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Logging out...';
            window.OcayurAuth.logout();
          });
        }
      }

      modalOverlay.classList.add('active');
      modalOverlay.setAttribute('aria-hidden', 'false');
    },
    updateUI: function () {
      const doctor = this.getDoctor() || this.getUser();
      if (!doctor) return;

      const name = doctor.full_name || doctor.name || 'Doctor';
      const role = doctor.expertise || doctor.area || doctor.role || 'Doctor';

      document.querySelectorAll('.doctor-name, #headerDoctorName').forEach(el => {
        el.textContent = name;
      });
      document.querySelectorAll('.doctor-role, #headerDoctorRole').forEach(el => {
        el.textContent = role;
      });
    }
  };

  // Backwards compatibility alias
  window.PanchvedAuth = window.OcayurAuth;

  // Wire up logout handlers globally
  document.addEventListener('DOMContentLoaded', () => {
    window.OcayurAuth.updateUI();

    const logoutBtns = document.querySelectorAll('.logout-btn, #logoutBtn');
    logoutBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        window.OcayurAuth.showLogoutModal();
      });
    });
  });
})();
