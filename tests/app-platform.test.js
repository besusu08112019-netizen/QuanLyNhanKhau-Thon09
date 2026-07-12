const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'assets/js/app-platform.js'), 'utf8');

function createSandbox() {
  const listeners = [];
  const windowListeners = {};
  const historyCalls = [];
  const sandbox = {
    console,
    CustomEvent: function CustomEvent(type, options) {
      this.type = type;
      this.detail = options && options.detail;
    },
    document: {
      createTextNode(text) {
        return { nodeType: 3, textContent: String(text) };
      },
      createElement(tagName) {
        return {
          tagName: String(tagName).toUpperCase(),
          className: '',
          textContent: '',
          innerHTML: '',
          dataset: {},
          attributes: {},
          children: [],
          listeners: {},
          setAttribute(name, value) {
            this.attributes[name] = value;
          },
          removeAttribute(name) {
            delete this.attributes[name];
          },
          addEventListener(name, handler) {
            this.listeners[name] = handler;
          },
          appendChild(child) {
            this.children.push(child);
            return child;
          }
        };
      },
      dispatchEvent(event) {
        listeners.push(event);
      }
    },
    window: {
      fetch: null,
      App: {},
      location: { pathname: '/dashboard', search: '', hash: '' },
      history: {
        calls: historyCalls,
        pushState(state, title, url) {
          historyCalls.push({ method: 'pushState', state, title, url });
        },
        replaceState(state, title, url) {
          historyCalls.push({ method: 'replaceState', state, title, url });
        }
      },
      addEventListener(name, handler) {
        windowListeners[name] = handler;
      },
      removeEventListener(name, handler) {
        if (windowListeners[name] === handler) delete windowListeners[name];
      },
      Thon09NavigationController: {
        calls: [],
        navigate(screen, options) {
          this.calls.push({ screen, options });
        }
      }
    }
  };
  sandbox.window.window = sandbox.window;
  sandbox.window.document = sandbox.document;
  sandbox.window.CustomEvent = sandbox.CustomEvent;
  sandbox.listeners = listeners;
  sandbox.windowListeners = windowListeners;
  sandbox.historyCalls = historyCalls;
  return sandbox;
}

function loadPlatform() {
  const sandbox = createSandbox();
  vm.runInNewContext(source, sandbox, { filename: 'app-platform.js' });
  return sandbox;
}

function navRoot(items, datasetKey) {
  const nodes = items.map((value) => ({
    className: 'nav-link',
    dataset: { [datasetKey]: value },
    attributes: {},
    setAttribute(name, attrValue) {
      this.attributes[name] = attrValue;
    },
    removeAttribute(name) {
      delete this.attributes[name];
    }
  }));
  return {
    nodes,
    querySelectorAll(selector) {
      if (selector === '[data-screen]' || selector === '[data-mobile-screen]') return nodes;
      return [];
    }
  };
}

function screenNode(screenId) {
  return {
    id: screenId + '-screen',
    className: 'screen',
    dataset: { screenId },
    style: { display: 'block', zIndex: '5' },
    attributes: {},
    setAttribute(name, attrValue) {
      this.attributes[name] = attrValue;
    }
  };
}

