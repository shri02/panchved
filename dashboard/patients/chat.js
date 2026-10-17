/**
 * Ocayur Doctor Portal - Chat System Frontend Logic
 * Connects chat.html to ../api/chat.php
 */

document.addEventListener('DOMContentLoaded', () => {
  // State
  let activePatientId = 1;
  let allPatients = [];
  let currentMessages = [];
  let isSending = false;

  // DOM Elements
  const chatPatientsList = document.getElementById('chatPatientsList');
  const patientCountBadge = document.getElementById('patientCountBadge');
  const patientFilterInput = document.getElementById('patientFilterInput');

  const activePatientAvatar = document.getElementById('activePatientAvatar');
  const activePatientName = document.getElementById('activePatientName');
  const activePatientIdEl = document.getElementById('activePatientId');
  const activePatientPackage = document.getElementById('activePatientPackage');
  const activePatientStatus = document.getElementById('activePatientStatus');

  const chatMessagesContainer = document.getElementById('chatMessagesContainer');
  const chatInputForm = document.getElementById('chatInputForm');
  const chatTextInput = document.getElementById('chatTextInput');
  const btnSendMessage = document.getElementById('btnSendMessage');
  const btnManualRefresh = document.getElementById('btnManualRefresh');
  const refreshTimestampText = document.getElementById('refreshTimestampText');
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
    }, 3000);
  }

  // Safe HTML helper
  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  // Parse patient_id from URL query params
  const urlParams = new URLSearchParams(window.location.search);
  const paramId = parseInt(urlParams.get('patient_id') || urlParams.get('id'), 10);
  if (paramId && paramId > 0) {
    activePatientId = paramId;
  }

  // Load Doctor Profile into Header if available
  let currentDoctorId = 0;
  let currentDoctorName = '';
  try {
    const docData = JSON.parse((localStorage.getItem('ocayur_doctor') || localStorage.getItem('panchved_doctor')) || '{}');
    if (docData.id || docData.doctor_id) {
      currentDoctorId = parseInt(docData.id || docData.doctor_id, 10) || 0;
    }
    if (docData.full_name || docData.name) {
      currentDoctorName = docData.full_name || docData.name || '';
      const dName = document.getElementById('doctorNameHeader');
      if (dName) dName.textContent = currentDoctorName;
    }
    if (docData.expertise || docData.role) {
      const dRole = document.getElementById('doctorRoleHeader');
      if (dRole) dRole.textContent = docData.expertise || docData.role;
    }
  } catch (_) {}

  // =========================================================================
  // 1. Fetch Conversations / Patients Directory
  // =========================================================================
  async function loadPatientsDirectory() {
    try {
      const qParams = new URLSearchParams();
      qParams.append('action', 'patients_list');
      if (currentDoctorId > 0) qParams.append('doctor_id', currentDoctorId);
      if (currentDoctorName) qParams.append('doctor_name', currentDoctorName);
      if (activePatientId > 0) qParams.append('target_patient_id', activePatientId);

      const res = await fetch(`../api/chat.php?${qParams.toString()}`);
      const data = await res.json();

      if (data.doctor) {
        if (!currentDoctorId && data.doctor.id) currentDoctorId = parseInt(data.doctor.id, 10);
        const dName = document.getElementById('doctorNameHeader');
        if (dName && data.doctor.full_name) dName.textContent = data.doctor.full_name;
        const dRole = document.getElementById('doctorRoleHeader');
        if (dRole && data.doctor.expertise) dRole.textContent = data.doctor.expertise;
      }

      if (data.table_missing) {
        chatMessagesContainer.innerHTML = `
          <div class="chat-empty-state">
            <div class="chat-empty-icon" style="background: #FDF2F2; color: #D32F2F;">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <h3 style="color: #25231F; margin: 0 0 8px;">Database Table Required</h3>
            <p style="font-size: 0.88rem; color: #5C564D; max-width: 480px; margin: 0 auto 16px;">
              The <code>chat_messages</code> table has not been created yet in the database.
            </p>
            <div style="background: #FAF6EE; padding: 12px 16px; border-radius: 8px; font-family: monospace; font-size: 0.8rem; text-align: left; max-width: 520px; border: 1px solid #ECE5D9;">
              CREATE TABLE IF NOT EXISTS \`chat_messages\` (<br>
              &nbsp;&nbsp;\`id\` INT(11) NOT NULL AUTO_INCREMENT,<br>
              &nbsp;&nbsp;\`doctor_id\` INT(11) NOT NULL,<br>
              &nbsp;&nbsp;\`patient_id\` INT(11) NOT NULL,<br>
              &nbsp;&nbsp;\`sender_type\` ENUM('doctor', 'patient') NOT NULL,<br>
              &nbsp;&nbsp;\`message\` TEXT NOT NULL,<br>
              &nbsp;&nbsp;\`is_seen\` TINYINT(1) NOT NULL DEFAULT 0,<br>
              &nbsp;&nbsp;\`seen_at\` DATETIME DEFAULT NULL,<br>
              &nbsp;&nbsp;\`created_at\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,<br>
              &nbsp;&nbsp;PRIMARY KEY (\`id\`)<br>
              );
            </div>
          </div>
        `;
        return;
      }

      if (data.success && Array.isArray(data.patients)) {
        allPatients = data.patients;
        if (patientCountBadge) {
          patientCountBadge.textContent = `${allPatients.length} Patients`;
        }

        renderPatientsList(allPatients);

        if (allPatients.length > 0) {
          // If active patient is not in list, set to first patient
          const hasActive = allPatients.some(p => p.id === activePatientId);
          if (!hasActive) {
            activePatientId = allPatients[0].id;
          }

          // Load active conversation
          loadChatThread(activePatientId);
        } else {
          // No patients
          if (activePatientName) activePatientName.textContent = 'No Patients';
          if (chatMessagesContainer) {
            chatMessagesContainer.innerHTML = `
              <div class="chat-empty-state">
                <div class="chat-empty-icon">
                  <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                  </svg>
                </div>
                <h3 style="font-size: 1.05rem; color: #25231F; margin: 0 0 4px;">No Assigned Patients</h3>
                <p style="font-size: 0.85rem; color: #5C564D; margin: 0;">You do not have any patient consultations or messages assigned to you yet.</p>
              </div>
            `;
          }
        }
      } else if (!data.success) {
        chatPatientsList.innerHTML = `
          <li style="padding: 24px; text-align: center; color: #D32F2F; font-size: 0.85rem;">
            ${escapeHtml(data.message || 'Failed to load patient conversations.')}
          </li>
        `;
      }
    } catch (err) {
      console.error('Error loading patients:', err);
      chatPatientsList.innerHTML = `
        <li style="padding: 24px; text-align: center; color: #D32F2F; font-size: 0.85rem;">
          Failed to load patient conversations. Please try again.
        </li>
      `;
    }
  }

  // Render patients in sidebar
  function renderPatientsList(patients) {
    if (!chatPatientsList) return;

    if (patients.length === 0) {
      chatPatientsList.innerHTML = `
        <li style="padding: 24px; text-align: center; color: #8E877B; font-size: 0.85rem;">
          No matching patients found.
        </li>
      `;
      return;
    }

    chatPatientsList.innerHTML = patients.map(p => {
      const isActive = (p.id === activePatientId) ? 'active' : '';
      const unreadBadgeHtml = (p.unread_count > 0)
        ? `<span class="patient-unread-badge">${p.unread_count}</span>`
        : '';
      const displaySnippet = p.last_message || 'Start a conversation';

      return `
        <li class="patient-conv-item ${isActive}" data-id="${p.id}">
          <div class="patient-conv-avatar">${escapeHtml(p.initials || 'PT')}</div>
          <div class="patient-conv-info">
            <div class="patient-conv-top">
              <span class="patient-conv-name">${escapeHtml(p.full_name)}</span>
              <span class="patient-conv-time">${escapeHtml(p.last_message_time || '')}</span>
            </div>
            <div class="patient-conv-bottom">
              <p class="patient-conv-snippet">${escapeHtml(displaySnippet)}</p>
              ${unreadBadgeHtml}
            </div>
          </div>
        </li>
      `;
    }).join('');

    // Attach click listeners to patient items
    chatPatientsList.querySelectorAll('.patient-conv-item').forEach(item => {
      item.addEventListener('click', () => {
        const pId = parseInt(item.getAttribute('data-id'), 10);
        if (pId && pId !== activePatientId) {
          activePatientId = pId;
          
          // Update URL without reload
          const newUrl = new URL(window.location.href);
          newUrl.searchParams.set('patient_id', activePatientId);
          window.history.pushState({}, '', newUrl);

          // Update active class
          chatPatientsList.querySelectorAll('.patient-conv-item').forEach(el => el.classList.remove('active'));
          item.classList.add('active');

          loadChatThread(activePatientId);
        }
      });
    });
  }

  // Filter patients by search input
  if (patientFilterInput) {
    patientFilterInput.addEventListener('input', () => {
      const q = patientFilterInput.value.toLowerCase().trim();
      if (!q) {
        renderPatientsList(allPatients);
        return;
      }
      const filtered = allPatients.filter(p => 
        (p.full_name && p.full_name.toLowerCase().includes(q)) ||
        (p.patient_id && p.patient_id.toLowerCase().includes(q)) ||
        (p.package_name && p.package_name.toLowerCase().includes(q))
      );
      renderPatientsList(filtered);
    });
  }

  // =========================================================================
  // 2. Fetch Chat Thread for Active Patient & Update Read Receipts
  // =========================================================================
  async function loadChatThread(patientId, isManualRefresh = false) {
    if (!patientId) return;

    if (isManualRefresh && btnManualRefresh) {
      btnManualRefresh.classList.add('refreshing');
    }

    try {
      const qParams = new URLSearchParams();
      qParams.append('patient_id', patientId);
      if (currentDoctorId > 0) qParams.append('doctor_id', currentDoctorId);
      if (currentDoctorName) qParams.append('doctor_name', currentDoctorName);

      const res = await fetch(`../api/chat.php?${qParams.toString()}`);
      const data = await res.json();

      if (data.doctor) {
        if (!currentDoctorId && data.doctor.id) currentDoctorId = parseInt(data.doctor.id, 10);
        const dName = document.getElementById('doctorNameHeader');
        if (dName && data.doctor.full_name) dName.textContent = data.doctor.full_name;
        const dRole = document.getElementById('doctorRoleHeader');
        if (dRole && data.doctor.expertise) dRole.textContent = data.doctor.expertise;
      }

      if (data.table_missing) {
        return;
      }

      if (data.success) {
        const patient = data.patient;
        currentMessages = data.messages || [];

        // Update Header
        if (activePatientName) activePatientName.textContent = patient.full_name || 'Patient';
        if (activePatientIdEl) activePatientIdEl.textContent = `ID: ${patient.patient_id || 'PT' + patientId}`;
        if (activePatientPackage) activePatientPackage.textContent = `Package: ${patient.package_name || 'General'}`;
        if (activePatientAvatar) activePatientAvatar.textContent = patient.initials || 'PT';
        if (activePatientStatus) {
          activePatientStatus.textContent = patient.status || 'Ongoing';
          activePatientStatus.style.background = (patient.status && patient.status.toLowerCase() === 'completed') ? '#E3F2FD' : '#E8F5E9';
          activePatientStatus.style.color = (patient.status && patient.status.toLowerCase() === 'completed') ? '#1976D2' : '#2E7D32';
        }

        renderMessagesStream(currentMessages);

        // Update refresh timestamp
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        if (refreshTimestampText) {
          refreshTimestampText.textContent = `Last refreshed at ${timeStr}`;
        }

        if (isManualRefresh) {
          showToast('Messages & read receipts updated.');
        }
      } else {
        chatMessagesContainer.innerHTML = `
          <div class="chat-empty-state">
            <p style="color: #D32F2F;">${escapeHtml(data.message || 'Failed to load messages.')}</p>
          </div>
        `;
      }
    } catch (err) {
      console.error('Error loading chat thread:', err);
    } finally {
      if (btnManualRefresh) {
        setTimeout(() => {
          btnManualRefresh.classList.remove('refreshing');
        }, 400);
      }
    }
  }

  // =========================================================================
  // 3. Render Messages in Chat Stream with Read Receipts
  // =========================================================================
  function renderMessagesStream(messages) {
    if (!chatMessagesContainer) return;

    if (!messages || messages.length === 0) {
      chatMessagesContainer.innerHTML = `
        <div class="chat-empty-state">
          <div class="chat-empty-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
          </div>
          <h3 style="font-size: 1.05rem; color: #25231F; margin: 0 0 4px;">No messages in this chat yet</h3>
          <p style="font-size: 0.85rem; color: #5C564D; margin: 0;">Send the first consultation message or note to the patient below.</p>
        </div>
      `;
      return;
    }

    let html = '';
    let lastDate = '';

    messages.forEach(msg => {
      // Date divider
      if (msg.date !== lastDate) {
        html += `
          <div class="chat-date-divider">
            <span class="chat-date-pill">${escapeHtml(msg.date)}</span>
          </div>
        `;
        lastDate = msg.date;
      }

      const isDoctor = (msg.sender_type === 'doctor');
      const rowClass = isDoctor ? 'doctor-msg' : 'patient-msg';

      // Read Receipt indicator for Doctor's sent messages
      let readReceiptHtml = '';
      if (isDoctor) {
        if (msg.is_seen === 1) {
          const seenTime = msg.seen_at_display ? `at ${msg.seen_at_display}` : '';
          readReceiptHtml = `
            <span class="read-receipt-badge seen" title="Seen by patient ${seenTime}">
              <!-- Double Checkmark SVG -->
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
                <polyline points="15 6 9 12"></polyline>
              </svg>
              <span>Seen ${seenTime}</span>
            </span>
          `;
        } else {
          readReceiptHtml = `
            <span class="read-receipt-badge unseen" title="Sent. Patient has not refreshed yet.">
              <!-- Single Checkmark SVG -->
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              <span>Unseen</span>
            </span>
          `;
        }
      }

      html += `
        <div class="chat-message-row ${rowClass}" id="msg-${msg.id}">
          <div class="chat-bubble">
            ${escapeHtml(msg.message).replace(/\n/g, '<br>')}
          </div>
          <div class="chat-msg-meta">
            <span class="chat-msg-time">${escapeHtml(msg.time)}</span>
            ${readReceiptHtml}
          </div>
        </div>
      `;
    });

    chatMessagesContainer.innerHTML = html;
    scrollToBottom();
  }

  function scrollToBottom() {
    if (chatMessagesContainer) {
      chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;
    }
  }

  // =========================================================================
  // 4. Send New Message (Doctor -> Patient)
  // =========================================================================
  async function sendMessage() {
    if (isSending || !chatTextInput) return;
    const text = chatTextInput.value.trim();
    if (!text) return;

    isSending = true;
    if (btnSendMessage) btnSendMessage.disabled = true;

    try {
      const payload = {
        patient_id: activePatientId,
        message: text
      };
      if (currentDoctorId > 0) {
        payload.doctor_id = currentDoctorId;
      }
      if (currentDoctorName) {
        payload.doctor_name = currentDoctorName;
      }

      const response = await fetch('../api/chat.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const result = await response.json();
      if (result.success && result.message_item) {
        // Clear input
        chatTextInput.value = '';
        chatTextInput.style.height = 'auto';

        // Append to current messages
        currentMessages.push(result.message_item);
        renderMessagesStream(currentMessages);

        showToast('Message sent. Marked as Unseen until patient refreshes.');
      } else {
        showToast(result.message || 'Failed to send message.', 'error');
      }
    } catch (err) {
      console.error('Error sending message:', err);
      showToast('Error connecting to chat server.', 'error');
    } finally {
      isSending = false;
      if (btnSendMessage) btnSendMessage.disabled = false;
      chatTextInput.focus();
    }
  }

  // Handle Form Submit
  if (chatInputForm) {
    chatInputForm.addEventListener('submit', (e) => {
      e.preventDefault();
      sendMessage();
    });
  }

  // Handle Enter key (Shift+Enter for new line)
  if (chatTextInput) {
    chatTextInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    // Auto-grow textarea
    chatTextInput.addEventListener('input', () => {
      chatTextInput.style.height = 'auto';
      chatTextInput.style.height = Math.min(chatTextInput.scrollHeight, 120) + 'px';
    });
  }

  // =========================================================================
  // 5. Manual Refresh Button Listener
  // =========================================================================
  if (btnManualRefresh) {
    btnManualRefresh.addEventListener('click', () => {
      loadChatThread(activePatientId, true);
    });
  }

  // Initialize
  loadPatientsDirectory();
});
