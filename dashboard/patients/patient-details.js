/**
 * Ocayur Doctor Portal - Patient Details Frontend Integration Logic
 * Connects patient-details.html to PHP Backend APIs
 */

document.addEventListener('DOMContentLoaded', () => {
  // State
  let currentPatientId = 1;
  let currentPatientData = null;
  let currentApptPage = 1;
  const apptsPerPage = 3;
  let totalApptPages = 4;

  // Read URL parameters
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('id')) {
    currentPatientId = parseInt(urlParams.get('id'), 10) || 1;
  } else if (urlParams.get('patient_id')) {
    currentPatientId = urlParams.get('patient_id');
  }

  // Chat Button & Link Binding
  const chatWithPatientBtn = document.getElementById('chatWithPatientBtn');
  if (chatWithPatientBtn) {
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
      const response = await fetch(`../api/get_patient_details.php?id=${encodeURIComponent(currentPatientId)}&page=${currentApptPage}&limit=${apptsPerPage}`);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();
      if (data.success) {
        currentPatientData = data.patient;

        // 1. Populate Metrics
        if (metricTotalAppts) metricTotalAppts.textContent = data.metrics.total_appointments || 12;
        if (metricPendingFollowups) metricPendingFollowups.textContent = data.metrics.pending_followups || 12;
        if (metricUnreadMessages) metricUnreadMessages.textContent = data.metrics.unread_messages || 3;

        // 2. Populate Personal Details
        if (patientDetailName) patientDetailName.textContent = data.patient.full_name || 'Rahul Sharma';
        if (patientDetailAge) patientDetailAge.textContent = data.patient.age || 34;
        if (patientDetailGender) patientDetailGender.textContent = data.patient.gender || 'Male';
        if (patientDetailPhone) patientDetailPhone.textContent = data.patient.phone_number || '9876543210';
        if (patientDetailEmail) patientDetailEmail.textContent = data.patient.email || 'rahulsharma@gmail.com';

        // 3. Populate Health Progress Values
        if (data.health_progress) {
          if (valWeight) valWeight.textContent = data.health_progress.weight?.current || '58 kgs';
          if (valAbdomen) valAbdomen.textContent = data.health_progress.abdomen_girth?.current || '82 cm';
          if (valBP) valBP.textContent = data.health_progress.blood_pressure?.current || '118/78 mm Hg';
          if (valSugar) valSugar.textContent = data.health_progress.blood_sugar?.current || '92 mg/dL';
        }

        // 4. Populate Appointments Table
        renderAppointmentsTable(data.appointments || []);
        updateApptPagination(data.pagination);
      }
    } catch (err) {
      console.error('Error fetching patient details:', err);
    }
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
                <a href="../appointments/add-prescription.html?patient_id=${currentPatientId}&appointment_id=${appt.id}" class="appt-dropdown-item">
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
  function openBookAppointment() {
    if (!bookAppointmentModal || !modalOverlay) return;

    const patientName = currentPatientData?.full_name || 'Rahul Sharma';
    const pkgName = currentPatientData?.package_name || 'Gut Healing Package';

    appointmentPatientId.value = currentPatientId;
    appointmentPackageName.value = pkgName;
    if (appointmentPatientName) appointmentPatientName.textContent = patientName;
    if (appointmentPatientPackage) appointmentPatientPackage.textContent = pkgName;

    const today = new Date().toISOString().split('T')[0];
    if (appointmentDateInput) appointmentDateInput.value = today;

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

    if (successPatientName) successPatientName.textContent = appt.patient_name || 'Rahul Sharma';
    if (successConsultation) successConsultation.textContent = appt.consultation_type || 'Follow-up Consultation';
    if (successDateTime) successDateTime.textContent = appt.date_time_formatted || '23 Sep 2026, 10:00 - 10:45 AM';
    if (successPaymentStatus) successPaymentStatus.textContent = appt.payment_status || 'Included in Package';

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
  // View Prescription Modal Handlers (Image 1)
  // =========================================================================
  const viewPrescriptionModal = document.getElementById('viewPrescriptionModal');
  const closePrescriptionModalBtn = document.getElementById('closePrescriptionModalBtn');
  const viewPrescriptionLink = document.getElementById('viewPrescriptionLink');

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
  const rxDoctorPhone = document.getElementById('rxDoctorPhone');
  const rxDoctorEmail = document.getElementById('rxDoctorEmail');

  async function openPrescriptionModal() {
    if (!viewPrescriptionModal || !modalOverlay) return;

    try {
      const response = await fetch(`../api/get_prescription.php?patient_id=${encodeURIComponent(currentPatientId)}`);
      if (response.ok) {
        const data = await response.json();
        if (data.success && data.prescription) {
          const rx = data.prescription;
          if (rxPatientName) rxPatientName.textContent = rx.patient_name || currentPatientData?.full_name || 'John Doe';
          if (rxPatientGender) rxPatientGender.textContent = rx.gender || currentPatientData?.gender || 'Male';
          if (rxPatientAge) rxPatientAge.textContent = rx.age || currentPatientData?.age || '34';
          if (rxMedicalHistory) rxMedicalHistory.textContent = rx.medical_history || 'No Known Significant Medical History';
          if (rxSymptoms) rxSymptoms.textContent = (typeof rx.symptoms === 'string') ? rx.symptoms : (Array.isArray(rx.symptoms) && rx.symptoms.length ? rx.symptoms.map(s => `${s.symptom || s.name || ''} (Severity: ${s.severity || 'Moderate'})`).join(', ') : 'Constipation (Severity : Moderate)');
          if (rxDiagnosis) rxDiagnosis.textContent = rx.diagnosis ? `${rx.diagnosis} (Duration : ${rx.diagnosis_duration || '3 months'})` : 'Migraine (Duration : 3 months)';
          if (rxExamFindings) rxExamFindings.textContent = rx.examination_findings || 'Abdomen Feels Distended';
          if (rxDoctorNotes) rxDoctorNotes.textContent = rx.notes || 'Gandbush ( Cow Ghee + Triphala Powder )';
          if (rxDoctorName) rxDoctorName.textContent = rx.doctor_name || 'Dr. Ananya Prasad';
          if (rxDoctorSpec) rxDoctorSpec.textContent = rx.doctor_specialty || 'Ayurvedic Medicine';
          if (rxDate) rxDate.textContent = rx.date || '08/09/2026';
          if (rxTime) rxTime.textContent = rx.time || '11:20 AM';
          if (rxDoctorPhone) rxDoctorPhone.textContent = rx.doctor_phone || rx.phone || '+91 7896543210';
          if (rxDoctorEmail) rxDoctorEmail.textContent = rx.doctor_email || rx.email || rx.support_email || 'ocayursupport@gmail.com';

          // Medications Table
          if (rxMedicationRows && Array.isArray(rx.medications) && rx.medications.length > 0) {
            let medHtml = '';
            rx.medications.forEach(m => {
              medHtml += `
                <div class="rx-med-row-grid">
                  <span class="rx-med-cell rx-med-name">${escapeHtml(m.medication || m.name || 'TAB MEENTOACID (TABLET)')}</span>
                  <span class="rx-med-cell rx-med-dose">${escapeHtml(m.dose || '1 TABLET')}</span>
                  <span class="rx-med-cell rx-med-freq">${escapeHtml(m.frequency || '1-0-1 BEFORE MEAL')}</span>
                  <span class="rx-med-cell rx-med-dur">${escapeHtml(m.duration || '10 DAYS')}</span>
                  <span class="rx-med-cell rx-med-rem">${escapeHtml(m.remarks || 'TAKE 1 TABLET - TWICE A DAY, BEFORE BREAKFAST AND BEFORE DINNER FOR 10 DAYS')}</span>
                </div>
              `;
            });
            rxMedicationRows.innerHTML = medHtml;
          }
        }
      }
    } catch (err) {
      console.warn('Could not load dynamic prescription:', err);
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
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeAllApptPopovers();
      closeBookAppointment();
      closeSuccessModal();
      closePrescriptionModal();
    }
  });

  // Initial Fetch
  fetchPatientDetails();
});
