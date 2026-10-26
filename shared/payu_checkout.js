/**
 * Ocayur / Panchved - PayU Hosted Checkout Helper (Doctor Portal)
 * 
 * Initiates payment with PayU by contacting api/payu_initiate.php
 * and auto-submitting the signed POST form to PayU's payment gateway.
 */
(function(window) {
  'use strict';

  function getInitiateEndpoint() {
    const pathname = window.location.pathname || '';
    if (pathname.includes('/courses/')) {
      return '../api/payu_initiate.php';
    }
    return 'api/payu_initiate.php';
  }

  const PayUCheckout = {
    /**
     * Process checkout with PayU
     * @param {Object} payload - { service_type, workshop_id, workshop_title, amount, doctor_id, doctor_name, doctor_email, doctor_phone }
     * @returns {Promise<{ redirected: boolean }>}
     */
    async processPayment(payload) {
      if (!payload || !payload.amount) {
        throw new Error('Payment amount and details are required.');
      }

      // Add client origin for dynamic return URL construction
      const fullPayload = {
        ...payload,
        client_origin: window.location.origin
      };

      const endpoint = getInitiateEndpoint();

      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(fullPayload)
      });

      const data = await res.json().catch(() => null);

      if (!res.ok || !data || !data.success) {
        const errorMsg = data && data.message ? data.message : 'Failed to initiate PayU payment gateway.';
        const err = new Error(errorMsg);
        err.data = data;
        throw err;
      }

      const { action_url, params } = data;
      if (!action_url || !params) {
        throw new Error('Invalid payment parameters received from server.');
      }

      // Create and submit hidden POST form to PayU gateway
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = action_url;
      form.style.display = 'none';

      for (const [key, val] of Object.entries(params)) {
        if (val !== undefined && val !== null) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = key;
          input.value = typeof val === 'object' ? JSON.stringify(val) : String(val);
          form.appendChild(input);
        }
      }

      document.body.appendChild(form);
      form.submit();

      return { redirected: true };
    }
  };

  window.PayUCheckout = PayUCheckout;
})(window);

