(() => {
  'use strict';

  const DEFAULT_UNIT = 'Thôn 09 - Xã Hồng Phong';
  const TYPE_ORIENTATION = Object.freeze({
    household: 'portrait',
    population: 'landscape',
    citizen: 'landscape',
    summary: 'portrait',
    gis: 'landscape',
    'gis-located': 'landscape',
    'gis-unlocated': 'landscape',
    contributions: 'landscape',
    'contributions-list': 'landscape',
    'contributions-collection': 'landscape',
    'contributions-unpaid-list': 'landscape',
    'contributions-partial': 'landscape',
    'contributions-exempt': 'landscape',
    'contributions-by-contribution': 'landscape',
    'contributions-summary': 'portrait',
    'contributions-year-summary': 'portrait'
  });

  const FILTER_LABELS = Object.freeze({
    type: 'Loại báo cáo',
    report_type: 'Loại báo cáo',
    dateFrom: 'Từ ngày',
    dateTo: 'Đến ngày',
    householdStatus: 'Trạng thái hộ',
    householdType: 'Diện hộ',
    gender: 'Giới tính',
    residencyStatus: 'Cư trú',
    presenceStatus: 'Hiện tại',
    lifeStatus: 'Trạng thái',
    ageFrom: 'Tuổi từ',
    ageTo: 'Tuổi đến',
    ethnicity: 'Dân tộc',
    religion: 'Tôn giáo',
    occupation: 'Nghề nghiệp',
    search: 'Từ khóa',
    year: 'Năm',
    campaign_id: 'Đợt thu',
    campaignId: 'Đợt thu',
    payment_status: 'Trạng thái thanh toán',
    paymentStatus: 'Trạng thái thanh toán',
    contribution_name: 'Khoản thu',
    contributionName: 'Khoản thu',
    area_code: 'Khu vực',
    areaCode: 'Khu vực',
    party_member: 'Đảng viên',
    has_health_insurance: 'BHYT'
  });

  function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
  }

  function nowText() {
    return new Date().toLocaleString('vi-VN');
  }

  function unitName(config = {}) {
    const settings = window.App?.settings || window.AppSettings || {};
    return config.unitName || config.meta?.unit_name || settings.unitName || [settings.hamletName, settings.communeName].filter(Boolean).join(' - ') || DEFAULT_UNIT;
  }

  function normalizeRows(rows) {
    return Array.isArray(rows) ? rows : [];
  }

  function columnCount(config) {
    const headers = Array.isArray(config.headers) ? config.headers : [];
    const rows = normalizeRows(config.rows);
    return Math.max(headers.length, ...rows.slice(0, 10).map(row => Array.isArray(row) ? row.length : 0), 1);
  }

  function orientation(config) {
    const forced = String(config.orientation || '').toLowerCase();
    if (forced === 'portrait' || forced === 'landscape') return forced;
    const type = String(config.type || config.reportType || config.filters?.type || config.filters?.report_type || '').toLowerCase();
    if (TYPE_ORIENTATION[type]) return TYPE_ORIENTATION[type];
    return columnCount(config) >= 9 ? 'landscape' : 'portrait';
  }

  function densityClass(config) {
    const cols = columnCount(config);
    if (config.fontSize) return '';
    if (cols >= 12) return ' print-dense';
    if (cols >= 9) return ' print-compact';
    return '';
  }

  function objectLines(data, labels = FILTER_LABELS) {
    if (!data || typeof data !== 'object') return [];
    return Object.entries(data)
      .filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '')
      .map(([key, value]) => [labels[key] || key, value]);
  }

  function listHtml(className, rows) {
    if (!rows.length) return '';
    return '<div class="' + className + '">' + rows.map(([label, value]) => '<div><strong>' + esc(label) + ':</strong> ' + esc(value) + '</div>').join('') + '</div>';
  }

  function reportHeader(config, printedAt) {
    const metaRows = [
      ['Thời gian in', printedAt],
      ['Người lập', config.preparedBy || config.meta?.prepared_by || window.App?.user?.displayName || window.App?.user?.email || 'Người lập']
    ];
    if (config.meta?.period_label) metaRows.unshift(['Kỳ báo cáo', config.meta.period_label]);
    return '<header class="print-header print-avoid-break">'
      + (config.showNationalHeader === false ? '' : '<p class="print-national-title">Cộng hòa xã hội chủ nghĩa Việt Nam</p><p class="print-national-subtitle">Độc lập - Tự do - Hạnh phúc</p>')
      + '<p class="print-unit">' + esc(unitName(config)) + '</p>'
      + '<h1 class="print-title">' + esc(config.title || 'Báo cáo') + '</h1>'
      + listHtml('print-meta', metaRows)
      + (config.showFilters === false ? '' : '<div class="print-section-title">Điều kiện lọc</div>' + (listHtml('print-filter-list', objectLines(config.filters)) || '<div class="print-filter-list"><div>Không áp dụng bộ lọc</div></div>'))
      + '</header>';
  }

  function reportTable(config) {
    const headers = Array.isArray(config.headers) && config.headers.length ? config.headers : ['Nội dung'];
    const rows = normalizeRows(config.rows);
    const repeat = config.repeatHeader === false ? '' : '<tr class="print-repeat-title"><th colspan="' + headers.length + '">' + esc(config.title || 'Báo cáo') + '</th></tr>';
    const head = headers.map(header => '<th>' + esc(header) + '</th>').join('');
    const body = rows.length
      ? rows.map(row => '<tr>' + headers.map((_, index) => '<td>' + esc(Array.isArray(row) ? row[index] : '') + '</td>').join('') + '</tr>').join('')
      : '<tr><td class="print-empty" colspan="' + headers.length + '">Không có dữ liệu</td></tr>';
    return '<table class="print-table"><thead>' + repeat + '<tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table>';
  }

  function summaryHtml(config) {
    if (config.showSummary === false) return '';
    const rows = objectLines(config.summary || defaultSummary(config));
    return rows.length ? '<section class="print-summary print-avoid-break"><div class="print-section-title">Tổng hợp cuối báo cáo</div>' + listHtml('print-summary-list', rows) + '</section>' : '';
  }

  function defaultSummary(config) {
    const headers = (config.headers || []).map(item => String(item).toLowerCase());
    const rows = normalizeRows(config.rows);
    const summary = { 'Tổng số dòng': Number(config.totalRows ?? rows.length).toLocaleString('vi-VN') };
    const genderIndex = headers.findIndex(header => header.includes('giới tính'));
    if (genderIndex >= 0) {
      summary.Nam = rows.filter(row => String(row[genderIndex] || '').toLowerCase() === 'nam').length;
      summary.Nữ = rows.filter(row => String(row[genderIndex] || '').toLowerCase() === 'nữ').length;
    }
    return summary;
  }

  function signatures(config) {
    if (config.showSignature === false) return '';
    const left = config.signatureLeft || config.meta?.prepared_by || 'Người lập';
    const right = config.signatureRight || config.meta?.approved_by || 'Trưởng thôn';
    return '<section class="print-signatures"><div>' + esc(left) + '<div class="print-sign-space"></div><div>........................</div></div><div>' + esc(right) + '<div class="print-sign-space"></div><div>........................</div></div></section>';
  }

  function footer(printedAt, config) {
    if (config.showFooter === false) return '';
    return '<footer class="print-footer"><span>Ngày in: ' + esc(printedAt) + '</span><span class="print-footer-page"></span></footer>';
  }

  function inlineStyle(config, orient) {
    const paper = config.paperSize || 'A4';
    const page = '@page{size:' + paper + ' ' + orient + ';margin:' + (orient === 'landscape' ? '10mm 8mm 16mm' : '14mm 10mm 18mm') + '}';
    const vars = config.fontSize ? '.print-document{--print-font-size:' + esc(config.fontSize) + '}' : '';
    return '<style>' + page + vars + '</style>';
  }

  function renderHtml(config) {
    const orient = orientation(config);
    const printedAt = nowText();
    return '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>' + esc(config.title || 'Báo cáo') + '</title><link rel="stylesheet" href="/assets/css/print.css">' + inlineStyle(config, orient) + '</head><body class="print-' + orient + '"><main class="print-document' + densityClass(config) + '">' + reportHeader(config, printedAt) + reportTable(config) + summaryHtml(config) + signatures(config) + footer(printedAt, config) + '</main><script>window.onload=function(){setTimeout(function(){window.print();},60);};<\/script></body></html>';
  }

  function print(config = {}) {
    const popup = window.open('', '_blank', 'width=1120,height=780');
    if (!popup) {
      if (typeof window.showToast === 'function') window.showToast('Trình duyệt đang chặn cửa sổ in', 'warning');
      return null;
    }
    popup.document.write(renderHtml(config));
    popup.document.close();
    return popup;
  }

  function fromTable(table, config = {}) {
    const headers = Array.from(table?.querySelectorAll?.('thead th') || []).map(th => th.textContent.trim()).filter(Boolean);
    const rows = Array.from(table?.querySelectorAll?.('tbody tr') || []).map(tr => Array.from(tr.children).map(td => td.textContent.trim()));
    return print(Object.assign({ headers, rows }, config));
  }

  function currentScreen(config = {}) {
    const screen = document.querySelector('.screen.active') || document;
    const table = screen.querySelector('table');
    if (table) return fromTable(table, Object.assign({ title: window.Thon09Platform?.navigation?.current?.()?.label || document.title }, config));
    return print(Object.assign({ title: document.title, headers: ['Nội dung'], rows: [[screen.innerText.trim()]] }, config));
  }

  window.Thon09Print = Object.freeze({
    print,
    render: print,
    fromTable,
    currentScreen,
    orientation,
    renderHtml
  });
})();
