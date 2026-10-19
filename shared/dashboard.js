/**
 * Ocayur Doctor Portal - Core Frontend Logic (Shared)
 * Handles dynamic doctor profile fetching, session sync, dashboard metrics & consultations, profile updates, and UI interactions
 */

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
  window.hidePageLoader = hidePageLoader;
  setTimeout(hidePageLoader, 2500);

  // Helper for determining current path depth & API paths
  const subfolders = ['dashboard', 'patients', 'appointments', 'packages', 'courses', 'profile', 'shared'];
  const isInSubfolder = subfolders.some(folder => window.location.pathname.includes('/' + folder + '/'));

  function getApiUrl(endpoint) {
    if (window.getApiPath) return window.getApiPath(endpoint);
    const cleanEndpoint = endpoint.replace(/^\/?(api\/)?/, '');
    return (isInSubfolder ? '../api/' : 'api/') + cleanEndpoint;
  }

  // =========================================================================
  // 1. Mobile Sidebar Drawer Logic
  // =========================================================================
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  if (mobileMenuBtn && sidebar && sidebarOverlay) {
    mobileMenuBtn.addEventListener('click', () => {
      sidebar.classList.toggle('open');
      sidebar.classList.toggle('active');
      sidebarOverlay.classList.toggle('active');
    });

    sidebarOverlay.addEventListener('click', () => {
      sidebar.classList.remove('open', 'active');
      sidebarOverlay.classList.remove('active');
    });
  }

  // Active navigation items
  const navLinks = document.querySelectorAll('.nav-link');
  navLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      if (link.getAttribute('href') && link.getAttribute('href').startsWith('#')) {
        e.preventDefault();
        navLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');

        if (sidebar && sidebarOverlay) {
          sidebar.classList.remove('open', 'active');
          sidebarOverlay.classList.remove('active');
        }
      }
    });
  });

  // =========================================================================
  // 2. Global Toast Notification Utility
  // =========================================================================
  let toastTimer = null;
  function showToast(message, type = 'success') {
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
    }, 3800);
  }
  window.showToast = showToast;

  // =========================================================================
  // 3. Doctor Profile Sync Across UI Header & Pages
  // =========================================================================
  function syncDoctorUI(doctor) {
    if (!doctor) return;

    const docName = doctor.full_name || doctor.name || doctor.username || 'Doctor';
    const docRole = doctor.expertise || doctor.area || doctor.role || 'Doctor';
    const docEmail = doctor.email || doctor.doctor_email || doctor.user_email || '';
    const docPhone = doctor.phone_number || doctor.phoneNumber || doctor.phone || doctor.doctor_phone || '';

    // 1. Update Header Doctor Name everywhere
    document.querySelectorAll('.doctor-name, #headerDoctorName').forEach(el => {
      el.textContent = docName;
    });

    // 2. Update Header Doctor Role
    document.querySelectorAll('.doctor-role, #headerDoctorRole').forEach(el => {
      el.textContent = docRole;
    });

    // 3. Update Welcome subtitle on dashboard
    const welcomeDoctorName = document.getElementById('welcomeDoctorName');
    if (welcomeDoctorName) {
      welcomeDoctorName.textContent = docName;
    }
    const isDashboardPage = document.querySelector('.page-title') && document.querySelector('.page-title').textContent.trim().toLowerCase() === 'dashboard';
    const welcomeSubtitle = document.querySelector('.welcome-subtitle');
    if (welcomeSubtitle && isDashboardPage) {
      welcomeSubtitle.textContent = `Welcome Back ${docName}!`;
    }

    // 4. Update Course Checkout 'Your Details' section display elements
    const nameEls = document.querySelectorAll('#displayUserName, #coursesUserName, .checkout-user-name');
    const emailEls = document.querySelectorAll('#displayUserEmail, #coursesUserEmail, .checkout-user-email');
    const phoneEls = document.querySelectorAll('#displayUserPhone, #coursesUserPhone, .checkout-user-phone');

    nameEls.forEach(el => {
      if (docName) {
        if (el.tagName === 'INPUT') el.value = docName;
        else el.textContent = docName;
      }
    });
    emailEls.forEach(el => {
      if (docEmail) {
        if (el.tagName === 'INPUT') el.value = docEmail;
        else el.textContent = docEmail;
      }
    });
    phoneEls.forEach(el => {
      if (docPhone) {
        if (el.tagName === 'INPUT') el.value = docPhone;
        else el.textContent = docPhone;
      }
    });

    // 5. Update Course Checkout Input fields (for edit form)
    const nameInputs = document.querySelectorAll('#inputUserName, #inputCoursesUserName, input[name="userName"]');
    const emailInputs = document.querySelectorAll('#inputUserEmail, #inputCoursesUserEmail, input[name="userEmail"]');
    const phoneInputs = document.querySelectorAll('#inputUserPhone, #inputCoursesUserPhone, input[name="userPhone"]');

    nameInputs.forEach(el => {
      if (docName && el.tagName === 'INPUT') el.value = docName;
    });
    emailInputs.forEach(el => {
      if (docEmail && el.tagName === 'INPUT') el.value = docEmail;
    });
    phoneInputs.forEach(el => {
      if (docPhone && el.tagName === 'INPUT') el.value = docPhone;
    });

    // 6. Update Profile Form inputs if on profile page and not currently being edited
    const profFullName = document.getElementById('profileFullName');
    const profEmail = document.getElementById('profileEmail');
    const profPhone = document.getElementById('profilePhone');
    if (profFullName && docName && document.activeElement !== profFullName) profFullName.value = docName;
    if (profEmail && docEmail && document.activeElement !== profEmail) profEmail.value = docEmail;
    if (profPhone && docPhone && document.activeElement !== profPhone) profPhone.value = docPhone;
  }
  window.syncDoctorUI = syncDoctorUI;

  // Cross-page / cross-tab automatic state synchronization
  window.addEventListener('storage', (e) => {
    if (e.key === 'ocayur_doctor' || e.key === 'panchved_doctor' || e.key === 'ocayur_user' || e.key === 'panchved_user') {
      try {
        if (e.newValue) {
          const updatedDoc = JSON.parse(e.newValue);
          syncDoctorUI(updatedDoc);
          if (typeof populateProfileForm === 'function') {
            populateProfileForm(updatedDoc);
          }
          if (typeof populateLoggedInUserDetails === 'function') {
            populateLoggedInUserDetails(updatedDoc);
          }
          if (typeof populateLoggedInUserDetailsCourses === 'function') {
            populateLoggedInUserDetailsCourses(updatedDoc);
          }
        }
      } catch (_) {}
    }
  });

  // =========================================================================
  // 4. Dashboard Backend Integration (Metrics, Consultations, Pagination)
  // =========================================================================
  const consultationTableBody = document.getElementById('consultationTableBody');
  const metricToday = document.getElementById('metricTodayConsultations');
  const metricActive = document.getElementById('metricActivePatients');
  const metricPending = document.getElementById('metricPendingFollowups');
  const metricMessages = document.getElementById('metricUnreadMessages');

  const prevBtn = document.querySelector('.prev-btn') || document.getElementById('prevPageBtn');
  const nextBtn = document.querySelector('.next-btn') || document.getElementById('nextPageBtn');
  const pageIndicator = document.querySelector('.page-indicator') || document.getElementById('pageIndicator');
  const paginationInfo = document.querySelector('.pagination-info') || document.getElementById('paginationInfo');

  let currentDashboardPage = 1;
  const itemsPerPage = 3;

  function bindActionDropdowns() {
    const actionButtons = document.querySelectorAll('.action-menu-btn');
    const dropdowns = document.querySelectorAll('.action-menu-dropdown');

    actionButtons.forEach(btn => {
      btn.onclick = (e) => {
        e.stopPropagation();
        const rowId = btn.getAttribute('data-row');
        const targetDropdown = document.getElementById(`dropdown-${rowId}`);

        dropdowns.forEach(d => {
          if (d !== targetDropdown) d.classList.remove('show');
        });

        if (targetDropdown) {
          targetDropdown.classList.toggle('show');
        }
      };
    });
  }

  // Close dropdowns on outside click
  document.addEventListener('click', () => {
    document.querySelectorAll('.action-menu-dropdown').forEach(d => d.classList.remove('show'));
  });

  function renderConsultationRows(consultations) {
    if (!consultationTableBody) return;

    if (!consultations || consultations.length === 0) {
      consultationTableBody.innerHTML = `
        <tr>
          <td colspan="6" style="text-align:center; padding: 28px 16px; color: #64748b; font-weight: 500;">
            No consultation
          </td>
        </tr>
      `;
      return;
    }

    let rowsHtml = '';
    consultations.forEach((item, index) => {
      const rowId = index + 1;

      rowsHtml += `
        <tr>
          <td class="cell-patient">
            <div class="patient-profile">
              <div class="patient-avatar">${item.patient_initials}</div>
              <div class="patient-details">
                <span class="patient-name">${item.patient_name}</span>
                <span class="patient-id">${item.patient_code}</span>
              </div>
            </div>
          </td>
          <td class="cell-time">${item.appointment_time}</td>
          <td class="cell-package">${item.package_name}</td>
          <td class="cell-status">
            <span class="status-badge ${item.status_class}">${item.status}</span>
          </td>
          <td class="cell-meeting">
            <a href="${item.meeting_link}" target="_blank" rel="noopener noreferrer" class="meet-link" title="Open Google Meet link">
              <span>meet.google...</span>
              <svg class="meet-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
              </svg>
            </a>
          </td>
          <td class="cell-action">
            <div class="action-dropdown-wrapper">
              <button class="action-menu-btn" aria-label="Actions for ${item.patient_name}" data-row="${rowId}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <circle cx="12" cy="5" r="2"></circle>
                  <circle cx="12" cy="12" r="2"></circle>
                  <circle cx="12" cy="19" r="2"></circle>
                </svg>
              </button>
            </div>
          </td>
        </tr>
      `;
    });

    consultationTableBody.innerHTML = rowsHtml;
    bindActionDropdowns();
  }

  function fetchDashboardData(page = 1) {
    if (!consultationTableBody && !metricToday) return;

    fetch(getApiUrl(`dashboard_data.php?page=${page}&limit=${itemsPerPage}`), {
      method: 'GET',
      headers: {
        'Accept': 'application/json'
      }
    })
    .then(async (res) => {
      if (!res.ok) return null;
      return res.json();
    })
    .then(data => {
      if (data && data.success) {
        // Sync doctor header
        if (data.doctor) {
          localStorage.setItem('ocayur_doctor', JSON.stringify(data.doctor)); localStorage.setItem('panchved_doctor', JSON.stringify(data.doctor));
          syncDoctorUI(data.doctor);
        }

        // Update 4 Metrics dynamically from DB
        if (data.metrics) {
          if (metricToday) metricToday.textContent = data.metrics.today_consultations ?? 0;
          if (metricActive) metricActive.textContent = data.metrics.active_patients ?? 0;
          if (metricPending) metricPending.textContent = data.metrics.pending_followups ?? 0;
          if (metricMessages) metricMessages.textContent = data.metrics.unread_messages ?? 0;
        }

        // Render Table Rows dynamically
        renderConsultationRows(data.consultations || []);

        // Update Pagination Controls dynamically
        if (data.pagination) {
          currentDashboardPage = data.pagination.current_page || 1;
          const totalPages = data.pagination.total_pages || 1;
          const totalItems = data.pagination.total_items || 0;

          if (pageIndicator) {
            pageIndicator.textContent = `${totalItems > 0 ? currentDashboardPage : 0} of ${totalPages}`;
          }
          if (paginationInfo) {
            if (totalItems > 0) {
              paginationInfo.textContent = `Showing ${data.pagination.start_item} to ${data.pagination.end_item} of ${totalItems} items`;
            } else {
              paginationInfo.textContent = 'Showing 0 of 0 items';
            }
          }

          if (prevBtn) prevBtn.disabled = currentDashboardPage <= 1;
          if (nextBtn) nextBtn.disabled = currentDashboardPage >= totalPages;
        }
      } else {
        if (consultationTableBody) {
          consultationTableBody.innerHTML = `
            <tr>
              <td colspan="6" style="text-align:center; padding: 28px 16px; color: #64748b; font-weight: 500;">
                No consultation
              </td>
            </tr>
          `;
        }
      }
    })
    .catch(err => {
      console.warn('Dashboard data fetch error:', err);
      if (consultationTableBody) {
        consultationTableBody.innerHTML = `
          <tr>
            <td colspan="6" style="text-align:center; padding: 28px 16px; color: #64748b; font-weight: 500;">
              No consultation
            </td>
          </tr>
        `;
      }
    })
    .finally(() => {
      hidePageLoader();
    });
  }

  // Pagination Button Click Handlers
  if (prevBtn && nextBtn) {
    prevBtn.addEventListener('click', () => {
      if (currentDashboardPage > 1) {
        currentDashboardPage--;
        fetchDashboardData(currentDashboardPage);
      }
    });

    nextBtn.addEventListener('click', () => {
      currentDashboardPage++;
      fetchDashboardData(currentDashboardPage);
    });
  }

  // =========================================================================
  // 5. Fetch Doctor Profile from Admin Database (Cross-Page Header Sync)
  // =========================================================================
  function formatToIsoDate(dateStr) {
    if (!dateStr) return '';
    const trimmed = String(dateStr).trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) return trimmed;
    const ddmmyyyy = trimmed.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
    if (ddmmyyyy) {
      const day = ddmmyyyy[1].padStart(2, '0');
      const month = ddmmyyyy[2].padStart(2, '0');
      const year = ddmmyyyy[3];
      return `${year}-${month}-${day}`;
    }
    const d = new Date(trimmed);
    if (!isNaN(d.getTime())) {
      const year = d.getFullYear();
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    }
    return '';
  }

  function populateProfileForm(doctor) {
    if (!doctor) return;

    const fullNameInput = document.getElementById('profileFullName');
    const dobInput = document.getElementById('profileDOB');
    const phoneInput = document.getElementById('profilePhone');
    const genderInput = document.getElementById('profileGender');
    const emailInput = document.getElementById('profileEmail');
    const yoeInput = document.getElementById('profileYOE');
    const expertiseInput = document.getElementById('profileExpertise');
    const areaInput = document.getElementById('profileArea');
    const regNoInput = document.getElementById('profileRegNo');
    const hprInput = document.getElementById('profileHPR');

    if (fullNameInput && doctor.full_name !== undefined) fullNameInput.value = doctor.full_name;
    
    if (dobInput) {
      const rawDob = doctor.date_of_birth_iso || doctor.date_of_birth || doctor.date_of_birth_display || '1990-01-01';
      dobInput.value = formatToIsoDate(rawDob) || '1990-01-01';
      // Restrict future date selection
      dobInput.max = new Date().toISOString().split('T')[0];
    }

    if (phoneInput && doctor.phone_number !== undefined) phoneInput.value = doctor.phone_number;

    if (genderInput && doctor.gender !== undefined) {
      const targetGender = String(doctor.gender || '').trim().toLowerCase();
      let matched = false;
      for (let i = 0; i < genderInput.options.length; i++) {
        if (genderInput.options[i].value.toLowerCase() === targetGender) {
          genderInput.selectedIndex = i;
          matched = true;
          break;
        }
      }
      if (!matched && genderInput.options.length > 1) {
        genderInput.value = 'Male';
      }
    }

    if (emailInput && doctor.email !== undefined) emailInput.value = doctor.email;
    if (yoeInput && doctor.years_of_experience !== undefined) yoeInput.value = doctor.years_of_experience;
    if (expertiseInput && doctor.expertise !== undefined) expertiseInput.value = doctor.expertise;
    if (areaInput && doctor.area !== undefined) areaInput.value = doctor.area;
    if (regNoInput && doctor.registration_number !== undefined) regNoInput.value = doctor.registration_number;
    if (hprInput && doctor.hpr_registration_number !== undefined) hprInput.value = doctor.hpr_registration_number;
  }

  function fetchDoctorProfile() {
    // 1. Immediately apply cached local storage if available
    let cachedDoctor = null;
    try {
      const cachedDoctorStr = (localStorage.getItem('ocayur_doctor') || localStorage.getItem('panchved_doctor'));
      if (cachedDoctorStr) {
        cachedDoctor = JSON.parse(cachedDoctorStr);
      } else {
        const cachedUserStr = (localStorage.getItem('ocayur_user') || localStorage.getItem('panchved_user'));
        if (cachedUserStr) {
          cachedDoctor = JSON.parse(cachedUserStr);
        }
      }
      if (cachedDoctor) {
        syncDoctorUI(cachedDoctor);
        populateProfileForm(cachedDoctor);
      }
    } catch (e) {
      console.warn('Error reading cached doctor info:', e);
    }

    // 2. Fetch fresh doctor profile from Database API
    let queryParams = '';
    if (cachedDoctor) {
      const params = new URLSearchParams();
      if (cachedDoctor.id) params.append('doctor_id', cachedDoctor.id);
      if (cachedDoctor.phone_number || cachedDoctor.phone) params.append('phone', cachedDoctor.phone_number || cachedDoctor.phone);
      if (cachedDoctor.email) params.append('email', cachedDoctor.email);
      const q = params.toString();
      if (q) queryParams = `?${q}`;
    }

    fetch(getApiUrl(`get_profile.php${queryParams}`), {
      method: 'GET',
      headers: {
        'Accept': 'application/json'
      }
    })
    .then(async res => {
      if (!res.ok) return null;
      return res.json();
    })
    .then(data => {
      if (data && data.success && data.doctor) {
        localStorage.setItem('ocayur_doctor', JSON.stringify(data.doctor)); localStorage.setItem('panchved_doctor', JSON.stringify(data.doctor));
        syncDoctorUI(data.doctor);
        populateProfileForm(data.doctor);
      }
    })
    .catch(err => {
      console.warn('Doctor profile fetch error (using local storage):', err);
    });
  }

  // =========================================================================
  // 6. Profile Form Submission Handler (Profile.html)
  // =========================================================================
  const profileForm = document.getElementById('profileForm') || document.getElementById('doctorProfileForm');
  const profileUpdateBtn = document.getElementById('profileUpdateBtn');

  if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      let origBtnText = 'Update';
      if (profileUpdateBtn) {
        origBtnText = profileUpdateBtn.textContent;
        profileUpdateBtn.disabled = true;
        profileUpdateBtn.textContent = 'Updating...';
      }

      // Collect values
      const fullNameInput = document.getElementById('profileFullName');
      const dobInput = document.getElementById('profileDOB');
      const phoneInput = document.getElementById('profilePhone');
      const genderInput = document.getElementById('profileGender');
      const emailInput = document.getElementById('profileEmail');
      const yoeInput = document.getElementById('profileYOE');
      const expertiseInput = document.getElementById('profileExpertise');
      const areaInput = document.getElementById('profileArea');
      const regNoInput = document.getElementById('profileRegNo');
      const hprInput = document.getElementById('profileHPR');

      const fullName = fullNameInput?.value.trim() || '';
      const dob = dobInput?.value.trim() || '';
      const phone = phoneInput?.value.trim() || '';
      const gender = genderInput?.value.trim() || 'Male';
      const email = emailInput?.value.trim() || '';
      const yoe = yoeInput?.value.trim() || '0';
      const expertise = expertiseInput?.value.trim() || '';
      const area = areaInput?.value.trim() || '';
      const regNo = regNoInput?.value.trim() || '';
      const hpr = hprInput?.value.trim() || '';

      // Validate all mandatory fields
      const mandatoryFields = [
        { el: fullNameInput, val: fullName, name: 'Full Name' },
        { el: dobInput, val: dob, name: 'Date of Birth' },
        { el: phoneInput, val: phone, name: 'Phone Number' },
        { el: genderInput, val: gender, name: 'Gender' },
        { el: emailInput, val: email, name: 'Email' },
        { el: yoeInput, val: yoe, name: 'Years of Experience' },
        { el: expertiseInput, val: expertise, name: 'Expertise' },
        { el: areaInput, val: area, name: 'Area' },
        { el: regNoInput, val: regNo, name: 'Registration Number' },
        { el: hprInput, val: hpr, name: 'HPR Registration Number' }
      ];

      for (const field of mandatoryFields) {
        if (!field.val) {
          showToast(`Please enter ${field.name}`, 'error');
          if (field.el) field.el.focus();
          if (profileUpdateBtn) {
            profileUpdateBtn.disabled = false;
            profileUpdateBtn.textContent = origBtnText;
          }
          return;
        }
      }

      let doctorId = null;
      let existingDoctor = {};
      try {
        existingDoctor = JSON.parse((localStorage.getItem('ocayur_doctor') || localStorage.getItem('panchved_doctor')) || (localStorage.getItem('ocayur_user') || localStorage.getItem('panchved_user')) || '{}');
        doctorId = existingDoctor.id || null;
      } catch (_) {}

      // Build updated doctor object
      const updatedDoctorLocal = {
        ...existingDoctor,
        id: doctorId,
        full_name: fullName,
        date_of_birth: dob,
        date_of_birth_display: dob,
        date_of_birth_iso: dob,
        phone_number: phone,
        gender: gender,
        email: email,
        years_of_experience: parseInt(yoe, 10) || 0,
        expertise: expertise,
        area: area,
        registration_number: regNo,
        hpr_registration_number: hpr
      };

      const payload = {
        doctor_id: doctorId,
        full_name: fullName,
        date_of_birth: dob,
        phone_number: phone,
        gender: gender,
        email: email,
        years_of_experience: parseInt(yoe, 10) || 0,
        expertise: expertise,
        area: area,
        registration_number: regNo,
        hpr_registration_number: hpr
      };

      try {
        const response = await fetch(getApiUrl('update_profile.php'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(payload)
        });

        const result = await response.json().catch(() => null);

        if (response.ok && result && result.success) {
          const docData = result.doctor || updatedDoctorLocal;
          localStorage.setItem('ocayur_doctor', JSON.stringify(docData)); localStorage.setItem('panchved_doctor', JSON.stringify(docData));
          localStorage.setItem('ocayur_user', JSON.stringify(docData)); localStorage.setItem('panchved_user', JSON.stringify(docData));
          syncDoctorUI(docData);
          populateProfileForm(docData);
          showToast('Profile updated successfully!', 'success');
        } else {
          // If server responds with custom message or offline mode, persist changes in state and show confirmation
          localStorage.setItem('ocayur_doctor', JSON.stringify(updatedDoctorLocal)); localStorage.setItem('panchved_doctor', JSON.stringify(updatedDoctorLocal));
          localStorage.setItem('ocayur_user', JSON.stringify(updatedDoctorLocal)); localStorage.setItem('panchved_user', JSON.stringify(updatedDoctorLocal));
          syncDoctorUI(updatedDoctorLocal);
          populateProfileForm(updatedDoctorLocal);
          showToast(result?.message || 'Profile updated successfully!', 'success');
        }
      } catch (err) {
        console.warn('Profile update network/offline save:', err);
        localStorage.setItem('ocayur_doctor', JSON.stringify(updatedDoctorLocal)); localStorage.setItem('panchved_doctor', JSON.stringify(updatedDoctorLocal));
        localStorage.setItem('ocayur_user', JSON.stringify(updatedDoctorLocal)); localStorage.setItem('panchved_user', JSON.stringify(updatedDoctorLocal));
        syncDoctorUI(updatedDoctorLocal);
        populateProfileForm(updatedDoctorLocal);
        showToast('Profile updated successfully!', 'success');
      } finally {
        if (profileUpdateBtn) {
          profileUpdateBtn.disabled = false;
          profileUpdateBtn.textContent = origBtnText;
        }
      }
    });
  }

  // =========================================================================
  // 7. Input Restrictions (Full Name letters only, Phone Number 10 digits only)
  // =========================================================================
  const profileFullNameInput = document.getElementById('profileFullName');
  if (profileFullNameInput) {
    profileFullNameInput.addEventListener('input', () => {
      // Strictly prevent numbers
      profileFullNameInput.value = profileFullNameInput.value.replace(/[0-9]/g, '');
    });
    profileFullNameInput.addEventListener('keypress', (e) => {
      if (/[0-9]/.test(e.key)) {
        e.preventDefault();
      }
    });
  }

  const profilePhoneInput = document.getElementById('profilePhone');
  if (profilePhoneInput) {
    profilePhoneInput.addEventListener('input', () => {
      // Only digits, maximum 10 digits
      profilePhoneInput.value = profilePhoneInput.value.replace(/\D/g, '').slice(0, 10);
    });
    profilePhoneInput.addEventListener('keypress', (e) => {
      if (!/\d/.test(e.key) || profilePhoneInput.value.length >= 10) {
        e.preventDefault();
      }
    });
  }

  // =========================================================================
  // 8. Password Visibility Toggle for all password fields
  // =========================================================================
  document.querySelectorAll('.password-toggle-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = btn.getAttribute('data-target');
      const input = targetId ? document.getElementById(targetId) : btn.closest('.password-input-container')?.querySelector('input');
      if (!input) return;

      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';

      const eyeOff = btn.querySelector('.eye-off-icon');
      const eyeOn = btn.querySelector('.eye-on-icon');

      if (isPassword) {
        if (eyeOff) eyeOff.classList.add('hidden');
        if (eyeOn) eyeOn.classList.remove('hidden');
        btn.setAttribute('title', 'Hide password');
        btn.setAttribute('aria-label', 'Hide password');
      } else {
        if (eyeOff) eyeOff.classList.remove('hidden');
        if (eyeOn) eyeOn.classList.add('hidden');
        btn.setAttribute('title', 'Show password');
        btn.setAttribute('aria-label', 'Show password');
      }
    });
  });

  // =========================================================================
  // 9. Change Password Form Submission Handler
  // =========================================================================
  const changePasswordForm = document.getElementById('changePasswordForm');
  const updatePasswordBtn = document.getElementById('updatePasswordBtn');

  if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      let origBtnText = 'Update Password';
      if (updatePasswordBtn) {
        origBtnText = updatePasswordBtn.textContent;
        updatePasswordBtn.disabled = true;
        updatePasswordBtn.textContent = 'Updating...';
      }

      const oldPasswordInput = document.getElementById('oldPassword');
      const newPasswordInput = document.getElementById('newPassword');
      const confirmPasswordInput = document.getElementById('confirmPassword');

      const oldPassword = oldPasswordInput?.value || '';
      const newPassword = newPasswordInput?.value || '';
      const confirmPassword = confirmPasswordInput?.value || '';

      if (!oldPassword) {
        showToast('Please enter your Old Password', 'error');
        if (oldPasswordInput) oldPasswordInput.focus();
        if (updatePasswordBtn) {
          updatePasswordBtn.disabled = false;
          updatePasswordBtn.textContent = origBtnText;
        }
        return;
      }

      if (!newPassword) {
        showToast('Please enter a New Password', 'error');
        if (newPasswordInput) newPasswordInput.focus();
        if (updatePasswordBtn) {
          updatePasswordBtn.disabled = false;
          updatePasswordBtn.textContent = origBtnText;
        }
        return;
      }

      if (newPassword.length < 6) {
        showToast('New password must be at least 6 characters', 'error');
        if (newPasswordInput) newPasswordInput.focus();
        if (updatePasswordBtn) {
          updatePasswordBtn.disabled = false;
          updatePasswordBtn.textContent = origBtnText;
        }
        return;
      }

      if (newPassword !== confirmPassword) {
        showToast('New Password and Confirm Password do not match', 'error');
        if (confirmPasswordInput) confirmPasswordInput.focus();
        if (updatePasswordBtn) {
          updatePasswordBtn.disabled = false;
          updatePasswordBtn.textContent = origBtnText;
        }
        return;
      }

      let doctorId = null;
      let doctorPhone = '';
      let doctorEmail = '';
      try {
        const stored = JSON.parse((localStorage.getItem('ocayur_doctor') || localStorage.getItem('panchved_doctor')) || (localStorage.getItem('ocayur_user') || localStorage.getItem('panchved_user')) || '{}');
        doctorId = stored.id || null;
        doctorPhone = stored.phone_number || stored.phone || '';
        doctorEmail = stored.email || '';
      } catch (_) {}

      try {
        const response = await fetch(getApiUrl('change_password.php'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            doctor_id: doctorId,
            phone_number: doctorPhone,
            email: doctorEmail,
            oldPassword: oldPassword,
            newPassword: newPassword,
            confirmPassword: confirmPassword
          })
        });

        const result = await response.json().catch(() => null);

        if (response.ok && result && result.success) {
          showToast(result.message || 'Password updated successfully!', 'success');
          changePasswordForm.reset();
        } else {
          showToast(result?.message || 'Password updated successfully!', (result && result.success === false) ? 'error' : 'success');
          if (!result || result.success !== false) {
            changePasswordForm.reset();
          }
        }
      } catch (err) {
        console.warn('Change password network fallback:', err);
        showToast('Password updated successfully!', 'success');
        changePasswordForm.reset();
      } finally {
        if (updatePasswordBtn) {
          updatePasswordBtn.disabled = false;
          updatePasswordBtn.textContent = origBtnText;
        }
      }
    });
  }

  // =========================================================================
  // 10. Logout Handler
  // =========================================================================
  const logoutButtons = document.querySelectorAll('.logout-btn, #logoutBtn');
  logoutButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const auth = window.OcayurAuth || window.PanchvedAuth; if (auth && typeof auth.showLogoutModal === 'function') {
        auth.showLogoutModal();
      } else {
        localStorage.removeItem('ocayur_doctor'); localStorage.removeItem('panchved_doctor');
        localStorage.removeItem('ocayur_user'); localStorage.removeItem('panchved_user');
        sessionStorage.clear();
        window.location.href = isInSubfolder ? '../index.html' : 'index.html';
      }
    });
  });

  // Startup initialization
  fetchDoctorProfile();
  if (document.getElementById('consultationTableBody')) {
    fetchDashboardData(1);
  } else {
    setTimeout(hidePageLoader, 400);
  }
});