{
  const sandbox = loadPlatform();
  assert.ok(sandbox.window.Thon09Platform, 'platform is exposed');
  assert.strictEqual(sandbox.listeners[0].type, 'thon09:platform-ready');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const route = platform.routes.match('/households/42/edit');
  assert.strictEqual(route.moduleKey, 'households');
  assert.strictEqual(route.screenId, 'households');
  assert.strictEqual(route.action, 'edit');
  assert.strictEqual(route.params.id, '42');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const module = platform.modules.get('households');
  assert.strictEqual(module.path, '/households');
  assert.strictEqual(module.screenId, 'households');
  assert.strictEqual(module.loaderName, 'loadHouseholds');

  const editRoute = platform.menu.routeForModuleAction('households', 'edit');
  assert.strictEqual(editRoute.path, '/households/:id/edit');

  const resolved = platform.menu.resolveMenuItem('publicAssets');
  assert.strictEqual(resolved.menuKey, 'assets');
  assert.strictEqual(resolved.route, '/public-assets');
  assert.strictEqual(resolved.screenId, 'publicAssets');

  const systemAdmin = platform.menu.resolveMenuItem('systemAdmin');
  assert.strictEqual(systemAdmin.menuKey, 'system');
  assert.strictEqual(systemAdmin.route, '/system/admin');
  assert.strictEqual(systemAdmin.screenId, 'systemAdmin');

  const populationModules = platform.menu.modulesForMenu('population').map((item) => item.moduleKey);
  assert.strictEqual(populationModules.join(','), 'households,persons,temporaryResidence,temporaryAbsence,movements');

  const mobileScreens = platform.menuRenderer.mobileScreens();
  assert.strictEqual(
    mobileScreens.join(','),
    'households,persons,temporaryResidence,temporaryAbsence,movements,publicAssets,businessHouseholds,livestock,houses,vehicles,agriculture,contributions'
  );
  assert.strictEqual(platform.menuRenderer.mobileModules()[0].mobileLabel, 'Ho');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const legacy = platform.normalizeApiResponse({ ok: true, data: { items: [] }, pagination: { page: 1 } });
  assert.strictEqual(legacy.success, true);
  assert.strictEqual(legacy.message, '');
  assert.strictEqual(legacy.data.items.length, 0);
  assert.strictEqual(legacy.meta.page, 1);

  const standard = platform.normalizeApiResponse({ success: false, message: 'Denied', data: null, meta: { code: 403 } });
  assert.strictEqual(standard.success, false);
  assert.strictEqual(standard.message, 'Denied');
  assert.strictEqual(standard.meta.code, 403);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const requests = [];
  function syncThen(value) {
    return {
      then(fn) {
        const result = fn(value);
        return result && typeof result.then === 'function' ? result : syncThen(result);
      }
    };
  }
  const client = platform.createApiClient({
    baseUrl: '/base',
    fetch(url, options) {
      requests.push({ url, options });
      return syncThen({
        ok: true,
        status: 200,
        json() {
          return syncThen({ success: true, message: 'OK', data: { saved: true }, meta: { page: 1 } });
        }
      });
    }
  });

  let apiResponse = null;
  client.put('/households/1', { name: 'A' }).then((response) => {
    apiResponse = response;
  });
  assert.strictEqual(apiResponse.success, true);
  assert.strictEqual(apiResponse.data.saved, true);
  assert.strictEqual(apiResponse.meta.page, 1);
  assert.strictEqual(requests[0].url, '/base/households/1');
  assert.strictEqual(requests[0].options.method, 'PUT');
  assert.strictEqual(requests[0].options.headers['Content-Type'], 'application/json');
  assert.strictEqual(requests[0].options.body, JSON.stringify({ name: 'A' }));

  client.delete('/households/1');
  assert.strictEqual(requests[1].options.method, 'DELETE');
}

