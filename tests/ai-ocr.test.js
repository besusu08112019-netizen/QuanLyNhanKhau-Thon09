const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'assets/js/ai-ocr.js'), 'utf8');
const view = fs.readFileSync(path.join(root, 'views/app.php'), 'utf8');
const buildAssets = fs.readFileSync(path.join(root, 'tools/build-assets.js'), 'utf8');

assert.match(source, /TenantAiOcr/);
assert.match(source, /parseCccdText/);
assert.match(source, /applyCccdToPersonForm/);
assert.match(source, /capture="environment"/);
assert.match(source, /TextDetector/);
assert.match(source, /data-ai-ocr-text/);
assert.match(source, /tenant:ai-ocr-filled/);
assert.match(source, /function esc/);
assert.doesNotMatch(source, /localStorage/);
assert.doesNotMatch(source, /\bfetch\s*\(/);
assert.doesNotMatch(source, /apiPost|window\.api/);
assert.match(view, /src="\/assets\/js\/ai-ocr\.min\.js"/);
assert.match(buildAssets, /assets\/js\/ai-ocr\.js/);

const context = {
  window: {},
  document: {
    readyState: 'loading',
    addEventListener() {},
    querySelector() { return null; },
    getElementById() { return null; },
    createElement() {
      return {
        style: {},
        dataset: {},
        classList: { add() {}, remove() {} },
        set textContent(value) { this._textContent = value; },
        get textContent() { return this._textContent || ''; },
      };
    },
  },
  console,
  Event: function Event(type, options) { return { type, bubbles: Boolean(options && options.bubbles) }; },
};
vm.createContext(context);
vm.runInContext(source, context);

const text = [
  'CONG HOA XA HOI CHU NGHIA VIET NAM',
  'Can cuoc cong dan',
  'So / No: 001185000001',
  'Ho va ten: TRAN THI BINH',
  'Ngay sinh: 01/02/1985',
  'Gioi tinh: Nu',
  'Noi thuong tru: Thon 09, Xa Minh Chau',
].join('\n');

const parsed = context.window.TenantAiOcr.parseCccdText(text);
assert.strictEqual(parsed.identityNumber, '001185000001');
assert.strictEqual(parsed.fullName, 'TRAN THI BINH');
assert.strictEqual(parsed.dateOfBirth, '1985-02-01');
assert.strictEqual(parsed.gender, 'Nữ');
assert.strictEqual(parsed.currentAddress, 'Thon 09, Xa Minh Chau');

function input() {
  return {
    value: '',
    events: [],
    dispatchEvent(event) { this.events.push(event.type); },
  };
}

const form = {
  elements: {
    identityNumber: input(),
    fullName: input(),
    dateOfBirth: input(),
    gender: input(),
    currentAddress: input(),
  },
};
const filled = context.window.TenantAiOcr.applyCccdToPersonForm(form, parsed);
assert.deepStrictEqual(Array.from(filled), ['identityNumber', 'fullName', 'dateOfBirth', 'gender', 'currentAddress']);
assert.strictEqual(form.elements.identityNumber.value, '001185000001');
assert.strictEqual(form.elements.fullName.value, 'TRAN THI BINH');
assert.deepStrictEqual(form.elements.fullName.events, ['input', 'change']);

console.log('AI OCR checks passed');
