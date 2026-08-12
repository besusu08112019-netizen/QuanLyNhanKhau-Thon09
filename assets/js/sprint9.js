(() => {
  document.addEventListener('DOMContentLoaded', bootSprint9);
  document.addEventListener('tenant:auth-state', event => { if (event.detail?.authenticated) bootSprint9(); });

  function bootSprint9() { patchDashboard(); patchImportScreen(); patchRestoreScreen(); }

  function patchDashboard() { return; }

  function renderSprint9Dashboard(data) {
    const m = data.metrics || {};
    const cards = [['Tá»•ng sá»‘ há»™',m.total_households,'fa-house'],['Tá»•ng sá»‘ nhÃ¢n kháº©u',m.total_citizens,'fa-users'],['Nam',m.male_count,'fa-mars'],['Ná»¯',m.female_count,'fa-venus'],['Chá»§ há»™',m.household_head_count,'fa-user-check'],['Táº¡m trÃº',m.temporary_count,'fa-location-dot'],['Táº¡m váº¯ng',m.away_count,'fa-person-walking-arrow-right'],['Tráº» em',m.children_count,'fa-child'],['NgÆ°á»i cao tuá»•i',m.elderly_count,'fa-person-cane'],['Äá»™ tuá»•i lao Ä‘á»™ng',m.working_age_count,'fa-briefcase'],['Há»™ nghÃ¨o',m.poor_households,'fa-hand-holding-heart'],['Há»™ cáº­n nghÃ¨o',m.near_poor_households,'fa-scale-balanced']];
    const host = document.querySelector('#dashboardCards');
    if (host) host.innerHTML = cards.map(([label,value,icon]) => '<div class="col-sm-6 col-xl-3"><div class="metric-card admin-metric"><i class="fa-solid ' + icon + '"></i><div><div class="metric-label">' + escapeHtml(label) + '</div><div class="metric-value">' + number(value) + '</div></div></div></div>').join('');
    renderChartSafe('#genderChart', data.charts?.population || []); renderChartSafe('#ageChart', data.charts?.ages || []); renderChartSafe('#householdChart', data.charts?.households || []); renderChartSafe('#residencyChart', data.charts?.residency || []);
    ensureChart('hamletChart','DÃ¢n sá»‘ theo thÃ´n'); ensureChart('monthlyChart','TÄƒng giáº£m dÃ¢n sá»‘ theo thÃ¡ng'); ensureChart('povertyChart','Biá»ƒu Ä‘á»“ há»™ nghÃ¨o');
    renderChartSafe('#hamletChart', data.charts?.hamlets || []); renderChartSafe('#monthlyChart', data.charts?.monthlyChanges || []); renderChartSafe('#povertyChart', data.charts?.poverty || []);
  }

  function renderChartSafe(selector, items) { if (typeof window.renderChart === 'function') window.renderChart(selector, items); }
  function ensureChart(id, title) { if (document.querySelector('#' + id)) return; const row = document.querySelector('#dashboardScreen .row.g-3.mt-1'); if (row) row.insertAdjacentHTML('beforeend', '<div class="col-lg-4"><div class="content-card"><h3 class="section-title">' + escapeHtml(title) + '</h3><div id="' + id + '" class="chart-list"></div></div></div>'); }

  function patchImportScreen() {
    const screen = document.querySelector('#importScreen');
    if (screen) screen.dataset.sprint9 = '1';
  }

  function patchRestoreScreen() {
    const form = document.querySelector('#restoreForm');
    if (!form || form.dataset.sprint9) return;
    form.dataset.sprint9 = '1';
    if (!form.elements.file) form.insertAdjacentHTML('afterbegin', '<label class="form-label">Chá»n file SQL cáº§n khÃ´i phá»¥c</label><input name="file" type="file" class="form-control mb-3" accept=".sql"><label class="form-label">Hoáº·c dÃ¡n ná»™i dung SQL</label>');
    form.addEventListener('submit', async event => {
      const file = form.elements.file?.files?.[0];
      if (!file) return;
      event.preventDefault(); event.stopImmediatePropagation();
      if (!confirm('KhÃ´i phá»¥c dá»¯ liá»‡u sáº½ thay Ä‘á»•i database. Tiáº¿p tá»¥c?')) return;
      const data = new FormData(form);
      const response = await fetch('/api/backups/restore', { method: 'POST', headers: { Authorization: 'Bearer ' + App.token, 'X-CSRF-Token': App.csrfToken || '' }, body: data });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.ok) throw new Error(payload?.error?.message || 'KhÃ´ng khÃ´i phá»¥c Ä‘Æ°á»£c dá»¯ liá»‡u');
      showToast('ÄÃ£ khÃ´i phá»¥c dá»¯ liá»‡u');
    }, true);
  }
})();