{
  const sandbox = loadPlatform();
  const result = sandbox.window.Thon09Platform.navigation.navigate('/persons');
  assert.strictEqual(result.moduleKey, 'persons');
  assert.strictEqual(result.screenId, 'persons');
  assert.strictEqual(result.route, '/persons');
  assert.strictEqual(sandbox.window.Thon09NavigationController.calls[0].screen, 'persons');
  assert.strictEqual(sandbox.window.App.route, '/persons');
  assert.strictEqual(sandbox.window.App.moduleKey, 'persons');
  assert.strictEqual(sandbox.window.App.screen, 'persons');
  assert.strictEqual(sandbox.window.App.action, 'list');
  assert.strictEqual(sandbox.window.Thon09Platform.appState.get().moduleKey, 'persons');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const personEdit = platform.routes.match('/persons/9/edit');
  assert.strictEqual(personEdit.moduleKey, 'persons');
  assert.strictEqual(personEdit.action, 'edit');
  assert.strictEqual(personEdit.params.id, '9');
  const publicAssetCreate = platform.routes.match('/public-assets/create');
  assert.strictEqual(publicAssetCreate.moduleKey, 'publicAssets');
  assert.strictEqual(publicAssetCreate.action, 'create');
  const contributionDetail = platform.routes.match('/contributions/15');
  assert.strictEqual(contributionDetail.action, 'detail');
  assert.strictEqual(platform.menu.routeForModuleAction('vehicles', 'edit').path, '/vehicles/:id/edit');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const crumbs = platform.breadcrumbs.fromRoute('/households/42/edit');
  assert.strictEqual(crumbs.map((crumb) => crumb.label).join('>'), 'Dashboard>Quan ly dan cu>Ho gia dinh>Chinh sua');
  assert.strictEqual(crumbs[2].moduleKey, 'households');
  assert.strictEqual(crumbs[3].params.id, '42');

  const createCrumbs = platform.breadcrumbs.fromModuleAction('persons', 'create');
  assert.strictEqual(createCrumbs[createCrumbs.length - 1].label, 'Them moi');

  const root = {
    textContent: 'old',
    dataset: {},
    children: [],
    appendChild(child) {
      this.children.push(child);
      return child;
    }
  };
  assert.strictEqual(platform.breadcrumbs.render(root, { route: '/vehicles/7' }), true);
  assert.strictEqual(root.textContent, '');
  assert.strictEqual(root.dataset.platformBreadcrumb, 'true');
  assert.strictEqual(root.children.map((child) => child.textContent).join('>'), 'Dashboard>Quan ly phuong tien>Quan ly xe co>Chi tiet');
  assert.strictEqual(root.children[0].tagName, 'A');
  assert.strictEqual(root.children[root.children.length - 1].className, 'breadcrumb-item active');
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const next = platform.appState.set({ route: '/households/42/edit', width: 390 });
  assert.strictEqual(next.route, '/households/42/edit');
  assert.strictEqual(next.moduleKey, 'households');
  assert.strictEqual(next.screenId, 'households');
  assert.strictEqual(next.action, 'edit');
  assert.strictEqual(next.params.id, '42');
  assert.strictEqual(next.layout.mode, 'mobile');
  assert.strictEqual(next.breadcrumbs.map((crumb) => crumb.label).join('>'), 'Dashboard>Quan ly dan cu>Ho gia dinh>Chinh sua');
  assert.ok(sandbox.listeners.some((event) => event.type === 'thon09:app-state-change'));

  const patched = platform.appState.patch({ action: 'detail', params: { id: '99' }, width: 1280 });
  assert.strictEqual(patched.action, 'detail');
  assert.strictEqual(patched.params.id, '99');
  assert.strictEqual(patched.layout.mode, 'desktop');

  const reset = platform.appState.reset();
  assert.strictEqual(reset.moduleKey, 'dashboard');
  assert.strictEqual(platform.appState.get().screenId, 'dashboard');
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  assert.strictEqual(platform.router.pathFor('households', 'edit', { id: 42 }), '/households/42/edit');

  const resolved = platform.router.resolve('/vehicles/7');
  assert.strictEqual(resolved.route, '/vehicles/7');
  assert.strictEqual(resolved.routePattern, '/vehicles/:id');
  assert.strictEqual(resolved.moduleKey, 'vehicles');
  assert.strictEqual(resolved.screenId, 'vehicles');
  assert.strictEqual(resolved.action, 'detail');
  assert.strictEqual(resolved.params.id, '7');

  const byModule = platform.router.resolve({ moduleKey: 'persons', action: 'create' });
  assert.strictEqual(byModule.route, '/persons/create');
  assert.strictEqual(byModule.action, 'create');

  const synced = platform.router.sync({ moduleKey: 'temporaryResidence', action: 'list', width: 420 });
  assert.strictEqual(synced.route, '/temporary-residence');
  assert.strictEqual(synced.moduleKey, 'temporaryResidence');
  assert.strictEqual(synced.layout.mode, 'mobile');
  assert.ok(sandbox.listeners.some((event) => event.type === 'thon09:app-state-change'));
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const pushed = platform.history.push('/households/42/edit', { width: 390 });
  assert.strictEqual(pushed.route, '/households/42/edit');
  assert.strictEqual(pushed.moduleKey, 'households');
  assert.strictEqual(pushed.action, 'edit');
  assert.strictEqual(sandbox.historyCalls[0].method, 'pushState');
  assert.strictEqual(sandbox.historyCalls[0].url, '/households/42/edit');
  assert.strictEqual(sandbox.window.Thon09NavigationController.calls.length, 0);

  const replaced = platform.history.replace({ moduleKey: 'vehicles', action: 'detail', params: { id: 7 } });
  assert.strictEqual(replaced.route, '/vehicles/7');
  assert.strictEqual(sandbox.historyCalls[1].method, 'replaceState');

  assert.strictEqual(platform.history.start((state) => {
    assert.strictEqual(state.moduleKey, 'persons');
  }), true);
  sandbox.window.location.pathname = '/persons';
  sandbox.windowListeners.popstate({ state: { route: '/persons' } });
  assert.strictEqual(platform.history.active(), true);
  assert.strictEqual(platform.history.stop(), true);
  assert.strictEqual(platform.history.active(), false);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const sidebarRoot = navRoot(['households', 'persons', 'vehicles'], 'screen');
  const bottomRoot = navRoot(['households', 'persons', 'vehicles'], 'mobileScreen');
  const breadcrumbRoot = {
    textContent: '',
    dataset: {},
    children: [],
    appendChild(child) {
      this.children.push(child);
      return child;
    }
  };

  const state = platform.appState.set({ route: '/vehicles/7', width: 1280 });
  const result = platform.navigationView.sync({ sidebarRoot, bottomRoot, breadcrumbRoot, state });
  assert.strictEqual(result.screenId, 'vehicles');
  assert.strictEqual(result.sidebar.active, 1);
  assert.strictEqual(result.bottomNavigation.active, 1);
  assert.strictEqual(sidebarRoot.nodes[2].className, 'nav-link active');
  assert.strictEqual(sidebarRoot.nodes[2].attributes['aria-current'], 'page');
  assert.strictEqual(sidebarRoot.nodes[0].className, 'nav-link');
  assert.strictEqual(bottomRoot.nodes[2].className, 'nav-link active');
  assert.strictEqual(breadcrumbRoot.children.map((child) => child.textContent).join('>'), 'Dashboard>Quan ly phuong tien>Quan ly xe co>Chi tiet');

  platform.appState.set({ route: '/persons', width: 390 });
  platform.navigationView.sync({ sidebarRoot, bottomRoot });
  assert.strictEqual(sidebarRoot.nodes[1].className, 'nav-link active');
  assert.strictEqual(sidebarRoot.nodes[2].className, 'nav-link');
  assert.strictEqual(bottomRoot.nodes[1].attributes['aria-current'], 'page');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  const state = platform.appState.set({ route: '/persons', width: 1280 });
  const result = platform.screens.sync({ screens, state });
  assert.strictEqual(result.total, 3);
  assert.strictEqual(result.shown, 'persons');
  assert.strictEqual(result.hidden, 2);
  assert.strictEqual(screens[1].style.display, 'block');
  assert.strictEqual(screens[1].style.zIndex, '1');
  assert.strictEqual(screens[1].className, 'screen active');
  assert.strictEqual(screens[1].attributes['aria-hidden'], 'false');
  assert.strictEqual(screens[0].style.display, 'none');
  assert.strictEqual(screens[0].className, 'screen');
  assert.strictEqual(screens[0].attributes['aria-hidden'], 'true');

  const next = platform.screens.sync({ screens, screenId: 'vehicles' });
  assert.strictEqual(next.shown, 'vehicles');
  assert.strictEqual(screens.filter((node) => node.style.display === 'block').length, 1);
  assert.strictEqual(platform.screens.screenIdFor({ id: 'reports-screen', className: 'screen' }), 'reports');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  assert.strictEqual(platform.permissions.can('households', platform.ACTION.VIEW, { role: 'SUPER_ADMIN' }), true);
  platform.permissions.set('households', platform.ACTION.DELETE, false);
  assert.strictEqual(platform.permissions.can('households', platform.ACTION.DELETE, { role: 'SUPER_ADMIN' }), false);
  assert.strictEqual(platform.permissions.normalizeModule('public-assets'), 'publicAssets');
  assert.strictEqual(platform.permissions.normalizeAction('update'), platform.ACTION.EDIT);
  platform.permissions.setMany({
    citizen: { update: true },
    public_assets: { create: true },
    household: { manage: true }
  });
  assert.strictEqual(platform.permissions.can('persons', platform.ACTION.EDIT), true);
  assert.strictEqual(platform.permissions.can('publicAssets', platform.ACTION.CREATE), true);
  assert.strictEqual(platform.permissions.can('households', platform.ACTION.DELETE), false);
  assert.strictEqual(platform.permissions.can('households', platform.ACTION.EDIT), true);
  assert.strictEqual(platform.permissions.canAll([{ module: 'citizen', action: 'update' }, { module: 'public_assets', action: 'create' }]), true);
  assert.strictEqual(platform.permissions.canAny([{ module: 'reports', action: 'export' }, { module: 'citizen', action: 'update' }]), true);
  platform.permissions.clear().loadUser({ permissions: { vehicle: { view: true } } });
  assert.strictEqual(platform.permissions.can('vehicles', platform.ACTION.VIEW), true);
  platform.permissions.loadMatrix({ roles: [{ permissions: { house: { edit: true } } }] });
  assert.strictEqual(platform.permissions.can('houses', 'update'), true);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const loading = platform.state.loading('households', { source: 'test' });
  assert.strictEqual(loading.status, platform.STATE.LOADING);
  assert.strictEqual(platform.state.loaded('households', [{ id: 1 }]).status, platform.STATE.LOADED);
  assert.strictEqual(platform.state.get('households').data[0].id, 1);
  assert.strictEqual(platform.state.empty('persons').status, platform.STATE.EMPTY);
  assert.strictEqual(platform.state.error('reports', 'Network').error, 'Network');
  assert.ok(platform.state.list().length >= 3);
  assert.ok(sandbox.listeners.some((event) => event.type === 'thon09:module-state-change'));
  assert.throws(() => platform.state.set('households', 'Done'), /Invalid module state/);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const calls = [];
  platform.actions.register('households.create', (context) => {
    calls.push(context);
    return 'created';
  });
  assert.strictEqual(platform.actions.dispatch('households.create', { payload: { from: 'test' } }), 'created');
  assert.strictEqual(calls[0].payload.from, 'test');

  const target = {
    dataset: { platformAction: 'households.create', id: '9' },
    closest(selector) {
      assert.strictEqual(selector, '[data-platform-action]');
      return this;
    }
  };
  let prevented = false;
  assert.strictEqual(platform.actions.handleClick({
    target,
    preventDefault() {
      prevented = true;
    }
  }), 'created');
  assert.strictEqual(prevented, true);
  assert.strictEqual(calls[1].dataset.id, '9');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const createButton = platform.components.button({
    label: 'Them',
    icon: 'fa-plus',
    action: 'households.create',
    variant: 'success',
    dataset: { id: '12' }
  });
  assert.strictEqual(createButton.tagName, 'BUTTON');
  assert.strictEqual(createButton.className, 'btn btn-success');
  assert.strictEqual(createButton.dataset.platformAction, 'households.create');
  assert.strictEqual(createButton.dataset.id, '12');
  assert.strictEqual(createButton.children[0].tagName, 'I');
  assert.strictEqual(createButton.children[1].textContent, 'Them');

  const badge = platform.components.badge({ label: 'Active', tone: 'success' });
  assert.strictEqual(badge.className, 'badge text-bg-success');
  assert.strictEqual(badge.textContent, 'Active');

  const input = platform.components.input({
    name: 'headName',
    value: 'Nguyen Van A',
    placeholder: 'Chu ho',
    required: true
  });
  assert.strictEqual(input.tagName, 'INPUT');
  assert.strictEqual(input.attributes.name, 'headName');
  assert.strictEqual(input.attributes.value, 'Nguyen Van A');
  assert.strictEqual(input.attributes.required, 'required');

  const select = platform.components.select({
    name: 'areaId',
    value: 'A2',
    options: [
      { value: 'A1', label: 'Khu 1' },
      { value: 'A2', label: 'Khu 2' }
    ]
  });
  assert.strictEqual(select.tagName, 'SELECT');
  assert.strictEqual(select.children.length, 2);
  assert.strictEqual(select.children[1].attributes.selected, 'selected');

  const filterBar = platform.components.filterBar({
    search: { placeholder: 'Tim ho' },
    filters: [
      { key: 'areaId', type: 'select', defaultValue: 'A1', options: [{ value: 'A1', label: 'Khu 1' }] },
      { key: 'status', label: 'Trang thai', defaultValue: 'active' }
    ]
  });
  assert.strictEqual(filterBar.className, 'platform-filter-bar');
  assert.strictEqual(filterBar.children.length, 3);
  assert.strictEqual(filterBar.children[0].attributes.type, 'search');
  assert.strictEqual(filterBar.children[1].dataset.filterKey, 'areaId');
  assert.strictEqual(filterBar.children[2].dataset.filterKey, 'status');

  const card = platform.components.card({ title: 'Ho gia dinh', subtitle: 'Danh sach' }, ['Noi dung']);
  assert.strictEqual(card.tagName, 'SECTION');
  assert.strictEqual(card.children[0].className, 'card-header');
  assert.strictEqual(card.children[1].className, 'card-body');

  const tabs = platform.components.tabs({
    activeKey: 'extended',
    action: 'households.tab',
    tabs: [
      { key: 'basic', label: 'Co ban', content: ['A'] },
      { key: 'extended', label: 'Mo rong', content: ['B'] }
    ]
  });
  assert.strictEqual(tabs.tagName, 'DIV');
  assert.strictEqual(tabs.children[0].attributes.role, 'tablist');
  assert.strictEqual(tabs.children[0].children[0].dataset.platformAction, 'households.tab');
  assert.strictEqual(tabs.children[0].children[0].dataset.tabKey, 'basic');
  assert.strictEqual(tabs.children[0].children[1].className, 'platform-tab-button active');
  assert.strictEqual(tabs.children[1].children[0].attributes.hidden, 'hidden');
  assert.strictEqual(Object.prototype.hasOwnProperty.call(tabs.children[1].children[1].attributes, 'hidden'), false);

  const upload = platform.components.upload({
    id: 'attachments',
    name: 'files',
    label: 'Tep dinh kem',
    accept: 'image/*,.pdf',
    helpText: 'Chon tep'
  });
  assert.strictEqual(upload.className, 'platform-upload');
  assert.strictEqual(upload.children[0].tagName, 'LABEL');
  assert.strictEqual(upload.children[0].attributes.for, 'attachments');
  assert.strictEqual(upload.children[1].attributes.type, 'file');
  assert.strictEqual(upload.children[1].attributes.name, 'files');
  assert.strictEqual(upload.children[1].attributes.multiple, 'multiple');
  assert.strictEqual(upload.children[2].textContent, 'Chon tep');

  platform.state.loading('households');
  const stateNode = platform.components.moduleState('households');
  assert.strictEqual(stateNode.className, 'platform-state platform-state-loading');

  const table = platform.components.table({
    columns: [
      { key: 'code', label: 'Ma ho', sortable: true },
      { key: 'headName', label: 'Chu ho' },
      { key: 'hidden', label: 'An', visible: false }
    ],
    rows: [{ id: 1, code: 'H001', headName: 'Nguyen Van A' }],
    rowKey: 'id'
  });
  assert.strictEqual(table.tagName, 'TABLE');
  assert.strictEqual(table.children[0].tagName, 'THEAD');
  assert.strictEqual(table.children[0].children[0].children.length, 2);
  assert.strictEqual(table.children[0].children[0].children[0].attributes['data-sortable'], 'true');
  assert.strictEqual(table.children[1].children[0].dataset.rowKey, 1);
  assert.strictEqual(table.children[1].children[0].children[0].children[0].textContent, 'H001');

  const emptyTable = platform.components.table({
    columns: [{ key: 'code', label: 'Ma ho' }],
    rows: [],
    emptyText: 'Trong'
  });
  assert.strictEqual(emptyTable.children[1].children[0].children[0].attributes.colspan, '1');
  assert.strictEqual(emptyTable.children[1].children[0].children[0].textContent, 'Trong');

  const pager = platform.components.pagination({
    page: 2,
    pageSize: 10,
    total: 31,
    action: 'households.page'
  });
  assert.strictEqual(pager.tagName, 'NAV');
  assert.strictEqual(pager.children[0].dataset.platformAction, 'households.page');
  assert.strictEqual(pager.children[0].dataset.page, '1');
  assert.strictEqual(pager.children[1].textContent, '2/4');
  assert.strictEqual(pager.children[2].dataset.page, '3');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  platform.forms.register({
    key: 'householdForm',
    moduleKey: 'households',
    modalKey: 'householdModal',
    sections: {
      basic: [
        { name: 'code', label: 'Ma ho', defaultValue: 'H001' },
        { name: 'headName', label: 'Chu ho' }
      ],
      linked: [
        { name: 'areaId', label: 'Dia ban', defaultValue: 'A1' }
      ]
    },
    actions: ['save', 'cancel']
  });
  assert.strictEqual(platform.forms.get('householdForm').moduleKey, 'households');
  assert.strictEqual(platform.forms.fieldsFor('householdForm', 'basic').length, 2);
  assert.strictEqual(platform.forms.fieldsFor('householdForm').length, 3);
  assert.strictEqual(platform.forms.defaultsFor('householdForm').code, 'H001');
  assert.strictEqual(platform.forms.defaultsFor('householdForm').headName, '');
  assert.strictEqual(platform.forms.sectionOrder().join(','), 'basic,linked,extended,attachments');

  const form = {
    elements: [
      { name: 'code', value: 'H002' },
      { name: 'active', type: 'checkbox', checked: true, value: '1' },
      { name: 'ignore', type: 'checkbox', checked: false, value: '1' },
      { name: 'disabled', disabled: true, value: 'x' }
    ]
  };
  const serialized = platform.forms.serialize(form);
  assert.strictEqual(serialized.code, 'H002');
  assert.strictEqual(serialized.active, '1');
  assert.strictEqual(Object.prototype.hasOwnProperty.call(serialized, 'ignore'), false);
  assert.strictEqual(Object.prototype.hasOwnProperty.call(serialized, 'disabled'), false);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  platform.lists.register({
    key: 'householdList',
    moduleKey: 'households',
    screenId: 'households',
    columns: [
      { key: 'code', label: 'Ma ho', sortable: true },
      { key: 'headName', label: 'Chu ho' },
      { key: 'internalNote', label: 'Ghi chu noi bo', visible: false }
    ],
    filters: [
      { key: 'areaId', label: 'Dia ban', defaultValue: 'A1' }
    ],
    pagination: { pageSize: 50 },
    rowActions: ['view', 'edit'],
    bulkActions: ['export']
  });
  const list = platform.lists.get('householdList');
  assert.strictEqual(list.moduleKey, 'households');
  assert.strictEqual(list.screenId, 'households');
  assert.strictEqual(platform.lists.columnsFor('householdList').length, 2);
  assert.strictEqual(platform.lists.columnsFor('householdList', { includeHidden: true }).length, 3);
  assert.strictEqual(platform.lists.columnsFor('householdList')[0].sortable, true);
  assert.strictEqual(platform.lists.filtersFor('householdList')[0].defaultValue, 'A1');
  assert.strictEqual(platform.lists.actionsFor('householdList').join(','), 'view,edit');
  assert.strictEqual(platform.lists.actionsFor('householdList', 'bulk').join(','), 'export');
  assert.strictEqual(platform.lists.paginationFor('householdList').pageSize, 50);
  const query = platform.lists.queryDefaults('householdList');
  assert.strictEqual(query.page, 1);
  assert.strictEqual(query.pageSize, 50);
  assert.strictEqual(query.filters.areaId, 'A1');
  assert.strictEqual(query.search, '');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  platform.forms.register({
    key: 'householdsForm',
    moduleKey: 'households',
    sections: {
      basic: [{ name: 'code', label: 'Ma ho' }]
    }
  });
  platform.lists.register({
    key: 'householdsList',
    moduleKey: 'households',
    columns: [{ key: 'code', label: 'Ma ho' }]
  });
  platform.crud.register({
    moduleKey: 'households',
    formKey: 'householdsForm',
    listKey: 'householdsList',
    operations: { import: true, export: true, log: true },
    rowActions: ['detail', 'edit'],
    bulkActions: ['export']
  });
  assert.strictEqual(platform.crud.enabledOperations('households').join(','), 'list,detail,create,edit,delete,import,export,log');
  const listOperation = platform.crud.operationFor('households', 'list', { role: 'SUPER_ADMIN' });
  assert.strictEqual(listOperation.enabled, true);
  assert.strictEqual(listOperation.allowed, true);
  assert.strictEqual(listOperation.route.path, '/households');
  assert.strictEqual(listOperation.list.columns[0].key, 'code');
  assert.strictEqual(listOperation.actionKey, 'households.list');

  const createOperation = platform.crud.operationFor('households', 'create');
  assert.strictEqual(createOperation.route.path, '/households/create');
  assert.strictEqual(createOperation.form.moduleKey, 'households');
  assert.strictEqual(createOperation.permissionAction, platform.ACTION.CREATE);

  platform.permissions.set('households', platform.ACTION.DELETE, false);
  const deleteOperation = platform.crud.operationFor('households', 'delete', { role: 'SUPER_ADMIN' });
  assert.strictEqual(deleteOperation.enabled, true);
  assert.strictEqual(deleteOperation.allowed, false);

  const workflow = platform.crud.workflowFor('households');
  assert.strictEqual(workflow.config.rowActions.join(','), 'detail,edit');
  assert.strictEqual(workflow.export.permissionAction, platform.ACTION.EXPORT);
  assert.strictEqual(workflow.log.permissionAction, platform.ACTION.VIEW);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  assert.strictEqual(platform.layout.modeFor(1280).key, 'desktop');
  assert.strictEqual(platform.layout.modeFor(900).key, 'tablet');
  assert.strictEqual(platform.layout.modeFor(390).key, 'mobile');
  assert.strictEqual(platform.layout.summary(390).navigation, 'bottomNavigation');
  assert.strictEqual(platform.layout.summary(390).modal, 'fullscreen');
  assert.strictEqual(platform.layout.summary(1280).regions.join(','), 'sidebar,content,modal');
  assert.strictEqual(platform.layout.regionsFor('mobile').map((region) => region.key).join(','), 'content,bottomNavigation,modal');
  platform.layout
    .registerRegion({ key: 'breadcrumb', selector: '[data-platform-breadcrumb]', role: 'navigation' })
    .registerMode({ key: 'wide', navigation: 'sidebar', modal: 'dialog', regions: ['sidebar', 'breadcrumb', 'content', 'modal'] });
  assert.strictEqual(platform.layout.regionsFor('wide').map((region) => region.key).join(','), 'sidebar,breadcrumb,content,modal');
  platform.layout.setBreakpoints({ mobileMax: 600, tabletMax: 1100 });
  assert.strictEqual(platform.layout.breakpoints().tabletMax, 1100);
  assert.strictEqual(platform.layout.modeFor(700).key, 'tablet');
}

