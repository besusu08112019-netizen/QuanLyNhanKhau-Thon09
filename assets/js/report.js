(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#reportForm');
    if (!form) return;
    form.addEventListener('submit', event => { event.preventDefault(); loadReport(); });
    document.querySelector('#reportExcelBtn').addEventListener('click', () => downloadReport('/api/reports/export-excel', 'xls', 'Đã xuất Excel'));
    document.querySelector('#reportPdfBtn').addEventListener('click', () => downloadReport('/api/reports/export-pdf', 'pdf', 'Đã xuất PDF'));
    document.querySelector('#reportPrintBtn').addEventListener('click', printReport);
  });

  window.loadReport = async function loadReport() {
    try {
      const query = reportQuery();
      const report = await api('/api/reports/summary?' + query);
      renderReport(report);
    } catch (error) {
      showToast('Không tải được báo cáo: ' + error.message, 'danger');
    }
  };

  async function downloadReport(endpoint, extension, successMessage) {
    try {
      const response = await fetch(endpoint + '?' + reportQuery(), { headers: { Authorization: `Bearer ${App.token}` } });
      if (!response.ok) {
        const payload = await response.json().catch(() => null);
        throw new Error(payload?.error?.message || 'Không xuất được dữ liệu');
      }
      const blob = await response.blob();
      const fileName = fileNameFromHeader(response.headers.get('Content-Disposition')) || `bao_cao_${timestamp()}.${extension}`;
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = fileName;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
      showToast(successMessage);
    } catch (error) {
      showToast(error.message, 'danger');
    }
  }

  async function printReport() {
    try {
      const report = await api('/api/reports/print?' + reportQuery());
      const html = reportHtml(report);
      const popup = window.open('', '_blank', 'width=1024,height=768');
      if (!popup) {
        showToast('Trình duyệt đang chặn cửa sổ in. Vui lòng cho phép popup.', 'warning');
        return;
      }

      const printHtml = `
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <title>${escapeHtml(report.title || 'Báo cáo')}</title>
  <style>
    @page { size: A4; margin: 14mm; }
    body { font-family: Arial, sans-serif; color: #111; }
    h1 { text-align: center; font-size: 20px; margin: 0 0 12px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { border: 1px solid #555; padding: 6px; vertical-align: top; }
    th { background: #f0f3f7; }
    .meta { text-align: right; font-size: 12px; margin-bottom: 12px; }
  </style>
</head>
<body>
  ${html}
  <script>
    window.onload = function () { window.print(); };
  <\/script>
</body>
</html>`;

      popup.document.write(printHtml);
      popup.document.close();
    } catch (error) {
      showToast(error.message, 'danger');
    }
  }

  function reportQuery() {
    const data = formData(document.querySelector('#reportForm'));
    return new URLSearchParams(data).toString();
  }

  function renderReport(report) {
    document.querySelector('#reportTitle').textContent = report.title || 'Báo cáo';
    document.querySelector('#reportCount').textContent = `${number(report.totalRows || 0)} dòng`;
    document.querySelector('#reportPreview').innerHTML = reportTable(report);
  }

  function reportHtml(report) {
    return `<h1>${escapeHtml(report.title || 'Báo cáo')}</h1><div class="meta">Ngày in: ${new Date().toLocaleString('vi-VN')}</div>${reportTable(report)}`;
  }

  function reportTable(report) {
    const headers = (report.headers || []).map(header => `<th>${escapeHtml(header)}</th>`).join('');
    const rows = (report.rows || []).map(row => `<tr>${row.map(cell => `<td>${escapeHtml(cell ?? '')}</td>`).join('')}</tr>`).join('') || `<tr><td colspan="${Math.max((report.headers || []).length, 1)}" class="text-center text-muted py-3">Không có dữ liệu</td></tr>`;
    return `<table class="table table-bordered table-sm align-middle mb-0"><thead><tr>${headers}</tr></thead><tbody>${rows}</tbody></table>`;
  }

  function fileNameFromHeader(header) {
    const match = /filename="?([^";]+)"?/i.exec(header || '');
    return match ? match[1] : '';
  }

  function timestamp() {
    const now = new Date();
    const pad = value => String(value).padStart(2, '0');
    return `${now.getFullYear()}${pad(now.getMonth() + 1)}${pad(now.getDate())}_${pad(now.getHours())}${pad(now.getMinutes())}${pad(now.getSeconds())}`;
  }
})();
