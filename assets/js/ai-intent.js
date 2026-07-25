(function () {
  'use strict';

  const rules = [
    { intent: 'navigation.open_module', category: 'navigation', keywords: ['mo', 'mở', 'vao', 'vào', 'chuyen', 'chuyển', 'trang', 'module'], modules: ['ho dan', 'hộ dân', 'nhan khau', 'nhân khẩu', 'dashboard', 'gis', 'bao cao', 'báo cáo'], confirmation: false },
    { intent: 'search.query', category: 'search', keywords: ['tim', 'tìm', 'tra', 'kiem', 'kiếm', 'loc', 'lọc'], modules: ['ho dan', 'hộ dân', 'nhan khau', 'nhân khẩu', 'cccd', 'dia chi', 'địa chỉ'], confirmation: false },
    { intent: 'report.view', category: 'report', keywords: ['bao cao', 'báo cáo', 'thong ke', 'thống kê', 'tong hop', 'tổng hợp'], modules: ['ho dan', 'hộ dân', 'nhan khau', 'nhân khẩu', 'ho ngheo', 'hộ nghèo'], confirmation: false },
    { intent: 'data.create_draft', category: 'data_entry', keywords: ['them', 'thêm', 'tao', 'tạo', 'lap', 'lập'], modules: ['ho dan', 'hộ dân', 'nhan khau', 'nhân khẩu'], confirmation: true },
  ];

  const canonicalModules = {
    'ho dan': 'household',
    'hộ dân': 'household',
    'nhan khau': 'citizen',
    'nhân khẩu': 'citizen',
    'bao cao': 'report',
    'báo cáo': 'report',
    'ho ngheo': 'poor_household',
    'hộ nghèo': 'poor_household',
  };

  function normalize(text) {
    const normalizedText = String(text || '').toLowerCase().replace(/[^\p{L}\p{N}\s-]+/gu, ' ').replace(/\s+/g, ' ').trim();
    return {
      rawText: String(text || '').trim(),
      normalizedText,
      tokens: normalizedText ? normalizedText.split(/\s+/) : [],
    };
  }

  function containsPhrase(text, phrase) {
    return (' ' + text + ' ').includes(' ' + phrase.toLowerCase() + ' ');
  }

  function scorePhrases(text, phrases) {
    const matches = phrases.filter(phrase => containsPhrase(text, phrase)).length;
    return matches ? Math.min(0.64, 0.32 + matches * 0.08) : 0;
  }

  function firstMatchingPhrase(text, phrases) {
    return phrases.find(phrase => containsPhrase(text, phrase)) || '';
  }

  function extractEntities(text, module) {
    const entities = {};
    if (module) entities.module = canonicalModules[module] || module;
    const household = text.match(/\b(h\d{2}-\d{4})\b/u);
    if (household) entities.household_code = household[1].toUpperCase();
    const phone = text.match(/\b(?:0|\+84)\d{9,10}\b/u);
    if (phone) entities.phone = phone[0];
    const identity = text.match(/\b\d{9}(?:\d{3})?\b/u);
    if (identity) entities.identity_number = identity[0];
    return entities;
  }

  function recognize(text) {
    const command = normalize(text);
    if (!command.normalizedText) return { intent: 'unknown', category: 'unknown', confidence: 0, normalizedText: '', tokens: [], entities: {}, requiresConfirmation: false };
    let best = { rule: null, score: 0, module: '' };
    rules.forEach(rule => {
      const module = firstMatchingPhrase(command.normalizedText, rule.modules);
      const score = Math.min(0.99, scorePhrases(command.normalizedText, rule.keywords) + (module ? 0.35 : 0));
      if (score > best.score) best = { rule, score, module };
    });
    if (!best.rule || best.score < 0.45) return { intent: 'unknown', category: 'unknown', confidence: Number(best.score.toFixed(2)), normalizedText: command.normalizedText, tokens: command.tokens, entities: {}, requiresConfirmation: false };
    return {
      intent: best.rule.intent,
      category: best.rule.category,
      confidence: Number(best.score.toFixed(2)),
      normalizedText: command.normalizedText,
      tokens: command.tokens,
      entities: extractEntities(command.normalizedText, best.module),
      requiresConfirmation: best.rule.confirmation,
    };
  }

  function render(result) {
    const output = document.querySelector('#aiIntentPreview');
    if (!output) return;
    output.textContent = result.intent === 'unknown'
      ? 'Chua nhan dien duoc y dinh.'
      : result.intent + ' (' + Math.round(result.confidence * 100) + '%)';
  }

  document.addEventListener('tenant:ai-speech-transcript', function (event) {
    if (!event.detail || !event.detail.final) return;
    const result = recognize(event.detail.text);
    render(result);
    document.dispatchEvent(new CustomEvent('tenant:ai-intent-recognized', { detail: result }));
  });

  window.TenantAiIntent = Object.freeze({ normalize, recognize });
})();
