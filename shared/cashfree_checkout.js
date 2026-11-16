/**
 * Ocayur / Panchved - Cashfree Web Checkout Helper (Doctor Portal)
 * 
 * Initiates payment with Cashfree by contacting api/cashfree_initiate.php
 * and launching the Cashfree Checkout SDK v3.
 */
(function(window) {
  'use strict';

  function getInitiateEndpoint() {
    const pathname = window.location.pathname || '';
    if (pathname.includes('/courses/')) {
      return '../api/cashfree_initiate.php';
    }
    return 'api/cashfree_initiate.php';
  }

  // Helper to load Cashfree JS SDK v3 dynamically if not already loaded
  function loadCashfreeSDK() {
    return new Promise((resolve, reject) => {
      if (typeof window.Cashfree === 'function') {
        return resolve(window.Cashfree);
      }
      const existingScript = document.querySelector('script[src*="cashfree.com/js/v3/cashfree.js"]');
      if (existingScript) {
        existingScript.addEventListener('load', () => resolve(window.Cashfree));
        existingScript.addEventListener('error', (err) => reject(new Error('Failed to load Cashfree JS SDK')));
        return;
      }
      const script = document.createElement('script');
      script.src = 'https://sdk.cashfree.com/js/v3/cashfree.js';
      script.async = true;
      script.onload = () => {
        if (typeof window.Cashfree === 'function') {
          resolve(window.Cashfree);
        } else {
          reject(new Error('Cashfree SDK loaded but Cashfree function not found.'));
        }
      };
      script.onerror = () => reject(new Error('Failed to load Cashfree JS SDK from CDN.'));
      document.head.appendChild(script);
    });
  }

  const CashfreeCheckout = {
    /**
     * Process checkout with Cashfree Payment Gateway
     * @param {Object} payload - { service_type, workshop_id, workshop_title, amount, doctor_id, doctor_name, doctor_email, doctor_phone }
     * @param {Object} options - { redirectTarget: '_self' | '_modal' }
     * @returns {Promise<{ redirected: boolean, order_id: string }>}
     */
    async processPayment(payload, options = {}) {
      if (!payload || !payload.amount) {
        throw new Error('Payment amount and details are required.');
      }

      // Add client origin for dynamic callback URL construction
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
        const errorMsg = data && data.message ? data.message : 'Failed to initiate Cashfree payment gateway.';
        const err = new Error(errorMsg);
        err.data = data;
        throw err;
      }

      const { payment_session_id, order_id, cf_environment } = data;
      if (!payment_session_id) {
        throw new Error('Invalid payment session received from server.');
      }

      // Load SDK
      const CashfreeInit = await loadCashfreeSDK();

      // Initialize Cashfree instance with appropriate mode (sandbox / production)
      const isProd = (cf_environment === 'production' || cf_environment === 'prod');
      const cashfreeInstance = CashfreeInit({
        mode: isProd ? 'production' : 'sandbox'
      });

      // Checkout options: _self redirects browser to Cashfree payment page and then returns to return_url
      const redirectTarget = options.redirectTarget || '_self';

      cashfreeInstance.checkout({
        paymentSessionId: payment_session_id,
        redirectTarget: redirectTarget
      });

      return { redirected: true, order_id: order_id };
    }
  };

  window.CashfreeCheckout = CashfreeCheckout;
  // Backward compatibility alias for any existing code calling PayUCheckout
  window.PayUCheckout = CashfreeCheckout;
})(window);
