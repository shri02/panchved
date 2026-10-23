/**
 * Ocayur / Panchved - PayU Client-Side Payment Gateway Connector (Doctor Portal)
 */
window.PayUCheckout = {
  async processPayment(payload, onFallback = null) {
    try {
      const response = await fetch('../api/payu_initiate.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const data = await response.json().catch(() => null);

      if (!response.ok) {
        const err = new Error(data?.message || 'Payment initiation failed.');
        err.data = data;
        throw err;
      }

      if (data && data.payu_configured && data.action_url && data.params) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = data.action_url;
        form.style.display = 'none';

        for (const [key, value] of Object.entries(data.params)) {
          if (value !== undefined && value !== null) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
          }
        }

        document.body.appendChild(form);
        form.submit();
        return { redirected: true, data };
      }

      if (data && data.payu_configured === false) {
        console.info('PayU Notice: Test credentials pending in App Service variables. Executing fallback flow:', data);
        if (typeof onFallback === 'function') {
          return await onFallback(data);
        }
        throw new Error(data.message || 'PayU credentials not yet configured.');
      }

      return data;
    } catch (err) {
      console.error('PayU Doctor Checkout Error:', err);
      throw err;
    }
  }
};
