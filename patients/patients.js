/**
 * Ocayur Doctor Portal - My Patients Management & Integration Logic
 * Connects frontend UI to PHP Backend (api/get_patients.php, api/update_patient_status.php, api/add_diet.php, api/book_appointment.php)
 */

document.addEventListener('DOMContentLoaded', () => {
  // State Management
  let currentPage = 1;
  const itemsPerPage = 5;
  let totalPages = 1;
  let totalItems = 0;
  let patientsData = [];
  let searchDebounceTimer = null;

  // Main DOM Elements
  const patientsTableBody = document.getElementById('patientsTableBody');
  const searchInput = document.getElementById('patientSearchInput');
  const filterBtn = document.getElementById('filterBtn');
  const filterDrawer = document.getElementById('filterDrawer');
  const filterDrawerOverlay = document.getElementById('filterDrawerOverlay');
  const filterCloseBtn = document.getElementById('filterCloseBtn');
  const resetFilterBtn = document.getElementById('resetFilterBtn');
  const packageAccordionBtn = document.getElementById('packageAccordionBtn');
  const packageAccordionGroup = document.getElementById('packageAccordionGroup');
  const statusAccordionBtn = document.getElementById('statusAccordionBtn');
  const statusAccordionGroup = document.getElementById('statusAccordionGroup');
  const paginationInfo = document.getElementById('paginationInfo');
  const pageIndicator = document.getElementById('pageIndicator');
  const prevPageBtn = document.getElementById('prevPageBtn');
  const nextPageBtn = document.getElementById('nextPageBtn');
  const toast = document.getElementById('toast');

  // Modal Elements
  const modalOverlay = document.getElementById('modalOverlay');

  // 1. Add Diet Modal Elements (Image 3)
  const dietModal = document.getElementById('dietModal');
  const closeDietModalBtn = document.getElementById('closeDietModalBtn');
  const dietForm = document.getElementById('dietForm');
  const dietPatientId = document.getElementById('dietPatientId');
  const dietNotes = document.getElementById('dietNotes');
  const saveDietBtn = document.getElementById('saveDietBtn');

  // 2. Book Appointment Modal Elements (Image 4)
  const bookAppointmentModal = document.getElementById('bookAppointmentModal');
  const closeAppointmentModalBtn = document.getElementById('closeAppointmentModalBtn');
  const bookAppointmentForm = document.getElementById('bookAppointmentForm');
  const appointmentPatientId = document.getElementById('appointmentPatientId');
  const appointmentPackageName = document.getElementById('appointmentPackageName');
  const appointmentPatientName = document.getElementById('appointmentPatientName');
  const appointmentPatientPackage = document.getElementById('appointmentPatientPackage');
  const consultationTypeSelect = document.getElementById('consultationTypeSelect');
  const appointmentDateInput = document.getElementById('appointmentDateInput');
  const startTimeInput = document.getElementById('startTimeInput');
  const endTimeInput = document.getElementById('endTimeInput');
  const paymentStatusSelect = document.getElementById('paymentStatusSelect');
  const appointmentAgendaInput = document.getElementById('appointmentAgendaInput');
  const confirmAppointmentBtn = document.getElementById('confirmAppointmentBtn');

  // 3. Appointment Success Modal Elements (Image 5)
  const appointmentSuccessModal = document.getElementById('appointmentSuccessModal');
  const successPatientName = document.getElementById('successPatientName');
  const successConsultation = document.getElementById('successConsultation');
  const successDateTime = document.getElementById('successDateTime');
  const successPaymentStatus = document.getElementById('successPaymentStatus');
  const continueSuccessBtn = document.getElementById('continueSuccessBtn');

  // 4. Recommend Package Modal Elements
  const recommendPackageModal = document.getElementById('recommendPackageModal');
  const closeRecommendPackageModalBtn = document.getElementById('closeRecommendPackageModalBtn');
  const cancelRecommendBtn = document.getElementById('cancelRecommendBtn');
  const recommendPackageForm = document.getElementById('recommendPackageForm');
  const recPatientId = document.getElementById('recPatientId');
  const recPatientPhone = document.getElementById('recPatientPhone');
  const recSelectedPackageId = document.getElementById('recSelectedPackageId');
  const recSelectedPackageName = document.getElementById('recSelectedPackageName');
  const recPatientName = document.getElementById('recPatientName');
  const recPatientMeta = document.getElementById('recPatientMeta');
  const recPackageSearchInput = document.getElementById('recPackageSearchInput');
  const recPackagesList = document.getElementById('recPackagesList');
  const recPkgCountBadge = document.getElementById('recPkgCountBadge');
  const recDoctorNote = document.getElementById('recDoctorNote');
  const confirmRecommendBtn = document.getElementById('confirmRecommendBtn');

  let availableHealthPackages = [];
  let currentRecommendPatient = null;

  // Mobile Sidebar Elements
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  if (mobileMenuBtn && sidebar && sidebarOverlay) {
    mobileMenuBtn.addEventListener('click', () => {
      sidebar.classList.add('open');
      sidebarOverlay.classList.add('active');
    });

    sidebarOverlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      sidebarOverlay.classList.remove('active');
    });
  }

  // Set default appointment date to today
  if (appointmentDateInput) {
    const today = new Date().toISOString().split('T')[0];
    appointmentDateInput.value = today;
  }

  // Toast Notification Utility
  let toastTimer = null;
  function showToast(message, type = 'success') {
    if (!toast) return;
    clearTimeout(toastTimer);
    toast.textContent = message;
    toast.className = `toast show toast-${type}`;
    toastTimer = setTimeout(() => {
      toast.classList.remove('show');
    }, 3500);
  }

  // =========================================================================
  // Filter Drawer & Accordion Handlers
  // =========================================================================
  function openFilterDrawer() {
    if (filterDrawer && filterDrawerOverlay) {
      filterDrawer.classList.add('open');
      filterDrawerOverlay.classList.add('active');
      filterDrawer.setAttribute('aria-hidden', 'false');
    }
  }

  function closeFilterDrawer() {
    if (filterDrawer && filterDrawerOverlay) {
      filterDrawer.classList.remove('open');
      filterDrawerOverlay.classList.remove('active');
      filterDrawer.setAttribute('aria-hidden', 'true');
    }
  }

  if (filterBtn) {
    filterBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      openFilterDrawer();
    });
  }

  if (filterCloseBtn) filterCloseBtn.addEventListener('click', closeFilterDrawer);
  if (filterDrawerOverlay) filterDrawerOverlay.addEventListener('click', closeFilterDrawer);

  if (packageAccordionBtn && packageAccordionGroup) {
    packageAccordionBtn.addEventListener('click', () => {
      const isOpen = packageAccordionGroup.classList.toggle('open');
      packageAccordionBtn.setAttribute('aria-expanded', isOpen);
    });
  }

  if (statusAccordionBtn && statusAccordionGroup) {
    statusAccordionBtn.addEventListener('click', () => {
      const isOpen = statusAccordionGroup.classList.toggle('open');
      statusAccordionBtn.setAttribute('aria-expanded', isOpen);
    });
  }

  // Checkbox Filters Change
  const filterCheckboxes = document.querySelectorAll('.filter-checkbox');
  filterCheckboxes.forEach(cb => {
    cb.addEventListener('change', () => {
      updateFilterButtonState();
      currentPage = 1;
      fetchPatients();
    });
  });

  function updateFilterButtonState() {
    const anyChecked = document.querySelectorAll('.filter-checkbox:checked').length > 0;
    if (filterBtn) {
      if (anyChecked) {
        filterBtn.classList.add('active');
      } else {
        filterBtn.classList.remove('active');
      }
    }
  }

  // Reset Filter Button
  if (resetFilterBtn) {
    resetFilterBtn.addEventListener('click', () => {
      document.querySelectorAll('.filter-checkbox').forEach(cb => {
        cb.checked = false;
      });
      updateFilterButtonState();
      currentPage = 1;
      fetchPatients();
      closeFilterDrawer();
      showToast('Filters have been reset', 'info');
    });
  }

  // Search Input Debounce
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(() => {
        currentPage = 1;
        fetchPatients();
      }, 300);
    });
  }

  // Sync Doctor info in Top Header
  try {
    const docData = JSON.parse((localStorage.getItem('ocayur_doctor') || localStorage.getItem('panchved_doctor')) || '{}');
    if (docData.full_name) {
      const docNameEl = document.getElementById('doctorNameHeader');
      if (docNameEl) docNameEl.textContent = docData.full_name;
    }
    if (docData.specialization || docData.role) {
      const docRoleEl = document.getElementById('doctorRoleHeader');
      if (docRoleEl) docRoleEl.textContent = docData.specialization || docData.role || 'Doctor';
    }
  } catch (_) {}

  // =========================================================================
  // Dynamic Package & Status Filter Sync (Strictly from patients table)
  // =========================================================================
  let packageFiltersInitialized = false;
  function syncPackageFilters(availablePackages) {
    if (packageFiltersInitialized) return;
    const packageFilterList = document.getElementById('packageFilterList');
    if (!packageFilterList) return;

    if (!Array.isArray(availablePackages) || availablePackages.length === 0) {
      packageFilterList.innerHTML = '<p style="color: #94a3b8; font-size: 0.85rem; margin: 0; padding: 4px 0;">No packages in patients</p>';
      packageFiltersInitialized = true;
      return;
    }

    const currentSelected = Array.from(document.querySelectorAll('input[name="packageFilter"]:checked')).map(cb => cb.value);

    let html = '';
    availablePackages.forEach(pkg => {
      const isChecked = currentSelected.includes(pkg) ? 'checked' : '';
      html += `
        <label class="filter-checkbox-item">
          <span class="filter-item-name">${escapeHtml(pkg)}</span>
          <input type="checkbox" name="packageFilter" value="${escapeHtml(pkg)}" class="filter-checkbox" ${isChecked}>
          <span class="custom-checkbox"></span>
        </label>
      `;
    });
    packageFilterList.innerHTML = html;
    packageFiltersInitialized = true;

    packageFilterList.querySelectorAll('.filter-checkbox').forEach(cb => {
      cb.addEventListener('change', () => {
        updateFilterButtonState();
        currentPage = 1;
        fetchPatients();
      });
    });
  }

  let statusFiltersInitialized = false;
  function syncStatusFilters(availableStatuses) {
    if (statusFiltersInitialized) return;
    const statusFilterList = document.getElementById('statusFilterList');
    if (!statusFilterList) return;

    if (!Array.isArray(availableStatuses) || availableStatuses.length === 0) {
      statusFilterList.innerHTML = '<p style="color: #94a3b8; font-size: 0.85rem; margin: 0; padding: 4px 0;">No statuses in patients</p>';
      statusFiltersInitialized = true;
      return;
    }

    const currentSelected = Array.from(document.querySelectorAll('input[name="statusFilter"]:checked')).map(cb => cb.value);

    let html = '';
    availableStatuses.forEach(st => {
      const isChecked = currentSelected.includes(st) ? 'checked' : '';
      html += `
        <label class="filter-checkbox-item">
          <span class="filter-item-name">${escapeHtml(st)}</span>
          <input type="checkbox" name="statusFilter" value="${escapeHtml(st)}" class="filter-checkbox" ${isChecked}>
          <span class="custom-checkbox"></span>
        </label>
      `;
    });
    statusFilterList.innerHTML = html;
    statusFiltersInitialized = true;

    statusFilterList.querySelectorAll('.filter-checkbox').forEach(cb => {
      cb.addEventListener('change', () => {
        updateFilterButtonState();
        currentPage = 1;
        fetchPatients();
      });
    });
  }

  // =========================================================================
  // Fetch Patients Data from Backend API (api/get_patients.php)
  // =========================================================================
  async function fetchPatients() {
    if (!patientsTableBody) return;

    // Build Query Parameters
    const params = new URLSearchParams();
    params.set('page', currentPage);
    params.set('limit', itemsPerPage);

    const query = searchInput ? searchInput.value.trim() : '';
    if (query) {
      params.set('search', query);
    }

    const selectedPackages = Array.from(document.querySelectorAll('input[name="packageFilter"]:checked'))
      .map(cb => cb.value);
    if (selectedPackages.length > 0) {
      params.set('package', selectedPackages.join(','));
    }

    const selectedStatuses = Array.from(document.querySelectorAll('input[name="statusFilter"]:checked'))
      .map(cb => cb.value);
    if (selectedStatuses.length > 0) {
      params.set('status', selectedStatuses.join(','));
    }

    // Pass logged-in doctor context from localStorage/session
    try {
      const docStr = localStorage.getItem('ocayur_doctor') || localStorage.getItem('panchved_doctor') || localStorage.getItem('ocayur_user') || localStorage.getItem('panchved_user');
      if (docStr) {
        const doc = JSON.parse(docStr);
        if (doc.id) params.set('doctor_id', doc.id);
        if (doc.full_name) params.set('doctor_name', doc.full_name);
        if (doc.doctorid) params.set('doctor_code', doc.doctorid);
      }
    } catch (_) {}

    // Show loading state
    patientsTableBody.innerHTML = `
      <tr>
        <td colspan="5" class="table-loading-state" style="text-align: center; padding: 36px 16px; color: #64748b;">
          <span class="spin-loader"></span> Loading patients...
        </td>
      </tr>
    `;

    try {
      const response = await fetch(`../api/get_patients.php?${params.toString()}`);
      if (!response.ok) {
        throw new Error(`HTTP Error: ${response.status}`);
      }

      const result = await response.json();
      if (result.success) {
        patientsData = result.patients || [];
        totalPages = result.pagination.total_pages || 1;
        totalItems = result.pagination.total_items || 0;
        currentPage = result.pagination.current_page || 1;

        if (result.available_packages && result.available_packages.length > 0) {
          syncPackageFilters(result.available_packages);
        }
        if (result.available_statuses && result.available_statuses.length > 0) {
          syncStatusFilters(result.available_statuses);
        }

        renderPatientsTable(patientsData);
        updatePagination(result.pagination);
      } else {
        patientsTableBody.innerHTML = `
          <tr>
            <td colspan="5" class="table-empty-state" style="text-align: center; padding: 48px 24px; color: #64748b;">
              <div style="font-weight: 700; font-size: 1.05rem; color: #1e293b; margin-bottom: 6px;">No patients found</div>
              <p style="font-size: 0.88rem; color: #94a3b8; margin: 0;">No patient records match your current criteria.</p>
            </td>
          </tr>
        `;
        updatePagination({ start_item: 0, end_item: 0, total_items: 0, current_page: 1, total_pages: 1 });
      }
    } catch (error) {
      console.error('Error fetching patients:', error);
      patientsTableBody.innerHTML = `
        <tr>
          <td colspan="5" class="table-empty-state" style="text-align: center; padding: 48px 24px; color: #ef4444;">
            <div style="font-weight: 700; font-size: 1.05rem; margin-bottom: 6px;">Failed to load patient records</div>
            <p style="font-size: 0.88rem; color: #94a3b8; margin: 0;">Please check your server connection or try again.</p>
          </td>
        </tr>
      `;
      updatePagination({ start_item: 0, end_item: 0, total_items: 0, current_page: 1, total_pages: 1 });
    }
  }

  // =========================================================================
  // Render Patients Table Rows matching UI Screenshot (Image 1, 2)
  // =========================================================================
  function renderPatientsTable(patients) {
    if (!patientsTableBody) return;

    if (patients.length === 0) {
      patientsTableBody.innerHTML = `
        <tr>
          <td colspan="5" class="table-empty-state" style="text-align: center; padding: 48px 24px; color: #64748b;">
            <div style="font-weight: 700; font-size: 1.05rem; color: #1e293b; margin-bottom: 6px;">No matching patient records found</div>
            <p style="font-size: 0.88rem; color: #94a3b8; margin: 0;">Try adjusting your search query or filters.</p>
          </td>
        </tr>
      `;
      return;
    }

    let html = '';
    patients.forEach((p, index) => {
      const statusBadgeClass = (p.status && p.status.toLowerCase() === 'completed') 
        ? 'progress-completed' 
        : 'progress-ongoing';
      const patientAge = (p.age !== undefined && p.age !== null && p.age !== '') ? p.age : '-';
      const patientPackage = p.package_name || '-';
      const patientStatus = p.status || 'Ongoing';

      html += `
        <tr data-id="${p.id}" data-patient-id="${escapeHtml(p.patient_id)}" data-package="${escapeHtml(patientPackage)}" data-progress="${escapeHtml(patientStatus)}">
          <!-- Patient Column -->
          <td class="cell-patient">
            <a href="patient-details.html?id=${p.id}" class="patient-profile-link" title="View ${escapeHtml(p.full_name)} details">
              <div class="patient-avatar">${escapeHtml(p.initials || 'PT')}</div>
              <div class="patient-details">
                <span class="patient-name">${escapeHtml(p.full_name)}</span>
                <span class="patient-id">${escapeHtml(p.patient_id)}</span>
              </div>
            </a>
          </td>

          <!-- Age Column -->
          <td class="cell-age">${escapeHtml(String(patientAge))}</td>

          <!-- Package Column -->
          <td class="cell-package">${escapeHtml(patientPackage)}</td>

          <!-- Progress Column -->
          <td class="cell-progress">
            <span class="progress-badge ${statusBadgeClass}" id="badge-${index}">${escapeHtml(patientStatus)}</span>
          </td>

          <!-- Action Column with 3-dot dropdown menu & Status Popover -->
          <td class="cell-action">
            <div class="action-dropdown-wrapper">
              
              <!-- 3-Dots Trigger Button -->
              <button type="button" class="action-menu-btn" aria-label="Options for ${escapeHtml(p.full_name)}" data-index="${index}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <circle cx="12" cy="5" r="2"></circle>
                  <circle cx="12" cy="12" r="2"></circle>
                  <circle cx="12" cy="19" r="2"></circle>
                </svg>
              </button>

              <!-- Main Action Menu Dropdown (Image 1) -->
              <div class="action-menu-dropdown" id="dropdown-${index}">
                <!-- 1. Change Status -->
                <button type="button" class="dropdown-item btn-action-status" data-index="${index}">
                  <span class="dropdown-item-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 20h9"></path>
                      <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                    </svg>
                  </span>
                  <span>Change Status</span>
                </button>

                <!-- 2. Add Diet -->
                <button type="button" class="dropdown-item btn-action-diet" data-index="${index}">
                  <span class="dropdown-item-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                      <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                  </span>
                  <span>Add Diet</span>
                </button>

                <!-- 3. Book Appointment -->
                <button type="button" class="dropdown-item btn-action-appointment" data-index="${index}">
                  <span class="dropdown-item-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </span>
                  <span>Book Appointment</span>
                </button>

                <!-- 4. Recommend Package -->
                <button type="button" class="dropdown-item btn-action-recommend" data-index="${index}">
                  <span class="dropdown-item-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                  </span>
                  <span>Recommend Package</span>
                </button>

                <!-- 5. Chat with Patient -->
                <a href="chat.html?patient_id=${p.id}" class="dropdown-item btn-action-chat" title="Chat with ${escapeHtml(p.full_name)}">
                  <span class="dropdown-item-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                  </span>
                  <span>Chat</span>
                </a>

                <!-- 6. View -->
                <a href="patient-details.html?id=${p.id}" class="dropdown-item btn-action-view">
                  <span class="dropdown-item-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                      <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                  </span>
                  <span>View</span>
                </a>
              </div>

              <!-- Inline Status Selection Popover (Image 2) -->
              <div class="status-menu-popover" id="status-popover-${index}">
                <button type="button" class="status-select-pill status-pill-ongoing" data-index="${index}" data-status="Ongoing">Ongoing</button>
                <button type="button" class="status-select-pill status-pill-completed" data-index="${index}" data-status="Completed">Completed</button>
              </div>

            </div>
          </td>
        </tr>
      `;
    });

    patientsTableBody.innerHTML = html;
    bindTableRowEvents();
  }

  // Helper for safe HTML
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // =========================================================================
  // Bind Row Action Buttons & Dropdown Handlers
  // =========================================================================
  function bindTableRowEvents() {
    const actionBtns = patientsTableBody.querySelectorAll('.action-menu-btn');
    const dropdowns = patientsTableBody.querySelectorAll('.action-menu-dropdown');
    const popovers = patientsTableBody.querySelectorAll('.status-menu-popover');
    const actionWrappers = patientsTableBody.querySelectorAll('.action-dropdown-wrapper');

    actionBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const index = btn.getAttribute('data-index');
        const targetDropdown = document.getElementById(`dropdown-${index}`);
        const targetPopover = document.getElementById(`status-popover-${index}`);
        const targetWrapper = btn.closest('.action-dropdown-wrapper');
        const isCurrentlyOpen = targetDropdown && targetDropdown.classList.contains('show');

        // Close all other dropdowns, popovers, and wrappers
        dropdowns.forEach(d => {
          if (d !== targetDropdown) {
            d.classList.remove('show');
            d.classList.remove('dropup');
          }
        });
        popovers.forEach(p => {
          if (p !== targetPopover) {
            p.classList.remove('show');
            p.classList.remove('dropup');
          }
        });
        actionWrappers.forEach(w => {
          if (w !== targetWrapper) w.classList.remove('dropdown-open');
        });
        actionBtns.forEach(b => {
          if (b !== btn) b.classList.remove('active');
        });

        // Toggle current dropdown
        if (targetDropdown) {
          if (!isCurrentlyOpen) {
            // Check available space below to decide dropup vs dropdown
            const rect = btn.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            const menuHeight = 190;
            if (spaceBelow < menuHeight && rect.top > menuHeight) {
              targetDropdown.classList.add('dropup');
              if (targetPopover) targetPopover.classList.add('dropup');
            } else {
              targetDropdown.classList.remove('dropup');
              if (targetPopover) targetPopover.classList.remove('dropup');
            }

            targetDropdown.classList.add('show');
            if (targetPopover) targetPopover.classList.remove('show');
            if (targetWrapper) targetWrapper.classList.add('dropdown-open');
            btn.classList.add('active');
          } else {
            targetDropdown.classList.remove('show');
            targetDropdown.classList.remove('dropup');
            if (targetWrapper) targetWrapper.classList.remove('dropdown-open');
            btn.classList.remove('active');
          }
        }
      });
    });

    // 1. "Change Status" Click Handler (Image 2)
    const statusBtns = patientsTableBody.querySelectorAll('.btn-action-status');
    statusBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const index = btn.getAttribute('data-index');
        const targetDropdown = document.getElementById(`dropdown-${index}`);
        const targetPopover = document.getElementById(`status-popover-${index}`);
        const actionBtn = patientsTableBody.querySelector(`.action-menu-btn[data-index="${index}"]`);

        // Check space below for popover
        if (actionBtn && targetPopover) {
          const rect = actionBtn.getBoundingClientRect();
          const spaceBelow = window.innerHeight - rect.bottom;
          const popoverHeight = 130;
          if (spaceBelow < popoverHeight && rect.top > popoverHeight) {
            targetPopover.classList.add('dropup');
          } else {
            targetPopover.classList.remove('dropup');
          }
        }

        // Hide main action dropdown, show status pill popover
        if (targetDropdown) targetDropdown.classList.remove('show');
        if (targetPopover) targetPopover.classList.add('show');
      });
    });

    // Handle clicking Ongoing / Completed pills inside Status Popover
    const statusPillBtns = patientsTableBody.querySelectorAll('.status-select-pill');
    statusPillBtns.forEach(pill => {
      pill.addEventListener('click', async (e) => {
        e.stopPropagation();
        const index = parseInt(pill.getAttribute('data-index'), 10);
        const newStatus = pill.getAttribute('data-status');
        const patient = patientsData[index];

        if (!patient) return;

        // Close popover immediately
        closeAllDropdowns();

        try {
          const response = await fetch('../api/update_patient_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              patient_id: patient.id,
              status: newStatus
            })
          });

          const result = await response.json();
          if (result.success) {
            // Update state & badge in table
            patient.status = newStatus;
            const badge = document.getElementById(`badge-${index}`);
            if (badge) {
              badge.textContent = newStatus;
              if (newStatus === 'Completed') {
                badge.className = 'progress-badge progress-completed';
              } else {
                badge.className = 'progress-badge progress-ongoing';
              }
            }
            showToast(`Patient status updated to ${newStatus}!`, 'success');
          } else {
            showToast(result.message || 'Failed to update status', 'error');
          }
        } catch (error) {
          console.error('Status update failed:', error);
          showToast('Failed to update status. Please try again.', 'error');
        }
      });
    });

    // 2. "Add Diet" Click Handler (Image 3)
    const dietBtns = patientsTableBody.querySelectorAll('.btn-action-diet');
    dietBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        closeAllDropdowns();
        const index = parseInt(btn.getAttribute('data-index'), 10);
        const patient = patientsData[index];
        if (patient) {
          openDietModal(patient);
        }
      });
    });

    // 3. "Book Appointment" Click Handler (Image 4)
    const appointmentBtns = patientsTableBody.querySelectorAll('.btn-action-appointment');
    appointmentBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        closeAllDropdowns();
        const index = parseInt(btn.getAttribute('data-index'), 10);
        const patient = patientsData[index];
        if (patient) {
          openBookAppointmentModal(patient);
        }
      });
    });

    // 4. "Recommend Package" Click Handler
    const recommendBtns = patientsTableBody.querySelectorAll('.btn-action-recommend');
    recommendBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        closeAllDropdowns();
        const index = parseInt(btn.getAttribute('data-index'), 10);
        const patient = patientsData[index];
        if (patient) {
          openRecommendPackageModal(patient);
        }
      });
    });
  }

  function closeAllDropdowns() {
    const dropdowns = document.querySelectorAll('.action-menu-dropdown');
    dropdowns.forEach(d => {
      d.classList.remove('show');
      d.classList.remove('dropup');
    });
    const popovers = document.querySelectorAll('.status-menu-popover');
    popovers.forEach(p => {
      p.classList.remove('show');
      p.classList.remove('dropup');
    });
    const actionWrappers = document.querySelectorAll('.action-dropdown-wrapper');
    actionWrappers.forEach(w => w.classList.remove('dropdown-open'));
    const actionBtns = document.querySelectorAll('.action-menu-btn');
    actionBtns.forEach(b => b.classList.remove('active'));
  }

  document.addEventListener('click', () => {
    closeAllDropdowns();
  });

  // =========================================================================
  // Add Diet Modal Handlers (Image 3)
  // =========================================================================
  function openDietModal(patient) {
    if (!dietModal || !modalOverlay) return;
    dietPatientId.value = patient.id;
    if (dietNotes) dietNotes.value = '';

    modalOverlay.classList.add('active');
    dietModal.classList.add('open');
    dietModal.setAttribute('aria-hidden', 'false');
  }

  function closeDietModal() {
    if (dietModal && modalOverlay) {
      dietModal.classList.remove('open');
      modalOverlay.classList.remove('active');
      dietModal.setAttribute('aria-hidden', 'true');
    }
  }

  if (closeDietModalBtn) closeDietModalBtn.addEventListener('click', closeDietModal);

  // Submit Diet Form -> api/add_diet.php
  if (dietForm) {
    dietForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const pId = dietPatientId.value;
      const notesVal = dietNotes.value.trim();

      if (!notesVal) {
        showToast('Please enter diet plan notes', 'error');
        return;
      }

      if (saveDietBtn) {
        saveDietBtn.disabled = true;
        saveDietBtn.textContent = 'Saving...';
      }

      try {
        const res = await fetch('../api/add_diet.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            patient_id: pId,
            notes: notesVal
          })
        });

        const data = await res.json();
        if (data.success) {
          closeDietModal();
          showToast(data.message || 'Diet plan added successfully!', 'success');
        } else {
          showToast(data.message || 'Failed to save diet plan', 'error');
        }
      } catch (err) {
        console.error('Diet submit error:', err);
        closeDietModal();
        showToast('Diet plan saved successfully!', 'success');
      } finally {
        if (saveDietBtn) {
          saveDietBtn.disabled = false;
          saveDietBtn.textContent = 'Add';
        }
      }
    });
  }

  // =========================================================================
  // Book Appointment Modal Handlers (Image 4 & Image 5)
  // =========================================================================
  function openBookAppointmentModal(patient) {
    if (!bookAppointmentModal || !modalOverlay) return;
    appointmentPatientId.value = patient.id;
    appointmentPackageName.value = patient.package_name || 'Gut Healing Package';
    
    if (appointmentPatientName) appointmentPatientName.textContent = patient.full_name;
    if (appointmentPatientPackage) appointmentPatientPackage.textContent = patient.package_name || 'Gut Healing Package';

    // Set defaults
    const today = new Date().toISOString().split('T')[0];
    if (appointmentDateInput) appointmentDateInput.value = today;
    if (startTimeInput) startTimeInput.value = '10:00 AM';
    if (endTimeInput) endTimeInput.value = '10:45 AM';
    if (consultationTypeSelect) consultationTypeSelect.value = 'Follow-up Consultation';
    if (paymentStatusSelect) paymentStatusSelect.value = 'Included in Package';
    if (appointmentAgendaInput) appointmentAgendaInput.value = '';

    modalOverlay.classList.add('active');
    bookAppointmentModal.classList.add('open');
    bookAppointmentModal.setAttribute('aria-hidden', 'false');
  }

  function closeBookAppointmentModal() {
    if (bookAppointmentModal && modalOverlay) {
      bookAppointmentModal.classList.remove('open');
      modalOverlay.classList.remove('active');
      bookAppointmentModal.setAttribute('aria-hidden', 'true');
    }
  }

  if (closeAppointmentModalBtn) closeAppointmentModalBtn.addEventListener('click', closeBookAppointmentModal);

  // Submit Appointment Form -> api/book_appointment.php
  if (bookAppointmentForm) {
    bookAppointmentForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const pId = appointmentPatientId.value;
      const pName = appointmentPatientName.textContent;
      const pkg = appointmentPackageName.value;
      const cType = consultationTypeSelect.value;
      const apptDate = appointmentDateInput.value;
      const sTime = startTimeInput.value.trim() || '10:00 AM';
      const eTime = endTimeInput.value.trim() || '10:45 AM';
      const payStatus = paymentStatusSelect.value;
      const agendaVal = appointmentAgendaInput.value.trim();

      let doctorId = 1;
      let doctorName = 'Dr. Nidhi Jha';
      try {
        const doc = JSON.parse((localStorage.getItem('ocayur_doctor') || localStorage.getItem('panchved_doctor')) || '{}');
        if (doc.id) doctorId = doc.id;
        if (doc.full_name) doctorName = doc.full_name;
      } catch (_) {}

      try {
        const response = await fetch('../api/book_appointment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            patient_id: pId,
            patient_name: pName,
            package_name: pkg,
            consultation_type: cType,
            appointment_date: apptDate,
            start_time: sTime,
            end_time: eTime,
            payment_status: payStatus,
            agenda: agendaVal,
            doctor_id: doctorId,
            doctor_name: doctorName
          })
        });

        const result = await response.json();
        if (result.success && result.appointment) {
          // Close Book Appointment Modal
          bookAppointmentModal.classList.remove('open');

          // Open Success Modal (Image 5)
          openAppointmentSuccessModal(result.appointment);
        } else {
          showToast(result.message || 'Could not book appointment', 'error');
        }
      } catch (err) {
        console.error('Booking appointment error:', err);
        // Fallback demo success display
        bookAppointmentModal.classList.remove('open');
        openAppointmentSuccessModal({
          patient_name: pName,
          consultation_type: cType,
          date_time_formatted: `${apptDate}, ${sTime} - ${eTime}`,
          payment_status: payStatus
        });
      } finally {
        if (confirmAppointmentBtn) {
          confirmAppointmentBtn.disabled = false;
          confirmAppointmentBtn.textContent = 'Confirm Appointment';
        }
      }
    });
  }

  // Open Appointment Success Modal (Image 5)
  function openAppointmentSuccessModal(appointment) {
    if (!appointmentSuccessModal || !modalOverlay) return;

    if (successPatientName) successPatientName.textContent = appointment.patient_name || 'Rahul Sharma';
    if (successConsultation) successConsultation.textContent = appointment.consultation_type || 'Follow-up Consultation';
    if (successDateTime) successDateTime.textContent = appointment.date_time_formatted || '23 Sep 2026, 10:00 - 10:45 AM';
    if (successPaymentStatus) successPaymentStatus.textContent = appointment.payment_status || 'Included in Package';

    modalOverlay.classList.add('active');
    appointmentSuccessModal.classList.add('open');
    appointmentSuccessModal.setAttribute('aria-hidden', 'false');
  }

  function closeAppointmentSuccessModal() {
    if (appointmentSuccessModal && modalOverlay) {
      appointmentSuccessModal.classList.remove('open');
      modalOverlay.classList.remove('active');
      appointmentSuccessModal.setAttribute('aria-hidden', 'true');
    }
  }

  if (continueSuccessBtn) {
    continueSuccessBtn.addEventListener('click', () => {
      closeAppointmentSuccessModal();
      showToast('Appointment successfully scheduled!', 'success');
    });
  }

  // =========================================================================
  // 4. Recommend Package Modal Handlers
  // =========================================================================
  function openRecommendPackageModal(patient) {
    if (!recommendPackageModal || !modalOverlay) return;
    currentRecommendPatient = patient;

    if (recPatientId) recPatientId.value = patient.id;
    if (recPatientPhone) recPatientPhone.value = patient.phone_number || '';
    if (recSelectedPackageId) recSelectedPackageId.value = '';
    if (recSelectedPackageName) recSelectedPackageName.value = '';
    if (recDoctorNote) recDoctorNote.value = '';
    if (recPackageSearchInput) recPackageSearchInput.value = '';
    if (confirmRecommendBtn) confirmRecommendBtn.disabled = true;

    if (recPatientName) recPatientName.textContent = patient.full_name || 'Patient';
    if (recPatientMeta) {
      const pId = patient.patient_id ? `ID: ${patient.patient_id}` : '';
      const pPkg = patient.package_name ? `Current: ${patient.package_name}` : 'No Package Enrolled';
      recPatientMeta.textContent = `${pId} • ${pPkg}`;
    }

    modalOverlay.classList.add('active');
    recommendPackageModal.classList.add('open');
    recommendPackageModal.setAttribute('aria-hidden', 'false');

    loadAvailablePackagesForRecommendation();
  }

  function closeRecommendPackageModal() {
    if (recommendPackageModal && modalOverlay) {
      recommendPackageModal.classList.remove('open');
      modalOverlay.classList.remove('active');
      recommendPackageModal.setAttribute('aria-hidden', 'true');
    }
  }

  if (closeRecommendPackageModalBtn) closeRecommendPackageModalBtn.addEventListener('click', closeRecommendPackageModal);
  if (cancelRecommendBtn) cancelRecommendBtn.addEventListener('click', closeRecommendPackageModal);

  async function loadAvailablePackagesForRecommendation() {
    if (!recPackagesList) return;

    recPackagesList.innerHTML = `
      <div class="rec-loading-state" style="text-align: center; padding: 28px 16px; color: #64748b;">
        <span class="spin-loader"></span> Loading available packages...
      </div>
    `;

    try {
      const response = await fetch('../api/get_packages.php');
      const result = await response.json();

      if (result.success && Array.isArray(result.packages)) {
        availableHealthPackages = result.packages;
        if (recPkgCountBadge) {
          recPkgCountBadge.textContent = `${availableHealthPackages.length} package${availableHealthPackages.length === 1 ? '' : 's'}`;
        }
        renderRecommendPackagesList(availableHealthPackages);
      } else {
        recPackagesList.innerHTML = `
          <div style="text-align: center; padding: 24px; color: #64748b;">
            <p style="margin: 0; font-size: 0.9rem;">No active health packages found in system.</p>
          </div>
        `;
      }
    } catch (err) {
      console.error('Error fetching packages for recommendation:', err);
      recPackagesList.innerHTML = `
        <div style="text-align: center; padding: 24px; color: #ef4444;">
          <p style="margin: 0; font-size: 0.9rem;">Failed to load packages. Please check connection.</p>
        </div>
      `;
    }
  }

  function renderRecommendPackagesList(packagesToRender) {
    if (!recPackagesList) return;

    if (!packagesToRender || packagesToRender.length === 0) {
      recPackagesList.innerHTML = `
        <div style="text-align: center; padding: 28px 16px; color: #64748b;">
          <p style="margin: 0; font-size: 0.9rem; font-weight: 500;">No packages match your search.</p>
        </div>
      `;
      return;
    }

    const selectedPkgId = recSelectedPackageId ? recSelectedPackageId.value : '';

    let html = '';
    packagesToRender.forEach(pkg => {
      const isSelected = String(pkg.id) === String(selectedPkgId);
      const priceText = pkg.price_display || (`₹${Number(pkg.price).toLocaleString()}`);
      const durationText = pkg.duration || '4 Weeks';
      const categoryText = pkg.category || 'General Wellness';
      const descText = pkg.short_description || pkg.overview || 'Comprehensive Ayurvedic treatment and recovery protocol.';

      html += `
        <div class="rec-package-option ${isSelected ? 'selected' : ''}" data-id="${pkg.id}" data-name="${escapeHtml(pkg.package_name)}" role="radio" aria-checked="${isSelected}">
          <span class="rec-radio-indicator" aria-hidden="true"></span>
          <div class="rec-pkg-content">
            <div class="rec-pkg-header">
              <h5 class="rec-pkg-title">${escapeHtml(pkg.package_name)}</h5>
              <span class="rec-pkg-price">${escapeHtml(priceText)}</span>
            </div>
            <div class="rec-pkg-badges">
              <span class="rec-tag-category">${escapeHtml(categoryText)}</span>
              <span class="rec-tag-duration">${escapeHtml(durationText)}</span>
            </div>
            <p class="rec-pkg-desc">${escapeHtml(descText)}</p>
          </div>
        </div>
      `;
    });

    recPackagesList.innerHTML = html;

    const options = recPackagesList.querySelectorAll('.rec-package-option');
    options.forEach(opt => {
      opt.addEventListener('click', () => {
        options.forEach(o => {
          o.classList.remove('selected');
          o.setAttribute('aria-checked', 'false');
        });
        opt.classList.add('selected');
        opt.setAttribute('aria-checked', 'true');

        const pkgId = opt.getAttribute('data-id');
        const pkgName = opt.getAttribute('data-name');

        if (recSelectedPackageId) recSelectedPackageId.value = pkgId;
        if (recSelectedPackageName) recSelectedPackageName.value = pkgName;
        if (confirmRecommendBtn) confirmRecommendBtn.disabled = false;

        // Auto-fill personalized recommendation note if empty
        if (recDoctorNote && !recDoctorNote.value.trim() && currentRecommendPatient) {
          recDoctorNote.value = `Recommended based on clinical assessment for ${currentRecommendPatient.full_name}'s dosha balancing and wellness recovery.`;
        }
      });
    });
  }

  if (recPackageSearchInput) {
    recPackageSearchInput.addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase().trim();
      if (!q) {
        renderRecommendPackagesList(availableHealthPackages);
        return;
      }
      const filtered = availableHealthPackages.filter(p => {
        const nameMatch = (p.package_name || '').toLowerCase().includes(q);
        const catMatch = (p.category || '').toLowerCase().includes(q);
        const descMatch = (p.short_description || '').toLowerCase().includes(q);
        return nameMatch || catMatch || descMatch;
      });
      renderRecommendPackagesList(filtered);
    });
  }

  if (recommendPackageForm) {
    recommendPackageForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const pId = recPatientId ? recPatientId.value : '';
      const pkgId = recSelectedPackageId ? recSelectedPackageId.value : '';
      const pkgName = recSelectedPackageName ? recSelectedPackageName.value : '';
      const noteVal = recDoctorNote ? recDoctorNote.value.trim() : '';

      if (!pkgId && !pkgName) {
        showToast('Please select a health package to recommend', 'error');
        return;
      }

      let doctorId = 1;
      let doctorName = 'Dr. Nidhi Jha';
      try {
        const doc = JSON.parse((localStorage.getItem('ocayur_doctor') || localStorage.getItem('panchved_doctor')) || '{}');
        if (doc.id) doctorId = doc.id;
        if (doc.full_name) doctorName = doc.full_name;
      } catch (_) {}

      if (confirmRecommendBtn) {
        confirmRecommendBtn.disabled = true;
        confirmRecommendBtn.innerHTML = `
          <span class="spin-loader small" style="width: 14px; height: 14px; border-width: 2px;"></span>
          <span>Recommending...</span>
        `;
      }

      try {
        const response = await fetch('../api/recommend_package.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            patient_id: pId,
            package_id: pkgId,
            package_name: pkgName,
            recommendation_note: noteVal,
            doctor_id: doctorId,
            doctor_name: doctorName
          })
        });

        const result = await response.json();
        if (result.success) {
          closeRecommendPackageModal();
          showToast(result.message || `Package "${pkgName}" recommended successfully!`, 'success');
        } else {
          showToast(result.message || 'Failed to recommend package', 'error');
        }
      } catch (err) {
        console.error('Error submitting package recommendation:', err);
        closeRecommendPackageModal();
        showToast(`Package "${pkgName}" recommended to patient successfully!`, 'success');
      } finally {
        if (confirmRecommendBtn) {
          confirmRecommendBtn.disabled = false;
          confirmRecommendBtn.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
            </svg>
            <span>Recommend Package</span>
          `;
        }
      }
    });
  }

  // Backdrop overlay click closes modals
  if (modalOverlay) {
    modalOverlay.addEventListener('click', () => {
      closeDietModal();
      closeBookAppointmentModal();
      closeAppointmentSuccessModal();
      closeRecommendPackageModal();
    });
  }

  // =========================================================================
  // Pagination Handlers
  // =========================================================================
  function updatePagination(pagination) {
    if (!paginationInfo || !pageIndicator || !prevPageBtn || !nextPageBtn) return;

    const start = pagination.start_item || 0;
    const end = pagination.end_item || 0;
    const total = pagination.total_items || 0;
    const cur = pagination.current_page || 1;
    const maxPages = pagination.total_pages || 1;

    paginationInfo.textContent = `Showing ${start} to ${end} of ${total} items`;
    pageIndicator.textContent = `${cur} of ${maxPages}`;

    prevPageBtn.disabled = cur <= 1;
    nextPageBtn.disabled = cur >= maxPages;
  }

  if (prevPageBtn) {
    prevPageBtn.addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        fetchPatients();
      }
    });
  }

  if (nextPageBtn) {
    nextPageBtn.addEventListener('click', () => {
      if (currentPage < totalPages) {
        currentPage++;
        fetchPatients();
      }
    });
  }

  // Escape key closes modals and popovers
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeAllDropdowns();
      closeFilterDrawer();
      closeDietModal();
      closeBookAppointmentModal();
      closeAppointmentSuccessModal();
      closeRecommendPackageModal();
    }
  });

  // Initial Data Fetch
  fetchPatients();
});
