/**
 * Ocayur Doctor Portal - Patient Details Frontend Integration Logic
 * Connects patient-details.html to PHP Backend APIs
 */

document.addEventListener('DOMContentLoaded', () => {
  // State
  let currentPatientId = null;
  let currentPatientData = null;
  let currentPatientAppointments = [];
  let currentApptPage = 1;
  const apptsPerPage = 3;
  let totalApptPages = 1;

  // Read URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('id')) {
    currentPatientId = parseInt(urlParams.get('id'), 10);
  } else if (urlParams.get('patient_id')) {
    currentPatientId = urlParams.get('patient_id');
  }

  // Chat Button & Link Binding
  const chatWithPatientBtn = document.getElementById('chatWithPatientBtn');
  if (chatWithPatientBtn && currentPatientId) {
    chatWithPatientBtn.href = `chat.html?patient_id=${currentPatientId}`;
  }

  // DOM Elements
  const metricTotalAppts = document.getElementById('metricTotalAppts');
  const metricPendingFollowups = document.getElementById('metricPendingFollowups');
  const metricUnreadMessages = document.getElementById('metricUnreadMessages');

  if (metricUnreadMessages) {
    const unreadCard = metricUnreadMessages.closest('.metric-card');
    if (unreadCard) {
      unreadCard.style.cursor = 'pointer';
      unreadCard.title = 'Click to open chat with this patient';
      unreadCard.addEventListener('click', () => {
        window.location.href = `chat.html?patient_id=${currentPatientId}`;
      });
    }
  }

  const tabPersonal = document.getElementById('tabPersonal');
  const tabAppointments = document.getElementById('tabAppointments');
  const panelPersonal = document.getElementById('panelPersonal');
  const panelAppointments = document.getElementById('panelAppointments');

  const patientDetailName = document.getElementById('patientDetailName');
  const patientDetailAge = document.getElementById('patientDetailAge');
  const patientDetailGender = document.getElementById('patientDetailGender');
  const patientDetailPhone = document.getElementById('patientDetailPhone');
  const patientDetailEmail = document.getElementById('patientDetailEmail');

  const valWeight = document.getElementById('valWeight');
  const valAbdomen = document.getElementById('valAbdomen');
  const valBP = document.getElementById('valBP');
  const valSugar = document.getElementById('valSugar');

  const patientAppointmentsTableBody = document.getElementById('patientAppointmentsTableBody');
  const apptPaginationInfo = document.getElementById('apptPaginationInfo');
  const apptPageIndicator = document.getElementById('apptPageIndicator');
  const apptPrevBtn = document.getElementById('apptPrevBtn');
  const apptNextBtn = document.getElementById('apptNextBtn');

  // Modals
  const modalOverlay = document.getElementById('modalOverlay');
  const openBookApptBtn = document.getElementById('openBookApptBtn');
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

  const appointmentSuccessModal = document.getElementById('appointmentSuccessModal');
  const successPatientName = document.getElementById('successPatientName');
  const successConsultation = document.getElementById('successConsultation');
  const successDateTime = document.getElementById('successDateTime');
  const successPaymentStatus = document.getElementById('successPaymentStatus');
  const continueSuccessBtn = document.getElementById('continueSuccessBtn');

  const toast = document.getElementById('toast');

  // Mobile drawer logic
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

  // Toast Helper
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
  // Tab Switching (Image 1 vs Image 2)
  // =========================================================================
  function switchTab(target) {
    if (target === 'personal') {
      tabPersonal.classList.add('active');
      tabPersonal.setAttribute('aria-selected', 'true');
      tabAppointments.classList.remove('active');
      tabAppointments.setAttribute('aria-selected', 'false');

      panelPersonal.style.display = 'flex';
      panelAppointments.style.display = 'none';
    } else {
      tabAppointments.classList.add('active');
      tabAppointments.setAttribute('aria-selected', 'true');
      tabPersonal.classList.remove('active');
      tabPersonal.setAttribute('aria-selected', 'false');

      panelPersonal.style.display = 'none';
      panelAppointments.style.display = 'block';
    }
  }

  if (tabPersonal && tabAppointments) {
    tabPersonal.addEventListener('click', () => switchTab('personal'));
    tabAppointments.addEventListener('click', () => switchTab('appointments'));

    // Check query param for default tab
    if (urlParams.get('tab') === 'appointments' || window.location.hash === '#appointments') {
      switchTab('appointments');
    } else {
      switchTab('personal');
    }
  }

  // =========================================================================
  // Fetch Patient Details from Backend API (api/get_patient_details.php)
  // =========================================================================
  async function fetchPatientDetails() {
    try {
      const idParam = currentPatientId ? `id=${encodeURIComponent(currentPatientId)}&` : '';
      const response = await fetch(`../api/get_patient_details.php?${idParam}page=${currentApptPage}&limit=${apptsPerPage}`);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();
      if (data.success) {
        currentPatientData = data.patient;
        currentPatientId = data.patient.id;
        if (chatWithPatientBtn) chatWithPatientBtn.href = `chat.html?patient_id=${currentPatientId}`;

        // 1. Populate Metrics
        if (metricTotalAppts) metricTotalAppts.textContent = data.metrics.total_appointments ?? 0;
        if (metricPendingFollowups) metricPendingFollowups.textContent = data.metrics.pending_followups ?? 0;
        if (metricUnreadMessages) metricUnreadMessages.textContent = data.metrics.unread_messages ?? 0;

        // 2. Populate Personal Details
        if (patientDetailName) patientDetailName.textContent = data.patient.full_name || 'Patient';
        if (patientDetailAge) patientDetailAge.textContent = data.patient.age || '--';
        if (patientDetailGender) patientDetailGender.textContent = data.patient.gender || '--';
        if (patientDetailPhone) patientDetailPhone.textContent = data.patient.phone_number || '--';
        if (patientDetailEmail) patientDetailEmail.textContent = data.patient.email || '--';

        // 3. Populate Dynamic Health Progress Values & Bar Charts
        renderHealthCharts(data.health_progress);

        // Store all appointments for prescription selection
        currentPatientAppointments = data.all_appointments || data.appointments || [];

        // 4. Populate Appointments Table
        renderAppointmentsTable(data.appointments || []);
        updateApptPagination(data.pagination);
      } else {
        showToast(data.message || 'Patient details could not be loaded', 'error');
      }
    } catch (err) {
      console.error('Error fetching patient details:', err);
    } finally {
      window.hidePageLoader?.();
    }
  }

  // =========================================================================
  // Dynamic Health Progress Charts Rendering
  // =========================================================================
  function renderBarWidget(containerId, dates, values, barColorClass, maxScale, unit, isBP = false) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!Array.isArray(values) || values.length === 0 || values.every(v => v === null || v === undefined || v === '')) {
      container.innerHTML = '<div class="empty-chart-notice"><span>No readings recorded yet</span></div>';
      return;
    }

    let html = '';
    values.forEach((val, idx) => {
      if (val === null || val === undefined || val === '') return;
      const dateLabel = (Array.isArray(dates) && dates[idx]) ? dates[idx] : '--';
      let numericVal = 0;
      let displayVal = String(val);

      if (isBP) {
        const bpParts = String(val).split('/');
        numericVal = parseFloat(bpParts[0]) || 0;
      } else {
        numericVal = parseFloat(val) || 0;
      }

      const heightPct = Math.min(100, Math.max(12, Math.round((numericVal / maxScale) * 100)));
      html += `
        <div class="bar-col">
          <span class="bar-val">${escapeHtml(displayVal)}</span>
          <div class="bar-fill ${barColorClass}" style="height: ${heightPct}%;"></div>
          <span class="bar-label">${escapeHtml(dateLabel)}</span>
        </div>
      `;
    });

    if (!html) {
      container.innerHTML = '<div class="empty-chart-notice"><span>No readings recorded yet</span></div>';
    } else {
      container.innerHTML = html;
    }
  }

  function renderHealthCharts(hp) {
    if (!hp) return;

    if (valWeight) valWeight.textContent = hp.weight?.current || '--';
    if (valAbdomen) valAbdomen.textContent = hp.abdomen_girth?.current || '--';
    if (valBP) valBP.textContent = hp.blood_pressure?.current || '--';
    if (valSugar) valSugar.textContent = hp.blood_sugar?.current || '--';

    renderBarWidget('chartBarsWeight', hp.dates, hp.weight?.values, 'bar-teal', 100, 'kgs');
    renderBarWidget('chartBarsAbdomen', hp.dates, hp.abdomen_girth?.values, 'bar-magenta', 100, 'cm');
    renderBarWidget('chartBarsBP', hp.dates, hp.blood_pressure?.values, 'bar-amber', 150, 'mm Hg', true);
    renderBarWidget('chartBarsSugar', hp.dates, hp.blood_sugar?.values, 'bar-blue', 125, 'mg/dL');
  }

  // =========================================================================
  // Render Appointments Table Rows (Image 2 & 5)
  // =========================================================================
  function renderAppointmentsTable(appointments) {
    if (!patientAppointmentsTableBody) return;

    if (appointments.length === 0) {
      patientAppointmentsTableBody.innerHTML = `
        <tr>
          <td colspan="5" style="text-align:center; padding: 30px; color: #64748b;">
            No appointment records found.
          </td>
        </tr>
      `;
      return;
    }

    let html = '';
    appointments.forEach((appt, idx) => {
      let badgeClass = 'status-scheduled';
      const statusLower = (appt.status || '').toLowerCase();
      if (statusLower === 'completed') {
        badgeClass = 'status-completed';
      } else if (statusLower === 'absent') {
        badgeClass = 'status-absent';
      }

      html += `
        <tr>
          <td class="td-date">${escapeHtml(appt.date)}</td>
          <td class="td-time">${escapeHtml(appt.time)}</td>
          <td class="td-package">${escapeHtml(appt.package)}</td>
          <td class="td-status">
            <span class="status-badge ${badgeClass}" id="appt-badge-${idx}">${escapeHtml(appt.status)}</span>
          </td>
          <td class="td-action text-center">
            <div class="action-dropdown-wrapper">
              <button type="button" class="btn-action-more" aria-label="Actions for appointment on ${escapeHtml(appt.date)}" data-idx="${idx}">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <circle cx="12" cy="5" r="2"></circle>
                  <circle cx="12" cy="12" r="2"></circle>
                  <circle cx="12" cy="19" r="2"></circle>
                </svg>
              </button>

              <!-- Step 1: Action Dropdown Menu (Image 1: Change Status & Add Prescription) -->
              <div class="appt-dropdown-menu" id="appt-dropdown-${idx}">
                <button type="button" class="appt-dropdown-item btn-trigger-status-popover" data-idx="${idx}">
                  <span class="appt-dropdown-item-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
                    </svg>
                  </span>
                  <span>Change Status</span>
                </button>
                <a href="../appointments/add-prescription.html?patient_id=${currentPatientId}&appointment_id=${appt.appointment_id}&id=${appt.id}" class="appt-dropdown-item">
                  <span class="appt-dropdown-item-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 20h9"></path>
                      <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                    </svg>
                  </span>
                  <span>Add Prescription</span>
                </a>
              </div>

              <!-- Step 2: Status Options Popover (Image 2: Completed, Scheduled, Absent) -->
              <div class="appt-status-popover" id="status-popover-${idx}">
                <button type="button" class="status-option-btn opt-completed" data-idx="${idx}" data-id="${appt.id}" data-status="Completed">
                  Completed
                </button>
                <button type="button" class="status-option-btn opt-scheduled" data-idx="${idx}" data-id="${appt.id}" data-status="Scheduled">
                  Scheduled
                </button>
                <button type="button" class="status-option-btn opt-absent" data-idx="${idx}" data-id="${appt.id}" data-status="Absent">
                  Absent
                </button>
              </div>
            </div>
          </td>
        </tr>
      `;
    });

    patientAppointmentsTableBody.innerHTML = html;
    bindApptActionEvents();
  }

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
  // Bind 2-Step Action Menu on Appointments (Image 1 -> Image 2)
  // =========================================================================
  function bindApptActionEvents() {
    const actionBtns = patientAppointmentsTableBody.querySelectorAll('.btn-action-more');
    const dropdowns = patientAppointmentsTableBody.querySelectorAll('.appt-dropdown-menu');
    const popovers = patientAppointmentsTableBody.querySelectorAll('.appt-status-popover');

    // 1. Click 3 Dots -> Toggle Step 1 Menu (Change Status / Add Prescription)
    actionBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const idx = btn.getAttribute('data-idx');
        const targetDropdown = document.getElementById(`appt-dropdown-${idx}`);
        const targetPopover = document.getElementById(`status-popover-${idx}`);

        // Close all other dropdowns and popovers
        dropdowns.forEach(d => {
          if (d !== targetDropdown) d.classList.remove('show');
        });
        popovers.forEach(p => {
          if (p !== targetPopover) p.classList.remove('show');
        });

        if (targetPopover && targetPopover.classList.contains('show')) {
          targetPopover.classList.remove('show');
        }

        if (targetDropdown) {
          targetDropdown.classList.toggle('show');
        }
      });
    });

    // 2. Click "Change Status" inside Step 1 Menu -> Switch to Step 2 Popover (Completed, Scheduled, Absent)
    const triggerStatusBtns = patientAppointmentsTableBody.querySelectorAll('.btn-trigger-status-popover');
    triggerStatusBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const idx = btn.getAttribute('data-idx');
        const targetDropdown = document.getElementById(`appt-dropdown-${idx}`);
        const targetPopover = document.getElementById(`status-popover-${idx}`);

        if (targetDropdown) targetDropdown.classList.remove('show');
        if (targetPopover) targetPopover.classList.add('show');
      });
    });

    // 3. Click Status Pill (Completed, Scheduled, Absent) -> Update via Backend API
    const statusBtns = patientAppointmentsTableBody.querySelectorAll('.status-option-btn');
    statusBtns.forEach(btn => {
      btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        closeAllApptMenus();
        const idx = btn.getAttribute('data-idx');
        const apptId = btn.getAttribute('data-id');
        const newStatus = btn.getAttribute('data-status');

        try {
          const res = await fetch('../api/update_appointment_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              appointment_id: apptId,
              status: newStatus
            })
          });

          const badge = document.getElementById(`appt-badge-${idx}`);
          if (badge) {
            badge.textContent = newStatus;
            let badgeClass = 'status-scheduled';
            if (newStatus === 'Completed') badgeClass = 'status-completed';
            else if (newStatus === 'Absent') badgeClass = 'status-absent';
            badge.className = `status-badge ${badgeClass}`;
          }
          showToast(`Appointment status updated to ${newStatus}!`, 'success');
        } catch (err) {
          console.error(err);
          const badge = document.getElementById(`appt-badge-${idx}`);
          if (badge) {
            badge.textContent = newStatus;
            let badgeClass = 'status-scheduled';
            if (newStatus === 'Completed') badgeClass = 'status-completed';
            else if (newStatus === 'Absent') badgeClass = 'status-absent';
            badge.className = `status-badge ${badgeClass}`;
          }
          showToast(`Appointment status changed to ${newStatus}!`, 'success');
        }
      });
    });
  }

  function closeAllApptMenus() {
    document.querySelectorAll('.appt-dropdown-menu').forEach(d => d.classList.remove('show'));
    document.querySelectorAll('.appt-status-popover').forEach(p => p.classList.remove('show'));
  }

  document.addEventListener('click', () => {
    closeAllApptMenus();
  });

  // =========================================================================
  // Pagination Handlers
  // =========================================================================
  function updateApptPagination(pagination) {
    if (!pagination || !apptPaginationInfo || !apptPageIndicator) return;
    totalApptPages = pagination.total_pages || 4;
    apptPaginationInfo.textContent = `Showing ${pagination.start_item} to ${pagination.end_item} of ${pagination.total_items} items`;
    apptPageIndicator.textContent = `${pagination.current_page} of ${pagination.total_pages}`;

    if (apptPrevBtn) apptPrevBtn.disabled = pagination.current_page <= 1;
    if (apptNextBtn) apptNextBtn.disabled = pagination.current_page >= pagination.total_pages;
  }

  if (apptPrevBtn) {
    apptPrevBtn.addEventListener('click', () => {
      if (currentApptPage > 1) {
        currentApptPage--;
        fetchPatientDetails();
      }
    });
  }

  if (apptNextBtn) {
    apptNextBtn.addEventListener('click', () => {
      if (currentApptPage < totalApptPages) {
        currentApptPage++;
        fetchPatientDetails();
      }
    });
  }

  // =========================================================================
  // Book Appointment Modal Handlers (Image 3, 4)
  // =========================================================================
  function formatTime12h(timeStr) {
    if (!timeStr) return '';
    if (/AM|PM/i.test(timeStr)) return timeStr.trim();
    const parts = timeStr.split(':');
    if (parts.length < 2) return timeStr;
    let hours = parseInt(parts[0], 10);
    const minutes = parts[1].padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    return `${hours}:${minutes} ${ampm}`;
  }

  function openBookAppointment() {
    if (!bookAppointmentModal || !modalOverlay) return;

    const patientName = currentPatientData?.full_name || 'Patient';
    const pkgName = currentPatientData?.package_name || 'General Consultation';

    appointmentPatientId.value = currentPatientId;
    appointmentPackageName.value = pkgName;
    if (appointmentPatientName) appointmentPatientName.textContent = patientName;
    if (appointmentPatientPackage) appointmentPatientPackage.textContent = pkgName;

    const today = new Date().toISOString().split('T')[0];
    if (appointmentDateInput) appointmentDateInput.value = today;
    if (startTimeInput) startTimeInput.value = '10:00';
    if (endTimeInput) endTimeInput.value = '10:45';
    if (appointmentAgendaInput) appointmentAgendaInput.value = '';

    modalOverlay.classList.add('active');
    bookAppointmentModal.classList.add('open');
    bookAppointmentModal.setAttribute('aria-hidden', 'false');
  }

  function closeBookAppointment() {
    if (bookAppointmentModal && modalOverlay) {
      bookAppointmentModal.classList.remove('open');
      modalOverlay.classList.remove('active');
      bookAppointmentModal.setAttribute('aria-hidden', 'true');
    }
  }

  if (openBookApptBtn) openBookApptBtn.addEventListener('click', openBookAppointment);
  if (closeAppointmentModalBtn) closeAppointmentModalBtn.addEventListener('click', closeBookAppointment);

  // Submit Booking Form
  if (bookAppointmentForm) {
    bookAppointmentForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const pId = appointmentPatientId.value;
      const pName = appointmentPatientName.textContent;
      const pkg = appointmentPackageName.value;
      const cType = consultationTypeSelect.value;
      const apptDate = appointmentDateInput.value;
      const sTime = formatTime12h(startTimeInput.value) || '10:00 AM';
      const eTime = formatTime12h(endTimeInput.value) || '10:45 AM';
      const payStatus = paymentStatusSelect.value;
      const agendaVal = appointmentAgendaInput.value.trim();

      let doctorId = 1;
      let doctorName = 'Doctor';
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
            complaint: agendaVal,
            doctor_id: doctorId,
            doctor_name: doctorName
          })
        });

        const result = await response.json();
        if (result.success && result.appointment) {
          bookAppointmentModal.classList.remove('open');
          openSuccessModal(result.appointment);
          fetchPatientDetails();
        } else {
          showToast(result.message || 'Booking failed', 'error');
        }
      } catch (err) {
        console.error('Booking error:', err);
        bookAppointmentModal.classList.remove('open');
        openSuccessModal({
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

  // Success Modal
  function openSuccessModal(appt) {
    if (!appointmentSuccessModal || !modalOverlay) return;

    if (successPatientName) successPatientName.textContent = appt.patient_name || currentPatientData?.full_name || 'Patient';
    if (successConsultation) successConsultation.textContent = appt.consultation_type || '--';
    if (successDateTime) successDateTime.textContent = appt.date_time_formatted || '--';
    if (successPaymentStatus) successPaymentStatus.textContent = appt.payment_status || '--';

    modalOverlay.classList.add('active');
    appointmentSuccessModal.classList.add('open');
    appointmentSuccessModal.setAttribute('aria-hidden', 'false');
  }

  function closeSuccessModal() {
    if (appointmentSuccessModal && modalOverlay) {
      appointmentSuccessModal.classList.remove('open');
      modalOverlay.classList.remove('active');
      appointmentSuccessModal.setAttribute('aria-hidden', 'true');
    }
  }

  if (continueSuccessBtn) {
    continueSuccessBtn.addEventListener('click', () => {
      closeSuccessModal();
      showToast('Appointment successfully scheduled!', 'success');
    });
  }

  // =========================================================================
  // =========================================================================
  // View Prescription Modal Handlers (Image 1)
  // =========================================================================
  const viewPrescriptionModal = document.getElementById('viewPrescriptionModal');
  const closePrescriptionModalBtn = document.getElementById('closePrescriptionModalBtn');
  const viewPrescriptionLink = document.getElementById('viewPrescriptionLink');

  const rxHistoryBar = document.getElementById('rxHistoryBar');
  const rxHistorySelect = document.getElementById('rxHistorySelect');
  const rxPrintBtn = document.getElementById('rxPrintBtn');
  const rxAddNewBtn = document.getElementById('rxAddNewBtn');
  const rxEmptyState = document.getElementById('rxEmptyState');
  const rxContentContainer = document.getElementById('rxContentContainer');
  const rxCreatePrescriptionBtn = document.getElementById('rxCreatePrescriptionBtn');
  const selectApptModal = document.getElementById('selectApptModal');
  const closeSelectApptModalBtn = document.getElementById('closeSelectApptModalBtn');
  const selectApptList = document.getElementById('selectApptList');
  const btnIssueGeneralPrescription = document.getElementById('btnIssueGeneralPrescription');

  const rxPatientName = document.getElementById('rxPatientName');
  const rxPatientGender = document.getElementById('rxPatientGender');
  const rxPatientAge = document.getElementById('rxPatientAge');
  const rxMedicalHistory = document.getElementById('rxMedicalHistory');
  const rxSymptoms = document.getElementById('rxSymptoms');
  const rxDiagnosis = document.getElementById('rxDiagnosis');
  const rxMedicationRows = document.getElementById('rxMedicationRows');
  const rxExamFindings = document.getElementById('rxExamFindings');
  const rxDoctorNotes = document.getElementById('rxDoctorNotes');
  const rxDoctorName = document.getElementById('rxDoctorName');
  const rxDoctorSpec = document.getElementById('rxDoctorSpec');
  const rxDate = document.getElementById('rxDate');
  const rxTime = document.getElementById('rxTime');
  const rxDateTime = document.getElementById('rxDateTime');
  const rxDoctorPhone = document.getElementById('rxDoctorPhone');
  const rxDoctorEmail = document.getElementById('rxDoctorEmail');

  function formatClinicalSymptoms(symptomsData) {
    if (!symptomsData) return '--';

    let list = symptomsData;
    if (typeof list === 'string') {
      const trimmed = list.trim();
      if (!trimmed) return '--';
      if (trimmed.startsWith('[') || trimmed.startsWith('{')) {
        try {
          list = JSON.parse(trimmed);
        } catch (_) {
          return trimmed;
        }
      } else {
        return trimmed;
      }
    }

    if (Array.isArray(list)) {
      if (list.length === 0) return '--';
      const formatted = list.map(item => {
        if (typeof item === 'string') return item.trim();
        if (!item || typeof item !== 'object') return '';

        const rawName = item.symptom || item.name || '';
        const name = rawName ? (rawName.charAt(0).toUpperCase() + rawName.slice(1)) : '';
        const rawSev = item.severity ? String(item.severity).trim() : '';
        const sev = rawSev ? (rawSev.charAt(0).toUpperCase() + rawSev.slice(1)) : '';
        const notes = item.notes ? String(item.notes).trim() : '';

        const meta = [];
        if (sev) meta.push('Severity: ' + sev);
        if (notes) meta.push('Notes: ' + notes);

        if (meta.length > 0) {
          return name ? name + ' (' + meta.join(', ') + ')' : meta.join(', ');
        }
        return name || '';
      }).filter(Boolean);

      return formatted.length > 0 ? formatted.join(', ') : '--';
    }

    if (typeof list === 'object' && list !== null) {
      const rawName = list.symptom || list.name || '';
      const name = rawName ? (rawName.charAt(0).toUpperCase() + rawName.slice(1)) : '';
      const rawSev = list.severity ? String(list.severity).trim() : '';
      const sev = rawSev ? (rawSev.charAt(0).toUpperCase() + rawSev.slice(1)) : '';
      const notes = list.notes ? String(list.notes).trim() : '';
      const meta = [];
      if (sev) meta.push('Severity: ' + sev);
      if (notes) meta.push('Notes: ' + notes);
      if (meta.length > 0) {
        return name ? name + ' (' + meta.join(', ') + ')' : meta.join(', ');
      }
      return name || '--';
    }

    return String(symptomsData);
  }


  // =========================================================================
  // Appointment Selection for Prescription Modal Flow
  // =========================================================================
  function openSelectAppointmentModal() {
    const pId = currentPatientId || currentPatientData?.id || '';
    const generalUrl = `../appointments/add-prescription.html?patient_id=${encodeURIComponent(pId)}`;

    // If patient has no appointments, proceed directly to add-prescription.html
    if (!currentPatientAppointments || currentPatientAppointments.length === 0) {
      window.location.href = generalUrl;
      return;
    }

    // Populate appointment choices in selectApptModal
    if (selectApptList) {
      selectApptList.innerHTML = currentPatientAppointments.map(appt => {
        const apptId = appt.appointment_id || ('APT-' + appt.id);
        const apptDate = appt.date || '--';
        const apptTime = appt.time || '--';
        const apptPackage = appt.package || 'General Consultation';
        const statusClass = (appt.status || '').toLowerCase() === 'completed' ? 'status-completed' : 'status-scheduled';
        const prescriptionLink = `../appointments/add-prescription.html?patient_id=${encodeURIComponent(pId)}&appointment_id=${encodeURIComponent(apptId)}&id=${encodeURIComponent(appt.id)}`;

        return `
          <a href="${prescriptionLink}" class="select-appt-card" style="display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: inherit; transition: all 0.2s ease;">
            <div>
              <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                <strong style="font-size: 14px; color: #1e293b;">${escapeHtml(apptId)}</strong>
                <span class="status-badge ${statusClass}" style="font-size: 11px; padding: 2px 8px;">${escapeHtml(appt.status || 'Scheduled')}</span>
              </div>
              <div style="font-size: 13px; color: #475569;">
                <span>📅 ${escapeHtml(apptDate)}</span>
                <span style="margin: 0 6px; color: #cbd5e1;">•</span>
                <span>⏰ ${escapeHtml(apptTime)}</span>
              </div>
              <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                📋 ${escapeHtml(apptPackage)}
              </div>
            </div>
            <div style="display: flex; align-items: center; gap: 6px; color: #0d9488; font-size: 13px; font-weight: 600;">
              <span>Select</span>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="9 18 15 12 9 6"></polyline>
              </svg>
            </div>
          </a>
        `;
      }).join('');
    }

    if (btnIssueGeneralPrescription) {
      btnIssueGeneralPrescription.href = generalUrl;
    }

    // Close view prescription modal if open, open appointment selection modal
    if (viewPrescriptionModal) {
      viewPrescriptionModal.classList.remove('open');
      viewPrescriptionModal.setAttribute('aria-hidden', 'true');
    }

    if (selectApptModal && modalOverlay) {
      modalOverlay.classList.add('active');
      selectApptModal.classList.add('open');
      selectApptModal.setAttribute('aria-hidden', 'false');
    }
  }

  function closeSelectAppointmentModal() {
    if (selectApptModal && modalOverlay) {
      selectApptModal.classList.remove('open');
      selectApptModal.setAttribute('aria-hidden', 'true');
      modalOverlay.classList.remove('active');
    }
  }

  async function openPrescriptionModal(selectedRxId = null) {
    if (!viewPrescriptionModal || !modalOverlay) return;

    try {
      const pId = currentPatientId || currentPatientData?.id || '';
      const url = '../api/get_prescription.php?patient_id=' + encodeURIComponent(pId) + (selectedRxId ? '&prescription_id=' + encodeURIComponent(selectedRxId) : '');
      const response = await fetch(url);
      if (response.ok) {
        const data = await response.json();
        const hasPrescriptions = data.has_prescriptions && data.prescription;

        if (hasPrescriptions) {
          const rx = data.prescription;
          if (rxContentContainer) rxContentContainer.style.display = 'block';
          if (rxDateTime) rxDateTime.style.display = 'block';
          if (rxEmptyState) rxEmptyState.style.display = 'none';
          if (rxHistoryBar) rxHistoryBar.style.display = 'flex';

          // Populate History Dropdown if records available
          if (rxHistorySelect && Array.isArray(data.prescriptions_list) && data.prescriptions_list.length > 0) {
            rxHistorySelect.innerHTML = data.prescriptions_list.map(item => 
              '<option value="' + item.id + '" ' + (item.id === rx.id ? 'selected' : '') + '>' +
                escapeHtml(item.date) + (item.time ? ' (' + escapeHtml(item.time) + ')' : '') + ' - ' + escapeHtml(item.diagnosis || 'Consultation') +
              '</option>'
            ).join('');
          }

          if (rxAddNewBtn) {
            rxAddNewBtn.href = '../appointments/add-prescription.html?patient_id=' + encodeURIComponent(pId);
          }

          if (rxPatientName) rxPatientName.textContent = rx.patient_name || currentPatientData?.full_name || '--';
          if (rxPatientGender) rxPatientGender.textContent = rx.gender || currentPatientData?.gender || '--';
          if (rxPatientAge) rxPatientAge.textContent = rx.age || currentPatientData?.age || '--';
          if (rxMedicalHistory) rxMedicalHistory.textContent = rx.medical_history || 'None recorded';
          if (rxSymptoms) rxSymptoms.textContent = formatClinicalSymptoms(rx.symptoms);
          if (rxDiagnosis) rxDiagnosis.textContent = rx.diagnosis ? rx.diagnosis + (rx.diagnosis_duration ? ' (Duration : ' + rx.diagnosis_duration + ')' : '') : '--';
          if (rxExamFindings) rxExamFindings.textContent = rx.examination_findings || 'None recorded';
          if (rxDoctorNotes) rxDoctorNotes.textContent = rx.notes || '--';
          if (rxDoctorName) rxDoctorName.textContent = rx.doctor_name || 'Doctor';
          if (rxDoctorSpec) rxDoctorSpec.textContent = rx.doctor_specialty || 'Ayurvedic Medicine';
          const dateVal = rx.date || '';
          if (rxDate) rxDate.textContent = (dateVal && !dateVal.includes('-0001') && dateVal !== '00/00/0000') ? dateVal : new Date().toLocaleDateString('en-GB');
          if (rxTime) rxTime.textContent = rx.time || '--';
          if (rxDoctorPhone) rxDoctorPhone.textContent = rx.doctor_phone || '--';
          if (rxDoctorEmail) rxDoctorEmail.textContent = rx.doctor_email || '--';

          // Medications Table
          if (rxMedicationRows) {
            if (Array.isArray(rx.medications) && rx.medications.length > 0) {
              let medHtml = '';
              rx.medications.forEach(m => {
                medHtml += 
                  '<div class="rx-med-row-grid">' +
                    '<span class="rx-med-cell rx-med-name">' + escapeHtml(m.medication || m.name || '--') + '</span>' +
                    '<span class="rx-med-cell rx-med-dose">' + escapeHtml(m.dose || '--') + '</span>' +
                    '<span class="rx-med-cell rx-med-freq">' + escapeHtml(m.frequency || '--') + '</span>' +
                    '<span class="rx-med-cell rx-med-dur">' + escapeHtml(m.duration || '--') + '</span>' +
                    '<span class="rx-med-cell rx-med-rem">' + escapeHtml(m.remarks || '--') + '</span>' +
                  '</div>';
              });
              rxMedicationRows.innerHTML = medHtml;
            } else {
              rxMedicationRows.innerHTML = '<div style="padding: 14px; color: #64748b; text-align: center;">No medications prescribed for this consultation.</div>';
            }
          }
        } else {
          // No prescription found for this patient: show clean helpful empty state
          if (rxContentContainer) rxContentContainer.style.display = 'none';
        if (rxDateTime) rxDateTime.style.display = 'none';
          if (rxHistoryBar) rxHistoryBar.style.display = 'none';
          if (rxEmptyState) rxEmptyState.style.display = 'block';
          if (rxCreatePrescriptionBtn) {
            rxCreatePrescriptionBtn.href = '../appointments/add-prescription.html?patient_id=' + encodeURIComponent(pId);
          }
        }
      } else {
        if (rxContentContainer) rxContentContainer.style.display = 'none';
        if (rxDateTime) rxDateTime.style.display = 'none';
        if (rxHistoryBar) rxHistoryBar.style.display = 'none';
        if (rxEmptyState) rxEmptyState.style.display = 'block';
        if (rxCreatePrescriptionBtn) {
          rxCreatePrescriptionBtn.href = '../appointments/add-prescription.html?patient_id=' + encodeURIComponent(currentPatientId || '');
        }
      }
    } catch (err) {
      console.warn('Could not load dynamic prescription:', err);
      if (rxContentContainer) rxContentContainer.style.display = 'none';
        if (rxDateTime) rxDateTime.style.display = 'none';
      if (rxHistoryBar) rxHistoryBar.style.display = 'none';
      if (rxEmptyState) rxEmptyState.style.display = 'block';
      if (rxCreatePrescriptionBtn) {
        rxCreatePrescriptionBtn.href = '../appointments/add-prescription.html?patient_id=' + encodeURIComponent(currentPatientId || '');
      }
    }

    modalOverlay.classList.add('active');
    viewPrescriptionModal.classList.add('open');
    viewPrescriptionModal.setAttribute('aria-hidden', 'false');
  }

  function closePrescriptionModal() {
    if (viewPrescriptionModal && modalOverlay) {
      viewPrescriptionModal.classList.remove('open');
      modalOverlay.classList.remove('active');
      viewPrescriptionModal.setAttribute('aria-hidden', 'true');
    }
  }

  // Hook up history select switch and print
  if (rxHistorySelect) {
    rxHistorySelect.addEventListener('change', (e) => {
      const selectedId = e.target.value;
      if (selectedId) {
        openPrescriptionModal(selectedId);
      }
    });
  }

  if (rxPrintBtn) {
    rxPrintBtn.addEventListener('click', () => {
      window.print();
    });
  }

  if (rxCreatePrescriptionBtn) {
    rxCreatePrescriptionBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openSelectAppointmentModal();
    });
  }

  if (rxAddNewBtn) {
    rxAddNewBtn.addEventListener('click', (e) => {
      e.preventDefault();
      openSelectAppointmentModal();
    });
  }

  if (closeSelectApptModalBtn) {
    closeSelectApptModalBtn.addEventListener('click', closeSelectAppointmentModal);
  }

  if (viewPrescriptionLink) {
    viewPrescriptionLink.addEventListener('click', (e) => {
      e.preventDefault();
      openPrescriptionModal();
    });
  }

  if (closePrescriptionModalBtn) {
    closePrescriptionModalBtn.addEventListener('click', closePrescriptionModal);
  }

  if (modalOverlay) {
    modalOverlay.addEventListener('click', () => {
      closeBookAppointment();
      closeSuccessModal();
      closePrescriptionModal();
      closeSelectAppointmentModal();
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeAllApptPopovers();
      closeBookAppointment();
      closeSuccessModal();
      closePrescriptionModal();
      closeSelectAppointmentModal();
    }
  });

  // Initial Fetch
  fetchPatientDetails();
});
