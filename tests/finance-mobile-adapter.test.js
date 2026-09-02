const assert = require('assert');
const fs = require('fs');

const finance = fs.readFileSync('assets/js/finance.js', 'utf8');
const mobile = fs.readFileSync('assets/js/mobile-component-library.js', 'utf8');

assert(finance.includes('TenantAppMobileFilterAdapters'), 'finance must register a mobile filter adapter');
assert(finance.includes('registry.finance = adapter'), 'finance adapter must be available by module key');
assert(finance.includes('registry.financeScreen = adapter'), 'finance adapter must be available by screen id');
assert(finance.includes("const FILTER_KEYS = ['search','transaction_type','fund_id','category_id','status','date_from','date_to','pageSize']"), 'finance adapter must expose the canonical Finance filter fields only');
assert(finance.includes('function applyMobileFilters'), 'finance adapter must apply mobile filters through module state');
assert(finance.includes('return load();'), 'finance adapter must reload through the canonical Finance loader');
assert(finance.includes('function ensureFinanceReady'), 'finance adapter readiness must wait for canonical catalogs');
assert(finance.includes('await catalogs();'), 'finance adapter must use canonical Finance catalogs');
assert(finance.includes('function categoriesFor(type)'), 'finance must retain canonical transaction_type/category dependency');
assert(finance.includes('options: optionList(categoriesFor(state.transaction_type))'), 'mobile category options must come from categoriesFor(transaction_type)');
assert(finance.includes('function normalizeFilterDependencies'), 'finance must normalize dependent category filters');
assert(finance.includes("if (!allowed.includes(String(state.category_id))) state.category_id = '';"), 'incompatible category_id must be cleared');
assert(finance.includes('state.page = 1;'), 'filter changes must reset pagination to page 1');
assert(finance.includes('setPage(page) { state.page = Number(page || 1); return load(); }'), 'mobile pagination must delegate to canonical loader');
assert(finance.includes('queryString() { return params().toString(); }'), 'adapter queryString must use canonical params()');
assert(finance.includes("page: 1, pageSize: 20, search: '', transaction_type: '', fund_id: '', category_id: '', status: '', date_from: '', date_to: '', sort: 'transaction_date', direction: 'DESC'"), 'reset must preserve canonical Finance reset semantics');
assert(mobile.includes('function mobileFilterAdapter'), 'shared mobile shell must resolve module adapters');
assert(mobile.includes('var activeClientState = adapterBacked ? {}'), 'adapter-backed modules must not run duplicate client-side business filtering');
assert(mobile.includes('adapterGoToPage(adapter'), 'adapter-backed pagination must delegate to module adapter');

console.log('finance mobile adapter checks passed');
