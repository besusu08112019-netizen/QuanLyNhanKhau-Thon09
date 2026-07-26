(function () {
  'use strict';

  const maxMessages = 20;
  const memory = {};
  let pendingAsk = false;
  let lastAskedText = '';

  function storageKey() {
    if (typeof window.tenantStorageKey === 'function') return window.tenantStorageKey('ai_conversation');
    return 'tenant_ai_conversation';
  }

  function loadHistory() {
    try {
      const data = JSON.parse(localStorage.getItem(storageKey()) || '[]');
      return Array.isArray(data) ? data.slice(-maxMessages) : [];
    } catch (error) {
      return [];
    }
  }

  function saveHistory(history) {
    try {
      localStorage.setItem(storageKey(), JSON.stringify(history.slice(-maxMessages)));
    } catch (error) {
      // Local storage can be unavailable in private browsing; conversation still works in memory for this page.
    }
  }

  function addMessage(role, content, meta) {
    const history = loadHistory();
    const message = {
      role,
      content: String(content || '').trim(),
      meta: meta || {},
      at: new Date().toISOString(),
    };
    if (!message.content) return history;
    history.push(message);
    saveHistory(history);
    render(history);
    return history;
  }

  function missingFields(result) {
    if (!result || result.intent === 'unknown') return ['intent'];
    const module = result.entities && result.entities.module ? result.entities.module : memory.lastModule;
    const needsModule = ['navigation.open_module', 'search.query', 'report.view', 'data.create_draft'].includes(result.intent);
    return needsModule && !module ? ['module'] : [];
  }

  function promptFor(result, missing) {
    if (missing.includes('intent')) return 'Toi chua hieu yeu cau. Ban muon mo module, tim kiem hay xem bao cao?';
    if (missing.includes('module')) {
      if (result.intent === 'navigation.open_module') return 'Ban muon mo module nao?';
      if (result.intent === 'search.query') return 'Ban muon tim trong ho dan hay nhan khau?';
      if (result.intent === 'report.view') return 'Ban muon xem bao cao ve ho dan hay nhan khau?';
      if (result.intent === 'data.create_draft') return 'Ban muon them ho dan hay nhan khau?';
    }
    return '';
  }

  function render(history) {
    const host = document.querySelector('#aiConversationLog');
    if (!host) return;
    host.innerHTML = '';
    history.slice(-6).forEach(message => {
      const item = document.createElement('div');
      item.className = 'ai-conversation-item ai-conversation-' + message.role;
      const text = document.createElement('div');
      text.className = 'ai-conversation-text';
      text.textContent = message.content;
      item.appendChild(text);
      appendSourceLine(item, message);
      appendResultPreview(item, message);
      host.appendChild(item);
    });
  }

  function appendSourceLine(item, message) {
    if (!message || message.role !== 'assistant' || !message.meta || !message.meta.payload) return;
    const source = sourceText(message.meta.payload);
    if (!source) return;
    const line = document.createElement('small');
    line.className = 'ai-source-line';
    line.textContent = source;
    item.appendChild(line);
  }

  function appendResultPreview(item, message) {
    if (!message || message.role !== 'assistant' || !message.meta || !message.meta.payload) return;
    const payload = message.meta.payload;
    const resultData = payload.result && payload.result.data ? payload.result.data : {};
    const rows = previewRows(resultData);
    if (!rows.length) return;
    const preview = document.createElement('div');
    preview.className = 'ai-result-preview';
    rows.slice(0, 6).forEach(row => {
      const line = document.createElement('div');
      line.className = 'ai-result-row';
      const label = document.createElement('span');
      label.textContent = row[0];
      const value = document.createElement('strong');
      value.textContent = row[1];
      line.appendChild(label);
      line.appendChild(value);
      preview.appendChild(line);
    });
    item.appendChild(preview);
  }

  function previewRows(data) {
    const source = data && data.data ? data.data : data;
    if (!source) return [];
    if (source.metrics && typeof source.metrics === 'object') {
      const metricRows = objectRows(source.metrics).map(row => ['metric ' + row[0], row[1]]);
      const itemRows = Array.isArray(source.items) ? source.items.slice(0, 3).map((row, index) => ['#' + (index + 1), compactObject(row)]) : [];
      return metricRows.concat(itemRows);
    }
    if (Array.isArray(source.items)) return source.items.slice(0, 4).map((row, index) => ['#' + (index + 1), compactObject(row)]);
    if (Array.isArray(source)) return source.slice(0, 4).map((row, index) => ['#' + (index + 1), compactObject(row)]);
    if (source.item && typeof source.item === 'object') return objectRows(source.item);
    if (typeof source === 'object') return objectRows(source);
    return [];
  }

  function objectRows(row) {
    return Object.keys(row || {}).filter(key => row[key] !== null && row[key] !== '').slice(0, 6).map(key => [labelFor(key), String(row[key])]);
  }

  function compactObject(row) {
    if (!row || typeof row !== 'object') return String(row || '');
    return ['household_code', 'full_name', 'identity_number', 'head_citizen_name', 'address'].map(key => row[key]).filter(Boolean).slice(0, 3).join(' - ') || JSON.stringify(row).slice(0, 90);
  }

  function labelFor(key) {
    return String(key || '').replace(/_/g, ' ');
  }

  function sourceText(payload) {
    if (!payload || payload.status === 'needs_clarification') return '';
    const plan = payload.plan || {};
    if (!plan.tool) return '';
    const input = plan.input || {};
    const action = input.action || 'ask';
    const reason = plan.reason ? ' - ' + String(plan.reason).replace(/_/g, ' ') : '';
    return 'Nguon: ' + plan.tool + '.' + action + reason;
  }

  function answerText(payload) {
    if (!payload) return 'Khong co du lieu phu hop.';
    if (payload.status === 'needs_clarification') return payload.prompt || 'Can bo sung thong tin.';
    if (payload.status === 'failed') return 'Khong truy van duoc du lieu.';
    const result = payload.result || {};
    const data = result.data || {};
    const toolData = data.data || data.item || data.items || data;
    const plan = payload.plan || {};
    if (plan.tool === 'insight' && toolData.answer) return String(toolData.answer);
    if (plan.tool === 'household' && data.item) return 'Da tim thay ho ' + (data.item.household_code || '') + '.';
    if (plan.tool === 'resident' && data.item) return 'Da tim thay nhan khau ' + (data.item.full_name || data.item.identity_number || '') + '.';
    if (plan.tool === 'statistics' && plan.input && plan.input.action === 'counts') {
      return 'Tong so: ' + (toolData.total_households || 0) + ' ho, ' + (toolData.total_citizens || 0) + ' nhan khau.';
    }
    if (plan.tool === 'statistics' && plan.input && plan.input.action === 'health_insurance') {
      return 'BHYT: ' + (toolData.insured || 0) + '/' + (toolData.total || 0) + ' nhan khau, ty le ' + (toolData.coverage_percent || 0) + '%.';
    }
    if (Array.isArray(data.items)) return 'Tim thay ' + data.items.length + ' ket qua.';
    return 'Da tra cuu xong du lieu.';
  }

  async function askBackend(text, options) {
    text = String(text || '').trim();
    if (!text || pendingAsk) return;
    if (options && options.skipDuplicate && text === lastAskedText) return;
    pendingAsk = true;
    setAskBusy(true);
    lastAskedText = text;
    if (!options || !options.userAlreadyAdded) addMessage('user', text, { source: options && options.source || 'manual' });
    setStatus('Đang xử lý...');
    try {
      const payload = await postJson('/api/ai/ask', { question: text });
      const answer = answerText(payload);
      setStatus('Đang trả lời...');
      addMessage('assistant', answer, { status: payload.status, plan: payload.plan || null, payload });
      setStatus(payload.status === 'needs_clarification' ? 'Cần bổ sung thông tin.' : 'Sẵn sàng.');
      document.dispatchEvent(new CustomEvent('tenant:ai-answer', { detail: payload }));
    } catch (error) {
      addMessage('assistant', error.message || 'Khong truy van duoc du lieu.', { error: true });
      setStatus('Không truy vấn được dữ liệu.');
    } finally {
      pendingAsk = false;
      setAskBusy(false);
    }
  }

  function setAskBusy(active) {
    const ask = document.querySelector('#aiAskBtn');
    if (!ask) return;
    ask.disabled = Boolean(active);
    ask.setAttribute('aria-busy', active ? 'true' : 'false');
    ask.innerHTML = active ? '<i class="fa-solid fa-spinner fa-spin"></i>' : '<i class="fa-solid fa-paper-plane"></i>';
  }

  async function postJson(path, body) {
    if (typeof window.api === 'function') return window.api(path, { method: 'POST', body });
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
    if (window.App && window.App.token) headers.Authorization = 'Bearer ' + window.App.token;
    if (window.App && window.App.csrfToken) headers['X-CSRF-Token'] = window.App.csrfToken;
    const response = await fetch(path, { method: 'POST', headers, body: JSON.stringify(body || {}), cache: 'no-store' });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload || payload.ok === false) throw new Error(payload && payload.error && payload.error.message || 'Khong truy van duoc du lieu.');
    return payload.data || payload;
  }

  function setStatus(text) {
    const status = document.querySelector('#aiSpeechStatus');
    if (status) status.textContent = text;
  }

  function handleIntent(result) {
    if (!result) return;
    const text = result.normalizedText || '';
    if (text) addMessage('user', text, { intent: result.intent });
    if (result.entities && result.entities.module) memory.lastModule = result.entities.module;
    const missing = missingFields(result);
    if (missing.length) {
      const prompt = promptFor(result, missing);
      addMessage('assistant', prompt, { missing, intent: result.intent });
      document.dispatchEvent(new CustomEvent('tenant:ai-conversation-clarification', {
        detail: { prompt, missing, intent: result.intent },
      }));
    } else if (text) {
      askBackend(text, { source: 'speech', skipDuplicate: true, userAlreadyAdded: true });
    }
  }

  function bind() {
    render(loadHistory());
    document.addEventListener('tenant:ai-intent-recognized', event => handleIntent(event.detail));
    const clear = document.querySelector('#aiConversationClearBtn');
    const ask = document.querySelector('#aiAskBtn');
    const text = document.querySelector('#aiSpeechText');
    if (ask && text && ask.dataset.boundAiAsk !== '1') {
      ask.dataset.boundAiAsk = '1';
      ask.addEventListener('click', function () { askBackend(text.value, { source: 'manual' }); });
      text.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
          event.preventDefault();
          askBackend(text.value, { source: 'keyboard' });
        }
      });
    }
    if (clear) {
      clear.addEventListener('click', function () {
        saveHistory([]);
        render([]);
        setStatus('Đã xóa lịch sử hội thoại.');
        document.dispatchEvent(new CustomEvent('tenant:ai-conversation-cleared'));
      });
    }
  }

  window.TenantAiConversation = Object.freeze({
    addMessage,
    ask: askBackend,
    history: loadHistory,
    clear: function () {
      saveHistory([]);
      render([]);
    },
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