{
  const sandbox = loadPlatform();
  const elementEvents = [];
  const element = {
    dispatchEvent(event) {
      elementEvents.push(event);
    }
  };
  const calls = [];
  sandbox.window.bootstrap = {
    Modal: {
      getOrCreateInstance(target) {
        assert.strictEqual(target, element);
        return {
          show() { calls.push('show'); },
          hide() { calls.push('hide'); }
        };
      }
    }
  };

  const platform = sandbox.window.Thon09Platform;
  platform.modals.registerBootstrap('householdModal', element);
  assert.strictEqual(platform.modals.open('householdModal', { id: 7 }).key, 'householdModal');
  assert.strictEqual(platform.modals.active().payload.id, 7);
  assert.strictEqual(platform.modals.close('householdModal').key, 'householdModal');
  assert.deepStrictEqual(calls, ['show', 'hide']);
  assert.deepStrictEqual(elementEvents.map((event) => event.type), ['thon09:modal-open', 'thon09:modal-close']);
}

{
  const sandbox = loadPlatform();
  const calls = [];
  sandbox.window.App.modals.user = {
    show() { calls.push('legacy-show'); },
    hide() { calls.push('legacy-hide'); }
  };
  assert.strictEqual(sandbox.window.Thon09Platform.modals.open('user', { mode: 'edit' }).key, 'user');
  assert.strictEqual(sandbox.window.Thon09Platform.modals.active().payload.mode, 'edit');
  assert.strictEqual(sandbox.window.Thon09Platform.modals.close('user').key, 'user');

  sandbox.window.App = { modals: {} };
  sandbox.window.App.modals.movement = {
    show() { calls.push('setter-show'); },
    hide() { calls.push('setter-hide'); }
  };
  assert.strictEqual(sandbox.window.Thon09Platform.modals.open('movement').key, 'movement');
  assert.strictEqual(sandbox.window.Thon09Platform.modals.close('movement').key, 'movement');
  assert.deepStrictEqual(calls, ['legacy-show', 'legacy-hide', 'setter-show', 'setter-hide']);
}

console.log('app-platform tests passed');
