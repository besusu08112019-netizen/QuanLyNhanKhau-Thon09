(function () {
  'use strict';

  const maxMessages = 20;
  const memory = {};

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
      item.textContent = message.content;
      host.appendChild(item);
    });
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
    }
  }

  function bind() {
    render(loadHistory());
    document.addEventListener('tenant:ai-intent-recognized', event => handleIntent(event.detail));
    const clear = document.querySelector('#aiConversationClearBtn');
    if (clear) {
      clear.addEventListener('click', function () {
        saveHistory([]);
        render([]);
        document.dispatchEvent(new CustomEvent('tenant:ai-conversation-cleared'));
      });
    }
  }

  window.TenantAiConversation = Object.freeze({
    addMessage,
    history: loadHistory,
    clear: function () {
      saveHistory([]);
      render([]);
    },
  });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();
