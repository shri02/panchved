/**
 * Ocayur / Panchved - PayU Client-Side Payment Gateway Connector (Doctor Portal)
 */
(function(window) {
  'use strict';

  function getInitiateEndpoint() {
    const pathname = window.location.pathname || '';
    if (pathname.includes('/dashboard/courses/')) {
      return '../../api/payu_initiate.php';
    }
    if (pathname.includes('/courses/')) {
      return '../api/payu_initiate.php';
    }
    return 'api/payu_initiate.php';
  }

  const PayUCheckout = {
    async processPayment(payload, onFallback = null) {
      try {
        if (!payload || !payload.amount) {
          throw new Error('Payment amount and workshop details are required.');
        }

        payload.origin_url = window.location.origin;
        payload.client_origin = window.location.origin;

        const endpoint = getInitiateEndpoint();

        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify(payload)
        });

        const data = await response.json().catch(() => null);

        if (!response.ok || !data || !data.success) {
          if (data && data.is_configured === false) {
            console.info('PayU Notice: Credentials pending in App Service variables. Executing fallback flow:', data);
            if (typeof onFallback === 'function') {
              return await onFallback(data);
            }
          }
          const err = new Error(data?.message || 'Payment initiation failed.');
          err.data = data;
          throw err;
        }

        const { action_url, params } = data;
        if (!action_url || !params) {
          throw new Error('Invalid payment parameters received from server.');
        }

        // Create and auto-submit hidden POST form to PayU gateway
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action_url;
        form.style.display = 'none';

        for (const [key, value] of Object.entries(params)) {
          if (value !== undefined && value !== null) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = typeof value === 'object' ? JSON.stringify(value) : String(value);
            form.appendChild(input);
          }
        }

        document.body.appendChild(form);
        form.submit();
        return { redirected: true, data };
      } catch (err) {
        console.error('PayU Doctor Checkout Error:', err);
        throw err;
      }
    }
  };

  window.PayUCheckout = PayUCheckout;
})(window);
