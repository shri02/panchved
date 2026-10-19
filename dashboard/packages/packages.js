/**
 * Ocayur Doctor Portal - Packages Directory Frontend Logic
 * Connects packages.html with api/get_packages.php
 */

document.addEventListener('DOMContentLoaded', () => {
  const packagesGrid = document.getElementById('packagesGrid');
  const packageSearchInput = document.getElementById('packageSearchInput');

  let allPackages = [];

  // =========================================================================
  // 1. Fetch Packages from Backend API
  // =========================================================================
  async function fetchPackages(searchQuery = '') {
    try {
      let url = '../api/get_packages.php';
      if (searchQuery) {
        url += `?search=${encodeURIComponent(searchQuery)}`;
      }

      const response = await fetch(url);
      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const data = await response.json();
      if (data.success && Array.isArray(data.packages)) {
        allPackages = data.packages;
        renderPackages(allPackages);
      }
    } catch (err) {
      console.warn('Packages fetch error (fallback to current DOM):', err);
    } finally {
      window.hidePageLoader?.();
    }
  }

  // =========================================================================
  // 2. Render Package Cards (Image 1)
  // =========================================================================
  function renderPackages(packages) {
    if (!packagesGrid) return;

    if (packages.length === 0) {
      packagesGrid.innerHTML = `
        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #64748b; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0;">
          No packages found matching your criteria.
        </div>
      `;
      return;
    }

    let html = '';
    packages.forEach(pkg => {
      const priceText = pkg.price_display || `Starting at ₹${pkg.price} / month`;
      const durationText = pkg.duration || '3 Months';
      const batchText = pkg.batch_display || `${pkg.enrollments || 18} Patient Batch`;
      const protocolUrl = `protocol-details.html?id=${pkg.id}`;

      html += `
        <article class="package-card" data-id="${pkg.id}">
          <div>
            <div class="package-card-header">
              <h3 class="package-title">${escapeHtml(pkg.package_name)}</h3>
              <p class="package-subtitle">${escapeHtml(pkg.short_description)}</p>
            </div>

            <div class="package-card-divider"></div>

            <div class="package-meta-list">
              <div class="package-meta-item">
                <span class="package-meta-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                    <line x1="2" y1="10" x2="22" y2="10"></line>
                  </svg>
                </span>
                <span>${escapeHtml(priceText)}</span>
              </div>

              <div class="package-meta-item">
                <span class="package-meta-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                  </svg>
                </span>
                <span>${escapeHtml(durationText)}</span>
              </div>

              <div class="package-meta-item">
                <span class="package-meta-icon" aria-hidden="true">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                  </svg>
                </span>
                <span>${escapeHtml(batchText)}</span>
              </div>
            </div>
          </div>

          <a href="${protocolUrl}" class="btn-view-protocol" aria-label="View Protocol for ${escapeHtml(pkg.package_name)}">View Protocol</a>
        </article>
      `;
    });

    packagesGrid.innerHTML = html;
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
  // 3. Live Search & Debounced Backend Search
  // =========================================================================
  let searchTimer = null;
  if (packageSearchInput) {
    packageSearchInput.addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase().trim();
      
      // Client-side quick filter
      const filtered = allPackages.filter(p => 
        (p.package_name || '').toLowerCase().includes(query) ||
        (p.short_description || '').toLowerCase().includes(query) ||
        (p.category || '').toLowerCase().includes(query)
      );
      renderPackages(filtered);

      // Server search debounce
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        fetchPackages(query);
      }, 400);
    });
  }

  // Initial Fetch
  fetchPackages();
});
