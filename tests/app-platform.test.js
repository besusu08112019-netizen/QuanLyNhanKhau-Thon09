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
      if (selector === '[data-screen]' || selector === '[data-mobile-screen]' || selector.indexOf('data-screen') !== -1 || selector.indexOf('data-mobile-screen') !== -1) return nodes;
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
    'households,persons,temporaryResidence,temporaryAbsence,movements,publicAssets,houses,businessHouseholds,agriculture,livestock,vehicles,contributions'
  );
  assert.strictEqual(platform.menuRenderer.mobileModules()[0].mobileLabel, 'Hộ');
  assert.strictEqual(platform.modules.get('households').label, 'Hộ gia đình');
  assert.strictEqual(platform.modules.get('persons').label, 'Nhân khẩu');
  assert.strictEqual(platform.modules.get('temporaryResidence').label, 'Tạm trú');
  assert.strictEqual(platform.modules.get('temporaryAbsence').label, 'Tạm vắng');
  assert.strictEqual(platform.modules.get('movements').mobileLabel, 'Biến động');
  assert.strictEqual(platform.modules.get('publicAssets').mobileLabel, 'Công trình');
  assert.strictEqual(platform.modules.get('houses').mobileLabel, 'Nhà ở');
  assert.strictEqual(platform.modules.get('agriculture').mobileLabel, 'Nông nghiệp');
  assert.strictEqual(platform.modules.get('livestock').mobileLabel, 'Vật nuôi');
  assert.strictEqual(platform.menus.get('population').label, 'Quản lý dân cư');
  assert.strictEqual(platform.menus.get('assets').label, 'Quản lý tài sản');
  assert.strictEqual(platform.menus.get('production').label, 'Quản lý sản xuất');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const audit = platform.navigationMapping.audit();
  assert.strictEqual(audit.ok, true);
  assert.strictEqual(audit.issues.length, 0);
  assert.strictEqual(audit.menuItemCount, platform.modules.list().length);
  assert.ok(audit.routeCount >= audit.moduleCount);

  const requiredModules = [
    'households',
    'persons',
    'temporaryResidence',
    'temporaryAbsence',
    'movements',
    'publicAssets',
    'businessHouseholds',
    'livestock',
    'houses',
    'vehicles',
    'agriculture',
    'contributions'
  ];
  requiredModules.forEach((moduleKey) => {
    const record = audit.records.find((item) => item.moduleKey === moduleKey);
    assert.ok(record, moduleKey + ' should be present in navigation mapping');
    assert.strictEqual(record.exists, true);
    assert.ok(record.screenId);
    assert.ok(record.route);
  });
  assert.strictEqual(platform.navigationMapping.routesForModule('households').some((route) => route.action === 'edit'), true);

  platform.menus.upsert({ key: 'broken', label: 'Broken', items: ['missingModule'] });
  const broken = platform.navigationMapping.audit();
  assert.strictEqual(broken.ok, false);
  assert.strictEqual(broken.issues.some((item) => item.code === 'menu-module-missing'), true);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  const screenRoot = {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-screen-id], .screen');
      return screens;
    }
  };
  const domDocument = {
    querySelector(selector) {
      return {
        '[data-platform-screen-root]': screenRoot
      }[selector] || null;
    }
  };

  const coverage = platform.navigationDomCoverage.audit({
    document: domDocument,
    moduleKeys: ['households', 'persons', 'vehicles']
  });
  assert.strictEqual(coverage.ok, true);
  assert.strictEqual(coverage.screenCount, 3);
  assert.strictEqual(coverage.coveredCount, 3);
  assert.strictEqual(coverage.records[0].screenId, 'households');

  const missing = platform.navigationDomCoverage.audit({
    document: domDocument,
    moduleKeys: ['households', 'livestock']
  });
  assert.strictEqual(missing.ok, false);
  assert.strictEqual(missing.issues.some((item) => item.code === 'screen-dom-missing'), true);

  const duplicateRoot = {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-screen-id], .screen');
      return [screenNode('households'), screenNode('households')];
    }
  };
  const duplicateDocument = {
    querySelector(selector) {
      return {
        '[data-platform-screen-root]': duplicateRoot
      }[selector] || null;
    }
  };
  const duplicate = platform.navigationDomCoverage.audit({
    document: duplicateDocument,
    moduleKeys: ['households']
  });
  assert.strictEqual(duplicate.ok, false);
  assert.strictEqual(duplicate.issues.some((item) => item.code === 'screen-dom-duplicate'), true);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const nav = {
    textContent: 'old',
    dataset: {},
    children: [],
    appendChild(child) {
      this.children.push(child);
      return child;
    }
  };
  assert.strictEqual(platform.menuRenderer.renderMobile(nav), true);
  assert.strictEqual(nav.dataset.platformMenu, 'true');
  assert.strictEqual(nav.children[0].dataset.mobileScreen, 'households');
  assert.strictEqual(nav.children[0].dataset.module, 'households');
  assert.strictEqual(nav.children[0].dataset.route, '/households');
  assert.strictEqual(nav.children[0].dataset.action, 'list');
  assert.strictEqual(platform.navigationIntent.fromElement(nav.children[0]).moduleKey, 'households');
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
  const platform = loadPlatform().window.Thon09Platform;
  assert.strictEqual(platform.apiResources.endpoint('households', 'list', { query: { page: 2, search: 'A B' } }), '/households?page=2&search=A%20B');
  assert.strictEqual(platform.apiResources.endpoint('households', 'detail', { params: { id: 42 } }), '/households/42');
  assert.strictEqual(platform.apiResources.endpoint('households', 'update', { params: { id: 42 } }), '/households/42/edit');
  assert.strictEqual(platform.apiResources.endpoint('households', 'delete', { params: { id: 42 } }), '/households/42');
  assert.strictEqual(platform.apiResources.operation('households', 'list').route.path, '/households');
  assert.strictEqual(platform.apiResources.methodFor('list'), 'GET');
  assert.strictEqual(platform.apiResources.methodFor('edit'), 'PUT');
  assert.strictEqual(platform.apiResources.methodFor('delete'), 'DELETE');
  const inspectedEdit = platform.apiResources.inspect('households', 'edit', { params: { id: 42 } });
  assert.strictEqual(inspectedEdit.moduleKey, 'households');
  assert.strictEqual(inspectedEdit.action, 'edit');
  assert.strictEqual(inspectedEdit.operation, 'update');
  assert.strictEqual(inspectedEdit.method, 'PUT');
  assert.strictEqual(inspectedEdit.endpoint, '/households/42/edit');
  assert.strictEqual(inspectedEdit.route.path, '/households/:id/edit');
  assert.strictEqual(inspectedEdit.permissionAction, platform.ACTION.EDIT);

  const calls = [];
  const mockClient = {
    get(path) {
      calls.push(['get', path]);
      return { success: true, data: [] };
    },
    post(path, body) {
      calls.push(['post', path, body]);
      return { success: true, data: body };
    },
    put(path, body) {
      calls.push(['put', path, body]);
      return { success: true, data: body };
    },
    delete(path) {
      calls.push(['delete', path]);
      return { success: true, data: null };
    }
  };
  const resources = platform.createApiResourceService(mockClient, platform.router, platform.crud);
  assert.strictEqual(resources.list('persons', { page: 1 }).success, true);
  resources.create('persons', { name: 'A' });
  resources.update('persons', 9, { name: 'B' });
  resources.delete('persons', 9);
  assert.deepStrictEqual(calls.map((call) => call[0] + ':' + call[1]), [
    'get:/persons?page=1',
    'post:/persons/create',
    'put:/persons/9/edit',
    'delete:/persons/9'
  ]);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const calls = [];
  const beforeLoad = platform.moduleLoader.inspect('households', {
    loaders: {
      loadHouseholds() {
        calls.push('inspect-should-not-load');
      }
    }
  });
  assert.strictEqual(beforeLoad.registered, true);
  assert.strictEqual(beforeLoad.moduleKey, 'households');
  assert.strictEqual(beforeLoad.screenId, 'households');
  assert.strictEqual(beforeLoad.loaderName, 'loadHouseholds');
  assert.strictEqual(beforeLoad.available, true);
  assert.strictEqual(beforeLoad.stateStatus, platform.STATE.EMPTY);
  assert.strictEqual(beforeLoad.missingReason, null);
  assert.deepStrictEqual(calls, []);

  const result = platform.moduleLoader.load('households', {
    route: '/households',
    loaders: {
      loadHouseholds(context) {
        calls.push(context.moduleKey + ':' + context.screenId + ':' + context.route);
        return { rows: [1] };
      }
    }
  });
  assert.strictEqual(result.loaded, true);
  assert.strictEqual(result.moduleKey, 'households');
  assert.deepStrictEqual(calls, ['households:households:/households']);
  assert.strictEqual(platform.state.get('households').status, platform.STATE.LOADED);
  assert.strictEqual(platform.state.get('households').data.rows.length, 1);
  assert.strictEqual(platform.moduleLoader.inspect('households').stateStatus, platform.STATE.LOADED);

  const skipped = platform.moduleLoader.load('vehicles');
  assert.strictEqual(skipped.skipped, true);
  assert.strictEqual(skipped.loaderName, null);
  const missingLoader = platform.moduleLoader.inspect('vehicles');
  assert.strictEqual(missingLoader.available, false);
  assert.strictEqual(missingLoader.missingReason, 'loaderNotConfigured');
  assert.strictEqual(platform.moduleLoader.inspect('missing').missingReason, 'moduleNotRegistered');

  assert.throws(() => platform.moduleLoader.load('persons', {
    loaders: {
      loadPersons() {
        throw new Error('Loader failed');
      }
    }
  }), /Loader failed/);
  assert.strictEqual(platform.state.get('persons').status, platform.STATE.ERROR);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const mockResources = {
    inspect(moduleKey, action, options) {
      return {
        moduleKey,
        action,
        operation: action,
        method: action === 'delete' ? 'DELETE' : 'GET',
        endpoint: '/' + moduleKey + (options && options.params && options.params.id ? '/' + options.params.id : '')
      };
    },
    list(moduleKey, query) {
      assert.strictEqual(moduleKey, 'households');
      assert.strictEqual(query.page, 1);
      return { success: true, data: [{ id: 1 }], meta: { total: 1 } };
    },
    detail(moduleKey, id) {
      return { success: true, data: { id: id } };
    },
    create(moduleKey, body) {
      return { success: true, data: body };
    },
    update(moduleKey, id, body) {
      return { success: true, data: Object.assign({ id: id }, body) };
    },
    delete() {
      return { success: true, data: [] };
    }
  };
  const data = platform.createCrudDataService(mockResources, platform.state);
  const beforeInspect = data.inspect('households', 'detail', { params: { id: 2 } });
  assert.strictEqual(beforeInspect.moduleKey, 'households');
  assert.strictEqual(beforeInspect.action, 'detail');
  assert.strictEqual(beforeInspect.endpoint, '/households/2');
  assert.strictEqual(beforeInspect.stateStatus, platform.STATE.EMPTY);
  assert.strictEqual(beforeInspect.state, null);
  assert.strictEqual(data.list('households', { page: 1 }).success, true);
  assert.strictEqual(platform.state.get('households').status, platform.STATE.LOADED);
  assert.strictEqual(platform.state.get('households').data[0].id, 1);
  assert.strictEqual(data.inspect('households').stateStatus, platform.STATE.LOADED);
  assert.strictEqual(data.inspect('households').state.data[0].id, 1);
  assert.strictEqual(data.detail('households', 2).data.id, 2);
  assert.strictEqual(data.create('households', { code: 'H1' }).data.code, 'H1');
  assert.strictEqual(data.update('households', 3, { code: 'H3' }).data.id, 3);
  assert.strictEqual(data.delete('households', 3).success, true);
  assert.strictEqual(platform.state.get('households').status, platform.STATE.EMPTY);

  const failing = platform.createCrudDataService({
    list() {
      throw new Error('Network down');
    }
  }, platform.state);
  assert.throws(() => failing.list('persons'), /Network down/);
  assert.strictEqual(platform.state.get('persons').status, platform.STATE.ERROR);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const result = platform.navigation.navigate('/persons', { source: 'menu' });
  assert.strictEqual(result.moduleKey, 'persons');
  assert.strictEqual(result.screenId, 'persons');
  assert.strictEqual(result.route, '/persons');
  assert.strictEqual(sandbox.window.Thon09NavigationController.calls[0].screen, 'persons');
  assert.strictEqual(sandbox.window.App.route, '/persons');
  assert.strictEqual(sandbox.window.App.moduleKey, 'persons');
  assert.strictEqual(sandbox.window.App.screen, 'persons');
  assert.strictEqual(sandbox.window.App.action, 'list');
  assert.strictEqual(platform.appState.get().moduleKey, 'persons');
  assert.strictEqual(platform.navigationExecutor.inspect().screen, 'persons');
  assert.strictEqual(platform.navigationExecutor.inspect().controllerAvailable, true);
  assert.strictEqual(platform.navigationExecutor.inspect().appMirrored, true);
  assert.strictEqual(result.transition.source, 'menu');
  assert.strictEqual(result.transition.screenId, 'persons');
  assert.strictEqual(result.transition.previous, null);
  assert.strictEqual(platform.navigationTransitions.current().executor.controllerAvailable, true);

  const next = platform.navigation.navigate('/vehicles/7', { source: 'detail-link' });
  assert.strictEqual(next.transition.previous.screenId, 'persons');
  assert.strictEqual(platform.navigationTransitions.history().length, 2);
  assert.strictEqual(platform.navigationTransitions.current().source, 'detail-link');
  assert.strictEqual(platform.navigationTransitions.clear(), true);
  assert.strictEqual(platform.navigationTransitions.count(), 0);
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
  const fromMenu = platform.navigationIntent.fromMenu('households');
  assert.strictEqual(fromMenu.moduleKey, 'households');
  assert.strictEqual(fromMenu.screenId, 'households');
  assert.strictEqual(fromMenu.route, '/households');
  assert.strictEqual(fromMenu.source, 'menu');

  const moduleNode = {
    dataset: { module: 'vehicles', action: 'detail', route: '/vehicles/7' },
    getAttribute() {
      return null;
    }
  };
  const fromModuleNode = platform.navigationIntent.fromElement(moduleNode);
  assert.strictEqual(fromModuleNode.moduleKey, 'vehicles');
  assert.strictEqual(fromModuleNode.action, 'detail');
  assert.strictEqual(fromModuleNode.route, '/vehicles/7');
  assert.strictEqual(fromModuleNode.source, 'element');

  const screenNode = {
    dataset: { screen: 'persons' },
    getAttribute() {
      return null;
    }
  };
  assert.strictEqual(platform.navigationIntent.fromElement(screenNode).moduleKey, 'persons');

  const routeNode = {
    dataset: {},
    getAttribute(name) {
      return name === 'href' ? '/temporary-absence' : null;
    }
  };
  assert.strictEqual(platform.navigationIntent.fromElement(routeNode).moduleKey, 'temporaryAbsence');

  const childNode = {
    dataset: {},
    parentNode: moduleNode,
    getAttribute() {
      return null;
    }
  };
  assert.strictEqual(platform.navigationIntent.closestElement(childNode), moduleNode);
  assert.strictEqual(platform.navigationIntent.fromEvent({ target: childNode }).source, 'event');

  const emptyNode = {
    dataset: {},
    getAttribute(name) {
      return name === 'href' ? '#' : null;
    }
  };
  assert.strictEqual(platform.navigationIntent.fromElement(emptyNode), null);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const moduleNode = {
    dataset: { module: 'vehicles', action: 'detail', route: '/vehicles/7' },
    getAttribute() {
      return null;
    }
  };
  const event = {
    target: moduleNode,
    prevented: false,
    stopped: false,
    preventDefault() {
      this.prevented = true;
    },
    stopPropagation() {
      this.stopped = true;
    }
  };
  const handled = platform.navigationDelegation.handleClick(event, { stopPropagation: true });
  assert.strictEqual(handled.moduleKey, 'vehicles');
  assert.strictEqual(handled.action, 'detail');
  assert.strictEqual(event.prevented, true);
  assert.strictEqual(event.stopped, true);
  assert.strictEqual(sandbox.window.Thon09NavigationController.calls[0].screen, 'vehicles');
  assert.strictEqual(sandbox.window.App.screen, 'vehicles');
  assert.strictEqual(handled.transition.source, 'event');
  assert.strictEqual(handled.transition.intent.moduleKey, 'vehicles');
  assert.strictEqual(platform.navigationTransitions.current().screenId, 'vehicles');

  const root = {
    listeners: {},
    addEventListener(name, handler) {
      this.listeners[name] = handler;
    },
    removeEventListener(name, handler) {
      if (this.listeners[name] === handler) delete this.listeners[name];
    }
  };
  const unbind = platform.navigationDelegation.bind(root);
  assert.strictEqual(platform.navigationDelegation.bindingCount(), 1);
  root.listeners.click({ target: { dataset: { screen: 'persons' }, getAttribute() { return null; }, preventDefault() {} } });
  assert.strictEqual(platform.navigation.current().screenId, 'persons');
  assert.strictEqual(platform.navigationTransitions.current().previous.screenId, 'vehicles');
  assert.strictEqual(unbind(), true);
  assert.strictEqual(unbind(), false);
  assert.strictEqual(platform.navigationDelegation.bindingCount(), 0);

  const ignored = platform.navigationDelegation.handleClick({
    defaultPrevented: true,
    target: moduleNode,
    preventDefault() {
      throw new Error('should not prevent already prevented event');
    }
  });
  assert.strictEqual(ignored, null);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const crumbs = platform.breadcrumbs.fromRoute('/households/42/edit');
  assert.strictEqual(crumbs.map((crumb) => crumb.label).join('>'), 'Dashboard>Quản lý dân cư>Hộ gia đình>Chinh sua');
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
  assert.strictEqual(root.children.map((child) => child.textContent).join('>'), 'Dashboard>Quản lý phương tiện>Quản lý xe cộ>Chi tiet');
  assert.strictEqual(root.children[0].tagName, 'A');
  assert.strictEqual(root.children[root.children.length - 1].className, 'breadcrumb-item active');
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const stateChanges = [];
  const unsubscribe = platform.appState.subscribe((state) => {
    stateChanges.push(state.moduleKey + ':' + state.action);
  }, { moduleKey: 'households' });
  assert.strictEqual(platform.appState.subscriberCount(), 1);

  const next = platform.appState.set({ route: '/households/42/edit', width: 390 });
  assert.strictEqual(next.route, '/households/42/edit');
  assert.strictEqual(next.moduleKey, 'households');
  assert.strictEqual(next.screenId, 'households');
  assert.strictEqual(next.action, 'edit');
  assert.strictEqual(next.params.id, '42');
  assert.strictEqual(next.layout.mode, 'mobile');
  assert.strictEqual(next.breadcrumbs.map((crumb) => crumb.label).join('>'), 'Dashboard>Quản lý dân cư>Hộ gia đình>Chinh sua');
  assert.ok(sandbox.listeners.some((event) => event.type === 'thon09:app-state-change'));

  const patched = platform.appState.patch({ action: 'detail', params: { id: '99' }, width: 1280 });
  assert.strictEqual(patched.action, 'detail');
  assert.strictEqual(patched.params.id, '99');
  assert.strictEqual(patched.layout.mode, 'desktop');
  assert.deepStrictEqual(stateChanges, ['households:edit', 'households:detail']);
  assert.strictEqual(unsubscribe(), true);
  assert.strictEqual(unsubscribe(), false);
  assert.strictEqual(platform.appState.subscriberCount(), 0);

  const reset = platform.appState.reset();
  assert.strictEqual(reset.moduleKey, 'dashboard');
  assert.strictEqual(platform.appState.get().screenId, 'dashboard');
  assert.deepStrictEqual(stateChanges, ['households:edit', 'households:detail']);
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
  const binding = platform.navigation.bindHistory(platform.history, { width: 390 });
  assert.strictEqual(binding.start(), true);
  assert.strictEqual(binding.start(), false);

  sandbox.window.location.pathname = '/vehicles/7';
  sandbox.windowListeners.popstate({ state: { route: '/vehicles/7' } });
  assert.strictEqual(sandbox.window.Thon09NavigationController.calls[0].screen, 'vehicles');
  assert.strictEqual(platform.navigation.current().moduleKey, 'vehicles');
  assert.strictEqual(platform.navigation.current().options.source, 'popstate');
  assert.strictEqual(platform.navigation.current().options.historyState.route, '/vehicles/7');
  assert.strictEqual(sandbox.window.App.screen, 'vehicles');
  assert.strictEqual(binding.active(), true);
  assert.strictEqual(binding.stop(), true);
  assert.strictEqual(binding.active(), false);
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
  assert.strictEqual(breadcrumbRoot.children.map((child) => child.textContent).join('>'), 'Dashboard>Quản lý phương tiện>Quản lý xe cộ>Chi tiet');

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
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const required = platform.navigationScopes.resolve('requiredBusinessModules');
  assert.strictEqual(required.ok, true);
  assert.strictEqual(required.moduleKeys.join(','), [
    'households',
    'persons',
    'temporaryResidence',
    'temporaryAbsence',
    'movements',
    'publicAssets',
    'houses',
    'businessHouseholds',
    'agriculture',
    'livestock',
    'vehicles',
    'contributions'
  ].join(','));
  assert.strictEqual(platform.navigationScopes.resolve('desktopModules').moduleKeys.length, 12);
  assert.strictEqual(platform.navigationScopes.resolve('dashboardModules').moduleKeys.join(','), [
    'dashboard',
    'dashboardHouseholds',
    'dashboardPopulation',
    'dashboardBusiness',
    'dashboardVehicles',
    'dashboardLivestock',
    'dashboardGis',
    'dashboardReports'
  ].join(','));
  assert.strictEqual(platform.navigationScopes.resolve('population').moduleKeys.join(','), [
    'households',
    'persons',
    'temporaryResidence',
    'temporaryAbsence',
    'movements'
  ].join(','));
  const missing = platform.navigationScopes.resolve(['households', 'missingModule']);
  assert.strictEqual(missing.ok, false);
  assert.strictEqual(missing.issues.some((item) => item.code === 'scope-module-missing'), true);

  const routeCoverage = platform.navigationRouteCoverage.audit({ navigationScope: 'requiredBusinessModules' });
  assert.strictEqual(routeCoverage.ok, true);
  assert.strictEqual(routeCoverage.moduleCount, 12);
  assert.strictEqual(routeCoverage.coveredCount, 12);
  assert.strictEqual(routeCoverage.requiredActions.join(','), 'list,create,detail,edit');

  const routeBlocked = platform.navigationRouteCoverage.audit({ navigationScope: ['gis'] });
  assert.strictEqual(routeBlocked.ok, false);
  assert.strictEqual(routeBlocked.issues.some((item) => item.code === 'route-action-missing'), true);

  const dashboardRoutes = platform.navigationRouteCoverage.audit({
    navigationScope: 'dashboardModules',
    actions: ['list']
  });
  assert.strictEqual(dashboardRoutes.ok, true);
  assert.strictEqual(dashboardRoutes.moduleCount, 8);
  assert.strictEqual(dashboardRoutes.coveredCount, 8);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const dashboardScreens = [
    'dashboard',
    'dashboardHouseholds',
    'dashboardPopulation',
    'dashboardBusiness',
    'dashboardVehicles',
    'dashboardLivestock',
    'dashboardGis',
    'dashboardReports'
  ];
  const screenRoot = {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-screen-id], .screen');
      return dashboardScreens.map(screenNode);
    }
  };
  const domDocument = {
    querySelector(selector) {
      return selector === '[data-platform-screen-root]' ? screenRoot : null;
    }
  };

  const dashboard = platform.moduleMigration.inspectModule('dashboard', { document: domDocument, stage: 'navigation' });
  assert.strictEqual(dashboard.ready, true);
  assert.strictEqual(dashboard.routeActions.indexOf('list') !== -1, true);
  assert.strictEqual(dashboard.domCoverage.ok, true);
  assert.strictEqual(dashboard.loader.loaderName, 'loadDashboard');

  const dashboardScope = platform.moduleMigration.inspect({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardScope.ready, true);
  assert.strictEqual(dashboardScope.moduleCount, 8);
  assert.strictEqual(dashboardScope.readyCount, 8);

  const dashboardPlan = platform.moduleMigration.plan({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardPlan.nextModuleKey, 'dashboard');
  assert.strictEqual(dashboardPlan.readyModules.length, 8);
  assert.strictEqual(dashboardPlan.blockedModules.length, 0);

  const dashboardPlanAfterFirst = platform.moduleMigration.plan({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation',
    completedModules: ['dashboard']
  });
  assert.strictEqual(dashboardPlanAfterFirst.nextModuleKey, 'dashboardHouseholds');
  assert.strictEqual(dashboardPlanAfterFirst.completedCount, 1);

  const initialProgress = platform.moduleMigration.progress({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(initialProgress.nextModuleKey, 'dashboard');
  assert.strictEqual(initialProgress.storedCompletedModules.length, 0);

  const afterDashboard = platform.moduleMigration.markComplete('dashboard', {
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(afterDashboard.nextModuleKey, 'dashboardHouseholds');
  assert.strictEqual(afterDashboard.completedModules.join(','), 'dashboard');
  assert.strictEqual(afterDashboard.storedCompletedModules.join(','), 'dashboard');

  const resetProgress = platform.moduleMigration.resetProgress({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(resetProgress.nextModuleKey, 'dashboard');
  assert.strictEqual(resetProgress.storedCompletedModules.length, 0);

  platform.moduleMigration.markComplete('dashboard', {
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  const migrationReport = platform.moduleMigration.report({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stages: ['navigation', 'runtime']
  });
  assert.strictEqual(migrationReport.ready, true);
  assert.strictEqual(migrationReport.stageCount, 2);
  assert.strictEqual(migrationReport.nextStage, 'navigation');
  assert.strictEqual(migrationReport.nextModuleKey, 'dashboardHouseholds');
  assert.strictEqual(migrationReport.stages[0].progressKey, 'navigation:migrationDashboard');
  assert.strictEqual(migrationReport.stages[0].completedModules.join(','), 'dashboard');
  assert.strictEqual(migrationReport.stages[1].nextModuleKey, 'dashboard');
  const migrationAssertReport = platform.moduleMigration.assertReport({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stages: ['navigation', 'runtime']
  });
  assert.strictEqual(migrationAssertReport.ready, true);
  assert.strictEqual(migrationAssertReport.nextModuleKey, 'dashboardHouseholds');

  const dashboardReports = platform.moduleMigration.reports({
    document: domDocument,
    scopes: ['migrationDashboard'],
    stages: ['navigation']
  });
  assert.strictEqual(dashboardReports.length, 1);
  assert.strictEqual(dashboardReports[0].scope.key, 'migrationDashboard');
  assert.strictEqual(dashboardReports[0].stages[0].moduleCount, 8);
  const dashboardAssertReports = platform.moduleMigration.assertReports({
    document: domDocument,
    scopes: ['migrationDashboard'],
    stages: ['navigation']
  });
  assert.strictEqual(dashboardAssertReports.length, 1);
  assert.strictEqual(dashboardAssertReports[0].ready, true);

  const nextHandoff = platform.moduleMigration.handoff({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(nextHandoff.moduleKey, 'dashboardHouseholds');
  assert.strictEqual(nextHandoff.isNext, true);
  assert.strictEqual(nextHandoff.canMigrate, true);
  assert.strictEqual(nextHandoff.checklist.some((item) => item.key === 'routes' && item.ready), true);
  assert.strictEqual(nextHandoff.checklist.some((item) => item.key === 'dom' && item.ready), true);

  const blockedHandoff = platform.moduleMigration.handoff({
    moduleKey: 'vehicles',
    stage: 'navigation',
    require: { dom: false, loaderConfigured: true }
  });
  assert.strictEqual(blockedHandoff.moduleKey, 'vehicles');
  assert.strictEqual(blockedHandoff.canMigrate, false);
  assert.strictEqual(blockedHandoff.checklist.some((item) => item.key === 'loaderConfigured' && !item.ready), true);

  const advanced = platform.moduleMigration.advance({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(advanced.completed, true);
  assert.strictEqual(advanced.moduleKey, 'dashboardHouseholds');
  assert.strictEqual(advanced.progress.nextModuleKey, 'dashboardPopulation');
  assert.strictEqual(advanced.progress.storedCompletedModules.join(','), 'dashboard,dashboardHouseholds');

  const dashboardQueue = platform.moduleMigration.queue({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardQueue.completedCount, 2);
  assert.strictEqual(dashboardQueue.remainingCount, 6);
  assert.strictEqual(dashboardQueue.percentComplete, 25);
  assert.strictEqual(dashboardQueue.nextModuleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardQueue.modules[0].status, 'completed');
  assert.strictEqual(dashboardQueue.modules[2].isNext, true);
  assert.strictEqual(dashboardQueue.upcomingModules[0], 'dashboardPopulation');
  assert.strictEqual(dashboardQueue.blockedQueue.length, 0);
  const dashboardBlockers = platform.moduleMigration.blockers({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardBlockers.ready, true);
  assert.strictEqual(dashboardBlockers.blockedCount, 0);
  assert.strictEqual(dashboardBlockers.blockerCount, 0);

  const dashboardMatrix = platform.moduleMigration.matrix({
    document: domDocument,
    scopes: ['migrationDashboard'],
    stages: ['navigation', 'runtime']
  });
  assert.strictEqual(dashboardMatrix.ready, true);
  assert.strictEqual(dashboardMatrix.scopeCount, 1);
  assert.strictEqual(dashboardMatrix.stageCount, 2);
  assert.strictEqual(dashboardMatrix.rows[0].scope, 'migrationDashboard');
  assert.strictEqual(dashboardMatrix.rows[0].stages[0].completedCount, 2);
  assert.strictEqual(dashboardMatrix.rows[0].stages[0].nextModuleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardMatrix.rows[0].stages[1].nextModuleKey, 'dashboard');
  assert.strictEqual(dashboardMatrix.next.scope, 'migrationDashboard');
  assert.strictEqual(dashboardMatrix.next.stage, 'navigation');
  const dashboardAssertMatrix = platform.moduleMigration.assertMatrix({
    document: domDocument,
    scopes: ['migrationDashboard'],
    stages: ['navigation', 'runtime']
  });
  assert.strictEqual(dashboardAssertMatrix.ready, true);
  assert.strictEqual(dashboardAssertMatrix.next.moduleKey, 'dashboardPopulation');

  const dashboardGate = platform.moduleMigration.gate({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardGate.canAdvance, true);
  assert.strictEqual(dashboardGate.reason, null);
  assert.strictEqual(dashboardGate.nextModuleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardGate.handoff.moduleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardGate.queue.percentComplete, 25);
  assert.strictEqual(dashboardGate.blockers.blockedCount, 0);
  const dashboardAssertGate = platform.moduleMigration.assertGate({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardAssertGate.canAdvance, true);
  assert.strictEqual(dashboardAssertGate.nextModuleKey, 'dashboardPopulation');

  const dashboardCurrent = platform.moduleMigration.current({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardCurrent.moduleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardCurrent.nextModuleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardCurrent.queuePosition, 3);
  assert.strictEqual(dashboardCurrent.percentComplete, 25);
  assert.strictEqual(dashboardCurrent.gate.canAdvance, true);
  assert.strictEqual(dashboardCurrent.handoff.moduleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardCurrent.blockers.blockedCount, 0);
  assert.strictEqual(dashboardCurrent.timeline.eventCount, 5);
  assert.strictEqual(dashboardCurrent.timeline.latest.length, 5);

  const dashboardNext = platform.moduleMigration.next({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardNext.moduleKey, dashboardCurrent.moduleKey);

  const dashboardCheckpoint = platform.moduleMigration.checkpoint({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardCheckpoint.nextAction, 'advance');
  assert.strictEqual(dashboardCheckpoint.moduleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardCheckpoint.canAdvance, true);
  assert.strictEqual(dashboardCheckpoint.current.moduleKey, dashboardCurrent.moduleKey);
  assert.strictEqual(dashboardCheckpoint.report.nextModuleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardCheckpoint.queue.percentComplete, 25);
  assert.strictEqual(dashboardCheckpoint.blockers.blockedCount, 0);
  assert.strictEqual(dashboardCheckpoint.timeline.eventCount, 5);
  const dashboardAssertCheckpoint = platform.moduleMigration.assertCheckpoint({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(dashboardAssertCheckpoint.moduleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardAssertCheckpoint.nextAction, 'advance');
  const dashboardStatus = platform.moduleMigration.status({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stages: ['navigation', 'runtime']
  });
  assert.strictEqual(dashboardStatus.ready, true);
  assert.strictEqual(dashboardStatus.blocked, false);
  assert.strictEqual(dashboardStatus.nextAction, 'advance');
  assert.strictEqual(dashboardStatus.moduleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardStatus.checkpoint.moduleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardStatus.report.nextModuleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardStatus.matrix.next.moduleKey, 'dashboardPopulation');
  assert.strictEqual(dashboardStatus.timeline.eventCount, 5);
  const dashboardAssertStatus = platform.moduleMigration.assertStatus({
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stages: ['navigation', 'runtime']
  });
  assert.strictEqual(dashboardAssertStatus.ready, true);
  assert.strictEqual(dashboardAssertStatus.moduleKey, 'dashboardPopulation');

  const outOfOrderComplete = platform.moduleMigration.completeHandoff('dashboardVehicles', {
    document: domDocument,
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(outOfOrderComplete.completed, false);
  assert.strictEqual(outOfOrderComplete.reason, 'out-of-order');
  assert.strictEqual(outOfOrderComplete.progress.storedCompletedModules.join(','), 'dashboard,dashboardHouseholds');

  const blockedComplete = platform.moduleMigration.completeHandoff('vehicles', {
    stage: 'navigation',
    require: { dom: false, loaderConfigured: true },
    allowOutOfOrder: true
  });
  assert.strictEqual(blockedComplete.completed, false);
  assert.strictEqual(blockedComplete.reason, 'handoff-blocked');
  assert.strictEqual(blockedComplete.handoff.canMigrate, false);
  const migrationTimeline = platform.moduleMigration.timeline({
    navigationScope: 'migrationDashboard',
    stage: 'navigation'
  });
  assert.strictEqual(migrationTimeline.eventCount, 6);
  assert.strictEqual(migrationTimeline.events[0].type, 'markComplete');
  assert.strictEqual(migrationTimeline.events[0].moduleKey, 'dashboard');
  assert.strictEqual(migrationTimeline.events[4].type, 'completeHandoff');
  assert.strictEqual(migrationTimeline.events[4].moduleKey, 'dashboardHouseholds');
  assert.strictEqual(migrationTimeline.events[5].reason, 'out-of-order');
  const handoffTimeline = platform.moduleMigration.timeline({
    navigationScope: 'migrationDashboard',
    stage: 'navigation',
    type: 'completeHandoff'
  });
  assert.strictEqual(handoffTimeline.eventCount, 2);
  assert.strictEqual(handoffTimeline.events[0].completed, true);
  assert.strictEqual(handoffTimeline.events[1].completed, false);
  platform.moduleMigration.resetProgress({ all: true });
  const allTimeline = platform.moduleMigration.timeline({ all: true });
  assert.strictEqual(allTimeline.events[allTimeline.events.length - 1].type, 'resetProgress');
  assert.strictEqual(allTimeline.events[allTimeline.events.length - 1].reason, 'all');

  const loaderBlocked = platform.moduleMigration.inspectModule('vehicles', {
    stage: 'navigation',
    require: { dom: false, loaderConfigured: true }
  });
  assert.strictEqual(loaderBlocked.ready, false);
  assert.strictEqual(loaderBlocked.issues.some((item) => item.code === 'loader-not-configured'), true);

  const crudReady = platform.moduleMigration.inspect({ navigationScope: 'requiredBusinessModules', stage: 'crud', require: { dom: false } });
  assert.strictEqual(crudReady.ready, true);
  assert.strictEqual(crudReady.moduleCount, 12);
  assert.strictEqual(crudReady.issues.length, 0);
  const householdCrudReady = platform.moduleMigration.inspectModule('households', { stage: 'crud', require: { dom: false } });
  assert.strictEqual(householdCrudReady.ready, true);
  assert.strictEqual(householdCrudReady.crud.operations.length, 4);
  assert.strictEqual(householdCrudReady.crud.operations.every((operation) => operation.hasRoute && operation.hasList !== false && operation.hasForm !== false), true);
  const personCrudReady = platform.moduleMigration.inspectModule('persons', { stage: 'crud', require: { dom: false } });
  assert.strictEqual(personCrudReady.ready, true);
  assert.strictEqual(personCrudReady.crud.operations.length, 4);
  assert.strictEqual(personCrudReady.crud.operations.every((operation) => operation.hasRoute && operation.hasList !== false && operation.hasForm !== false), true);
  const crudReportReady = platform.moduleMigration.assertReport({ navigationScope: 'requiredBusinessModules', stages: ['crud'], require: { dom: false } });
  assert.strictEqual(crudReportReady.ready, true);
  const crudReportsReady = platform.moduleMigration.assertReports({ scopes: ['requiredBusinessModules'], stages: ['crud'], require: { dom: false } });
  assert.strictEqual(crudReportsReady[0].ready, true);
  const crudPlan = platform.moduleMigration.plan({ navigationScope: 'requiredBusinessModules', stage: 'crud', require: { dom: false } });
  assert.strictEqual(crudPlan.nextModuleKey, 'households');
  assert.strictEqual(crudPlan.readyModules.length, 12);
  assert.strictEqual(crudPlan.blockedModules.length, 0);
  const crudQueue = platform.moduleMigration.queue({ navigationScope: 'requiredBusinessModules', stage: 'crud', require: { dom: false } });
  assert.strictEqual(crudQueue.percentComplete, 0);
  assert.strictEqual(crudQueue.remainingCount, 12);
  assert.strictEqual(crudQueue.upcomingModules.length, 12);
  assert.strictEqual(crudQueue.blockedQueue.length, 0);
  const crudBlockers = platform.moduleMigration.blockers({ navigationScope: 'requiredBusinessModules', stage: 'crud', require: { dom: false } });
  assert.strictEqual(crudBlockers.ready, true);
  assert.strictEqual(crudBlockers.blockedCount, 0);
  assert.strictEqual(crudBlockers.blockerCount, 0);
  assert.strictEqual(crudBlockers.codes.length, 0);
  assert.strictEqual(crudBlockers.modules.length, 0);
  const crudMatrix = platform.moduleMigration.matrix({
    scopes: ['requiredBusinessModules'],
    stages: ['crud'],
    require: { dom: false }
  });
  assert.strictEqual(crudMatrix.ready, true);
  assert.strictEqual(crudMatrix.blockedRows.length, 0);
  assert.strictEqual(crudMatrix.rows[0].stages[0].blockedCount, 0);
  assert.strictEqual(crudMatrix.rows[0].stages[0].nextModuleKey, 'households');
  assert.strictEqual(crudMatrix.rows[0].stages[0].blockerCount, 0);
  assert.strictEqual(platform.moduleMigration.assertMatrix({
    scopes: ['requiredBusinessModules'],
    stages: ['crud'],
    require: { dom: false }
  }).ready, true);
  const crudStatus = platform.moduleMigration.status({
    navigationScope: 'requiredBusinessModules',
    stages: ['crud'],
    require: { dom: false }
  });
  assert.strictEqual(crudStatus.ready, true);
  assert.strictEqual(crudStatus.blocked, false);
  assert.strictEqual(crudStatus.nextAction, 'advance');
  assert.strictEqual(crudStatus.moduleKey, 'households');
  assert.strictEqual(crudStatus.report.blockedStages.length, 0);
  assert.strictEqual(crudStatus.matrix.ready, true);
  assert.strictEqual(crudStatus.blockedRows.length, 0);
  assert.strictEqual(platform.moduleMigration.assertStatus({
    navigationScope: 'requiredBusinessModules',
    stages: ['crud'],
    require: { dom: false }
  }).ready, true);
  const crudGate = platform.moduleMigration.gate({ navigationScope: 'requiredBusinessModules', stage: 'crud', require: { dom: false } });
  assert.strictEqual(crudGate.canAdvance, true);
  assert.strictEqual(crudGate.reason, null);
  assert.strictEqual(crudGate.nextModuleKey, 'households');
  assert.strictEqual(crudGate.handoff.moduleKey, 'households');
  assert.strictEqual(crudGate.blockedCount, 0);
  assert.strictEqual(platform.moduleMigration.assertGate({ navigationScope: 'requiredBusinessModules', stage: 'crud', require: { dom: false } }).nextModuleKey, 'households');
  assert.strictEqual(platform.moduleMigration.assertCanAdvance({ navigationScope: 'requiredBusinessModules', stage: 'crud', require: { dom: false } }).canAdvance, true);
  assert.strictEqual(platform.moduleMigration.assertCheckpoint({ navigationScope: 'requiredBusinessModules', stage: 'crud', require: { dom: false } }).moduleKey, 'households');
  assert.strictEqual(platform.moduleMigration.assertReady({ navigationScope: 'requiredBusinessModules', stage: 'crud', require: { dom: false } }).ready, true);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  const sidebarRoot = navRoot(['households', 'persons', 'vehicles'], 'screen');
  const bottomRoot = navRoot(['households', 'persons', 'vehicles'], 'mobileScreen');
  const state = platform.navigation.navigate('/vehicles', { source: 'diagnostic-test' });
  platform.screens.sync({ screens, state });
  platform.navigationView.sync({ sidebarRoot, bottomRoot, state });
  screens[2].style.zIndex = '7';

  const report = platform.navigationDiagnostics.inspect({ screens, sidebarRoot, bottomRoot, state });
  assert.strictEqual(report.state.screenId, 'vehicles');
  assert.strictEqual(report.transition.source, 'diagnostic-test');
  assert.strictEqual(report.executor.screen, 'vehicles');
  assert.strictEqual(report.screens.total, 3);
  assert.strictEqual(report.screens.visibleCount, 1);
  assert.strictEqual(report.screens.visibleScreens.join(','), 'vehicles');
  assert.strictEqual(report.screens.activeScreens.join(','), 'vehicles');
  assert.strictEqual(report.screens.highestZIndexScreen, 'vehicles');
  assert.strictEqual(report.screens.highestZIndex, 7);
  assert.strictEqual(report.sidebar.active, 1);
  assert.strictEqual(report.sidebar.activeScreens.join(','), 'vehicles');
  assert.strictEqual(report.bottomNavigation.activeScreens.join(','), 'vehicles');

  const guarded = platform.navigationGuard.validate({ screens, sidebarRoot, bottomRoot, state });
  assert.strictEqual(guarded.ok, true);
  assert.strictEqual(guarded.expectedScreen, 'vehicles');
  assert.strictEqual(platform.navigationGuard.assert({ screens, sidebarRoot, bottomRoot, state }).ok, true);

  screens[0].style.display = 'block';
  screens[0].attributes['aria-hidden'] = 'false';
  const failed = platform.navigationGuard.validate({ screens, sidebarRoot, bottomRoot, state });
  assert.strictEqual(failed.ok, false);
  assert.strictEqual(failed.issues.some((item) => item.code === 'visible-screen-count'), true);
  assert.throws(() => platform.navigationGuard.assert({ screens, sidebarRoot, bottomRoot, state }), /Navigation guard failed/);
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
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
  const state = platform.appState.set({ route: '/households/42/edit', width: 390 });
  const result = platform.shellView.render({ screens, sidebarRoot, bottomRoot, breadcrumbRoot, state });
  assert.strictEqual(result.state.moduleKey, 'households');
  assert.strictEqual(result.screen.shown, 'households');
  assert.strictEqual(result.navigation.sidebar.active, 1);
  assert.strictEqual(result.navigation.bottomNavigation.active, 1);
  assert.strictEqual(screens.filter((node) => node.style.display === 'block').length, 1);
  assert.strictEqual(sidebarRoot.nodes[0].attributes['aria-current'], 'page');
  assert.strictEqual(bottomRoot.nodes[0].attributes['aria-current'], 'page');
  assert.strictEqual(breadcrumbRoot.children.map((child) => child.textContent).join('>'), 'Dashboard>Quản lý dân cư>Hộ gia đình>Chinh sua');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  const sidebarRoot = navRoot(['households', 'persons', 'vehicles'], 'screen');
  const bottomRoot = navRoot(['households', 'persons', 'vehicles'], 'mobileScreen');
  const binding = platform.shellView.bind({ screens, sidebarRoot, bottomRoot, moduleKey: 'vehicles' });
  assert.strictEqual(platform.appState.subscriberCount(), 1);
  assert.strictEqual(binding.current().state.moduleKey, 'dashboard');

  platform.appState.set({ route: '/persons', width: 1280 });
  assert.strictEqual(binding.current().state.moduleKey, 'dashboard');
  assert.strictEqual(screens.filter((node) => node.style.display === 'block').length, 0);

  platform.appState.set({ route: '/vehicles/7', width: 1280 });
  assert.strictEqual(binding.current().state.moduleKey, 'vehicles');
  assert.strictEqual(binding.current().screen.shown, 'vehicles');
  assert.strictEqual(sidebarRoot.nodes[2].attributes['aria-current'], 'page');
  assert.strictEqual(bottomRoot.nodes[2].attributes['aria-current'], 'page');
  assert.strictEqual(screens.filter((node) => node.style.display === 'block').length, 1);

  assert.strictEqual(binding.destroy(), true);
  assert.strictEqual(binding.destroy(), false);
  assert.strictEqual(platform.appState.subscriberCount(), 0);
  platform.appState.set({ route: '/households', width: 390 });
  assert.strictEqual(binding.current().state.moduleKey, 'vehicles');
  assert.strictEqual(screens[2].style.display, 'block');
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
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
  const screenRoot = {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-screen-id], .screen');
      return screens;
    }
  };
  const domDocument = {
    querySelector(selector) {
      return {
        '.gov-nav': sidebarRoot,
        '.mobile-bottom-nav': bottomRoot,
        '[data-platform-breadcrumb]': breadcrumbRoot,
        '[data-platform-screen-root]': screenRoot
      }[selector] || null;
    },
    querySelectorAll(selector) {
      if (selector === '[data-screen-id], .screen') return screens;
      return [];
    }
  };

  assert.strictEqual(platform.domRoots.resolve('sidebar', { document: domDocument }), sidebarRoot);
  assert.strictEqual(platform.domRoots.resolve('bottomNavigation', { document: domDocument }), bottomRoot);
  assert.strictEqual(platform.domRoots.resolve('breadcrumb', { document: domDocument }), breadcrumbRoot);
  assert.strictEqual(platform.domRoots.screens({ document: domDocument }).length, 3);
  assert.strictEqual(platform.domRoots.navigationRoots({ document: domDocument }).length, 2);
  assert.strictEqual(platform.domRoots.shellOptions({ document: domDocument }).screens[1].dataset.screenId, 'persons');
  platform.domRoots.configure({ sidebar: '[data-main-nav]' });
  assert.strictEqual(platform.domRoots.selectorFor('sidebar'), '[data-main-nav]');
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  const sidebarRoot = navRoot(['households', 'persons', 'vehicles'], 'screen');
  const bottomRoot = navRoot(['households', 'persons', 'vehicles'], 'mobileScreen');
  sidebarRoot.dataset = { platformMenu: 'true' };
  bottomRoot.dataset = { platformMenu: 'true' };
  const screenRoot = {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-screen-id], .screen');
      return screens;
    }
  };
  const domDocument = {
    querySelector(selector) {
      return {
        '.gov-nav': sidebarRoot,
        '.mobile-bottom-nav': bottomRoot,
        '[data-platform-screen-root]': screenRoot
      }[selector] || null;
    }
  };

  const ready = platform.navigationReadiness.inspect({ document: domDocument });
  assert.strictEqual(ready.ready, true);
  assert.strictEqual(ready.controllerAvailable, true);
  assert.strictEqual(ready.appAvailable, true);
  assert.strictEqual(ready.roots.sidebar.present, true);
  assert.strictEqual(ready.menuRendered.bottomNavigation, true);
  assert.strictEqual(ready.screenCount, 3);
  assert.strictEqual(platform.navigationReadiness.ready({ document: domDocument }), true);

  delete sidebarRoot.dataset.platformMenu;
  const blocked = platform.navigationReadiness.inspect({ document: domDocument });
  assert.strictEqual(blocked.ready, false);
  assert.strictEqual(blocked.issues.some((item) => item.code === 'sidebar-menu-not-rendered'), true);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  screens[0].className = 'screen active';
  screens[0].style.zIndex = '10';
  screens[1].style.display = 'none';
  screens[1].attributes['aria-hidden'] = 'true';
  screens[2].style.display = 'none';
  screens[2].attributes['aria-hidden'] = 'true';
  const sidebarRoot = navRoot(['households', 'persons', 'vehicles'], 'screen');
  const bottomRoot = navRoot(['households', 'persons', 'vehicles'], 'mobileScreen');
  sidebarRoot.dataset = { platformMenu: 'true' };
  bottomRoot.dataset = { platformMenu: 'true' };
  sidebarRoot.nodes[0].className = 'nav-link active';
  sidebarRoot.nodes[0].attributes['aria-current'] = 'page';
  bottomRoot.nodes[0].className = 'nav-link active';
  bottomRoot.nodes[0].attributes['aria-current'] = 'page';
  const screenRoot = {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-screen-id], .screen');
      return screens;
    }
  };
  const domDocument = {
    querySelector(selector) {
      return {
        '.gov-nav': sidebarRoot,
        '.mobile-bottom-nav': bottomRoot,
        '[data-platform-screen-root]': screenRoot
      }[selector] || null;
    }
  };
  platform.appState.set({ route: '/households', moduleKey: 'households', screenId: 'households', action: 'list' });

  const navigationScope = ['households', 'persons', 'vehicles'];
  const plan = platform.navigationRuntimePlan.plan({ document: domDocument, navigationScope });
  assert.strictEqual(plan.canStart, true);
  assert.strictEqual(plan.readiness.ready, true);
  assert.strictEqual(plan.mapping.ok, true);
  assert.strictEqual(plan.domCoverage.ok, true);
  assert.strictEqual(plan.routeCoverage.ok, true);
  assert.strictEqual(plan.scope.ok, true);
  assert.strictEqual(plan.scope.moduleKeys.join(','), navigationScope.join(','));
  assert.strictEqual(plan.guard.ok, true);
  assert.strictEqual(plan.roots.navigation.length, 2);
  assert.strictEqual(plan.roots.navigation[0].itemCount, 3);
  assert.strictEqual(plan.bindings.shell, true);
  assert.strictEqual(plan.bindings.navigation, true);
  assert.strictEqual(plan.bindings.history, false);
  assert.strictEqual(platform.navigationRuntimePlan.canStart({ document: domDocument, navigationScope }), true);

  const domBlocked = platform.navigationRuntimePlan.plan({
    document: domDocument,
    navigationScope: ['households', 'livestock']
  });
  assert.strictEqual(domBlocked.canStart, false);
  assert.strictEqual(domBlocked.domCoverage.ok, false);
  assert.strictEqual(domBlocked.issues.some((item) => item.code === 'dom-coverage-screen-dom-missing'), true);

  platform.menus.upsert({ key: 'broken', label: 'Broken', items: ['missingModule'] });
  const mappingBlocked = platform.navigationRuntimePlan.plan({ document: domDocument, navigationScope });
  assert.strictEqual(mappingBlocked.canStart, false);
  assert.strictEqual(mappingBlocked.mapping.ok, false);
  assert.strictEqual(mappingBlocked.issues.some((item) => item.code === 'mapping-menu-module-missing'), true);

  delete bottomRoot.dataset.platformMenu;
  const blocked = platform.navigationRuntimePlan.plan({ document: domDocument, navigationScope });
  assert.strictEqual(blocked.canStart, false);
  assert.strictEqual(blocked.guard, null);
  assert.strictEqual(blocked.issues.some((item) => item.code === 'bottom-navigation-menu-not-rendered'), true);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  screens[0].className = 'screen active';
  screens[0].style.zIndex = '10';
  screens[1].style.display = 'none';
  screens[1].attributes['aria-hidden'] = 'true';
  screens[2].style.display = 'none';
  screens[2].attributes['aria-hidden'] = 'true';
  const sidebarRoot = navRoot(['households', 'persons', 'vehicles'], 'screen');
  const bottomRoot = navRoot(['households', 'persons', 'vehicles'], 'mobileScreen');
  sidebarRoot.dataset = { platformMenu: 'true' };
  bottomRoot.dataset = { platformMenu: 'true' };
  sidebarRoot.nodes[0].className = 'nav-link active';
  sidebarRoot.nodes[0].attributes['aria-current'] = 'page';
  bottomRoot.nodes[0].className = 'nav-link active';
  bottomRoot.nodes[0].attributes['aria-current'] = 'page';
  sidebarRoot.listeners = {};
  sidebarRoot.addEventListener = function addEventListener(name, handler) {
    this.listeners[name] = handler;
  };
  sidebarRoot.removeEventListener = function removeEventListener(name, handler) {
    if (this.listeners[name] === handler) delete this.listeners[name];
  };
  bottomRoot.listeners = {};
  bottomRoot.addEventListener = function addEventListener(name, handler) {
    this.listeners[name] = handler;
  };
  bottomRoot.removeEventListener = function removeEventListener(name, handler) {
    if (this.listeners[name] === handler) delete this.listeners[name];
  };
  const screenRoot = {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-screen-id], .screen');
      return screens;
    }
  };
  const domDocument = {
    querySelector(selector) {
      return {
        '.gov-nav': sidebarRoot,
        '.mobile-bottom-nav': bottomRoot,
        '[data-platform-screen-root]': screenRoot
      }[selector] || null;
    }
  };
  platform.appState.set({ route: '/households', moduleKey: 'households', screenId: 'households', action: 'list' });
  const navigationScope = ['households', 'persons', 'vehicles'];

  const prepared = platform.navigationActivation.prepare({ document: domDocument, navigationScope });
  assert.strictEqual(prepared.canStart, true);
  assert.strictEqual(prepared.scope.moduleKeys.join(','), navigationScope.join(','));
  assert.strictEqual(platform.navigationActivation.currentPlan(), prepared);
  assert.strictEqual(platform.navigationRuntime.active(), false);

  const activated = platform.navigationActivation.activate({ document: domDocument, navigationScope });
  assert.strictEqual(activated.started, true);
  assert.strictEqual(activated.active, true);
  assert.strictEqual(activated.reason, null);
  assert.strictEqual(platform.navigationRuntime.inspect().delegatedBindings, 2);

  const repeated = platform.navigationActivation.activate({ document: domDocument, navigationScope });
  assert.strictEqual(repeated.started, false);
  assert.strictEqual(repeated.active, true);
  assert.strictEqual(repeated.reason, 'already-active');
  assert.strictEqual(platform.navigationActivation.deactivate(), true);
  assert.strictEqual(platform.navigationRuntime.active(), false);

  delete bottomRoot.dataset.platformMenu;
  const blocked = platform.navigationActivation.activate({ document: domDocument, navigationScope });
  assert.strictEqual(blocked.started, false);
  assert.strictEqual(blocked.active, false);
  assert.strictEqual(blocked.reason, 'plan-blocked');
  assert.strictEqual(platform.navigationRuntime.active(), false);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  screens[0].className = 'screen active';
  screens[0].style.zIndex = '10';
  screens[1].style.display = 'none';
  screens[1].attributes['aria-hidden'] = 'true';
  screens[2].style.display = 'none';
  screens[2].attributes['aria-hidden'] = 'true';
  const sidebarRoot = navRoot(['households', 'persons', 'vehicles'], 'screen');
  const bottomRoot = navRoot(['households', 'persons', 'vehicles'], 'mobileScreen');
  sidebarRoot.dataset = { platformMenu: 'true' };
  bottomRoot.dataset = { platformMenu: 'true' };
  sidebarRoot.nodes[0].className = 'nav-link active';
  sidebarRoot.nodes[0].attributes['aria-current'] = 'page';
  bottomRoot.nodes[0].className = 'nav-link active';
  bottomRoot.nodes[0].attributes['aria-current'] = 'page';
  sidebarRoot.listeners = {};
  sidebarRoot.addEventListener = function addEventListener(name, handler) {
    this.listeners[name] = handler;
  };
  sidebarRoot.removeEventListener = function removeEventListener(name, handler) {
    if (this.listeners[name] === handler) delete this.listeners[name];
  };
  bottomRoot.listeners = {};
  bottomRoot.addEventListener = function addEventListener(name, handler) {
    this.listeners[name] = handler;
  };
  bottomRoot.removeEventListener = function removeEventListener(name, handler) {
    if (this.listeners[name] === handler) delete this.listeners[name];
  };
  const screenRoot = {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-screen-id], .screen');
      return screens;
    }
  };
  const domDocument = {
    querySelector(selector) {
      return {
        '.gov-nav': sidebarRoot,
        '.mobile-bottom-nav': bottomRoot,
        '[data-platform-screen-root]': screenRoot
      }[selector] || null;
    }
  };
  platform.appState.set({ route: '/households', moduleKey: 'households', screenId: 'households', action: 'list' });
  const navigationScope = ['households', 'persons', 'vehicles'];

  const initial = platform.navigationRollout.inspect({ document: domDocument, navigationScope });
  assert.strictEqual(initial.ready, true);
  assert.strictEqual(initial.canActivate, true);
  assert.strictEqual(initial.active, false);
  assert.strictEqual(initial.issueCount, 0);
  assert.strictEqual(initial.prepared, false);
  assert.strictEqual(initial.plan.scope.moduleKeys.join(','), navigationScope.join(','));
  assert.strictEqual(platform.navigationRollout.ready({ document: domDocument, navigationScope }), true);
  assert.strictEqual(platform.navigationRollout.canActivate({ document: domDocument, navigationScope }), true);
  assert.strictEqual(platform.navigationRollout.assertReady({ document: domDocument, navigationScope }).ready, true);

  platform.navigationActivation.prepare({ document: domDocument, navigationScope });
  const prepared = platform.navigationRollout.inspect({ document: domDocument, navigationScope });
  assert.strictEqual(prepared.prepared, true);
  assert.strictEqual(prepared.lastPlan.canStart, true);
  assert.strictEqual(prepared.lastPlan.scope.moduleKeys.join(','), navigationScope.join(','));

  platform.navigationActivation.activate({ document: domDocument, navigationScope });
  const active = platform.navigationRollout.inspect({ document: domDocument, navigationScope });
  assert.strictEqual(active.active, true);
  assert.strictEqual(active.canActivate, false);
  assert.strictEqual(active.runtime.delegatedBindings, 2);
  assert.strictEqual(platform.navigationActivation.deactivate(), true);

  delete sidebarRoot.dataset.platformMenu;
  const blocked = platform.navigationRollout.inspect({ document: domDocument, navigationScope });
  assert.strictEqual(blocked.ready, false);
  assert.strictEqual(blocked.blocked, true);
  assert.strictEqual(blocked.issues.some((item) => item.code === 'sidebar-menu-not-rendered'), true);
  assert.throws(() => platform.navigationRollout.assertReady({ document: domDocument, navigationScope }), /Navigation rollout blocked/);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  const sidebarRoot = navRoot(['households', 'persons', 'vehicles'], 'screen');
  const bottomRoot = navRoot(['households', 'persons', 'vehicles'], 'mobileScreen');
  sidebarRoot.listeners = {};
  sidebarRoot.addEventListener = function addEventListener(name, handler) {
    this.listeners[name] = handler;
  };
  sidebarRoot.removeEventListener = function removeEventListener(name, handler) {
    if (this.listeners[name] === handler) delete this.listeners[name];
  };
  bottomRoot.listeners = {};
  bottomRoot.addEventListener = function addEventListener(name, handler) {
    this.listeners[name] = handler;
  };
  bottomRoot.removeEventListener = function removeEventListener(name, handler) {
    if (this.listeners[name] === handler) delete this.listeners[name];
  };

  assert.strictEqual(platform.navigationRuntime.start({ screens, sidebarRoot, bottomRoot }), true);
  assert.strictEqual(platform.navigationRuntime.start({ screens, sidebarRoot, bottomRoot }), false);
  assert.strictEqual(platform.navigationRuntime.inspect().delegatedBindings, 2);
  assert.strictEqual(platform.appState.subscriberCount(), 1);

  sidebarRoot.listeners.click({ target: sidebarRoot.nodes[1], preventDefault() {} });
  assert.strictEqual(platform.navigation.current().screenId, 'persons');
  assert.strictEqual(screens[1].style.display, 'block');
  assert.strictEqual(sidebarRoot.nodes[1].attributes['aria-current'], 'page');
  assert.strictEqual(bottomRoot.nodes[1].attributes['aria-current'], 'page');
  assert.strictEqual(sandbox.window.Thon09NavigationController.calls[0].screen, 'persons');

  assert.strictEqual(platform.navigationRuntime.stop(), true);
  assert.strictEqual(platform.navigationRuntime.stop(), false);
  assert.strictEqual(platform.navigationRuntime.active(), false);
  assert.strictEqual(platform.navigationRuntime.inspect().delegatedBindings, 0);
  assert.strictEqual(platform.appState.subscriberCount(), 0);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const screens = [screenNode('households'), screenNode('persons'), screenNode('vehicles')];
  const sidebarRoot = navRoot(['households', 'persons', 'vehicles'], 'screen');
  const bottomRoot = navRoot(['households', 'persons', 'vehicles'], 'mobileScreen');
  const screenRoot = {
    querySelectorAll(selector) {
      assert.strictEqual(selector, '[data-screen-id], .screen');
      return screens;
    }
  };
  sidebarRoot.listeners = {};
  sidebarRoot.addEventListener = function addEventListener(name, handler) {
    this.listeners[name] = handler;
  };
  sidebarRoot.removeEventListener = function removeEventListener(name, handler) {
    if (this.listeners[name] === handler) delete this.listeners[name];
  };
  bottomRoot.listeners = {};
  bottomRoot.addEventListener = function addEventListener(name, handler) {
    this.listeners[name] = handler;
  };
  bottomRoot.removeEventListener = function removeEventListener(name, handler) {
    if (this.listeners[name] === handler) delete this.listeners[name];
  };
  const domDocument = {
    querySelector(selector) {
      return {
        '.gov-nav': sidebarRoot,
        '.mobile-bottom-nav': bottomRoot,
        '[data-platform-screen-root]': screenRoot
      }[selector] || null;
    }
  };

  assert.strictEqual(platform.navigationRuntime.start({ document: domDocument }), true);
  assert.strictEqual(platform.navigationRuntime.inspect().delegatedBindings, 2);
  sidebarRoot.listeners.click({ target: sidebarRoot.nodes[2], preventDefault() {} });
  assert.strictEqual(platform.navigation.current().screenId, 'vehicles');
  assert.strictEqual(screens[2].style.display, 'block');
  assert.strictEqual(bottomRoot.nodes[2].attributes['aria-current'], 'page');
  assert.strictEqual(platform.navigationRuntime.stop(), true);
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
  const platform = loadPlatform().window.Thon09Platform;
  platform.permissions.set('vehicles', platform.ACTION.DELETE, false);
  platform.permissions.set('vehicles', platform.ACTION.EDIT, true);
  const denied = platform.permissionView.state('vehicle', 'delete');
  assert.strictEqual(denied.moduleKey, 'vehicles');
  assert.strictEqual(denied.action, platform.ACTION.DELETE);
  assert.strictEqual(denied.allowed, false);
  assert.strictEqual(platform.permissionView.attrs('vehicles', 'delete').disabled, 'disabled');

  const button = platform.permissionView.button({
    moduleKey: 'vehicles',
    permissionAction: 'delete',
    action: 'vehicles.delete',
    label: 'Xoa'
  });
  assert.strictEqual(button.attributes.disabled, 'disabled');
  assert.strictEqual(button.dataset.permissionAllowed, 'false');
  assert.strictEqual(button.dataset.platformAction, 'vehicles.delete');

  const node = {
    dataset: {},
    attributes: {},
    setAttribute(name, value) {
      this.attributes[name] = value;
    },
    removeAttribute(name) {
      delete this.attributes[name];
    }
  };
  const appliedDenied = platform.permissionView.apply(node, 'vehicle', 'delete');
  assert.strictEqual(appliedDenied.allowed, false);
  assert.strictEqual(node.dataset.permissionModule, 'vehicles');
  assert.strictEqual(node.dataset.permissionAction, platform.ACTION.DELETE);
  assert.strictEqual(node.dataset.permissionAllowed, 'false');
  assert.strictEqual(node.attributes.hidden, 'hidden');
  assert.strictEqual(node.attributes.disabled, 'disabled');
  assert.strictEqual(node.attributes['aria-disabled'], 'true');

  platform.permissions.set('vehicles', platform.ACTION.DELETE, true);
  const appliedAllowed = platform.permissionView.apply(node, 'vehicles', 'delete');
  assert.strictEqual(appliedAllowed.allowed, true);
  assert.strictEqual(node.dataset.permissionAllowed, 'true');
  assert.strictEqual(Object.prototype.hasOwnProperty.call(node.attributes, 'hidden'), false);
  assert.strictEqual(Object.prototype.hasOwnProperty.call(node.attributes, 'disabled'), false);
  assert.strictEqual(Object.prototype.hasOwnProperty.call(node.attributes, 'aria-disabled'), false);

  const allowed = platform.permissionView.filterActions('vehicles', ['edit', 'delete']);
  assert.deepStrictEqual(allowed, ['edit', 'delete']);
}

{
  const sandbox = loadPlatform();
  const platform = sandbox.window.Thon09Platform;
  const stateChanges = [];
  const unsubscribe = platform.state.subscribe((record) => {
    stateChanges.push(record.moduleKey + ':' + record.status);
  }, { moduleKey: 'households' });
  assert.strictEqual(platform.state.subscriberCount(), 1);
  const loading = platform.state.loading('households', { source: 'test' });
  assert.strictEqual(loading.status, platform.STATE.LOADING);
  assert.strictEqual(platform.state.loaded('households', [{ id: 1 }]).status, platform.STATE.LOADED);
  assert.strictEqual(platform.state.get('households').data[0].id, 1);
  assert.strictEqual(platform.state.empty('persons').status, platform.STATE.EMPTY);
  assert.strictEqual(platform.state.error('reports', 'Network').error, 'Network');
  assert.strictEqual(platform.state.statusFor('missing'), platform.STATE.EMPTY);
  assert.strictEqual(platform.state.statusFor('households'), platform.STATE.LOADED);
  assert.strictEqual(platform.state.is('reports', platform.STATE.ERROR), true);
  assert.strictEqual(platform.state.summary().Loaded, 1);
  assert.strictEqual(platform.state.summary().Error, 1);
  assert.ok(platform.state.list().length >= 3);
  assert.ok(sandbox.listeners.some((event) => event.type === 'thon09:module-state-change'));
  assert.deepStrictEqual(stateChanges, ['households:Loading', 'households:Loaded']);
  assert.strictEqual(unsubscribe(), true);
  assert.strictEqual(unsubscribe(), false);
  assert.strictEqual(platform.state.subscriberCount(), 0);
  platform.state.loading('households');
  assert.deepStrictEqual(stateChanges, ['households:Loading', 'households:Loaded']);
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
  const context = platform.actions.contextFor(target);
  assert.strictEqual(context.key, 'households.create');
  assert.strictEqual(context.target, target);
  assert.strictEqual(context.dataset.id, '9');

  let prevented = false;
  assert.strictEqual(platform.actions.handleClick({
    target,
    preventDefault() {
      prevented = true;
    }
  }), 'created');
  assert.strictEqual(prevented, true);
  assert.strictEqual(calls[1].dataset.id, '9');

  const root = {
    listeners: {},
    addEventListener(name, handler) {
      this.listeners[name] = handler;
    },
    removeEventListener(name, handler) {
      if (this.listeners[name] === handler) delete this.listeners[name];
    }
  };
  assert.strictEqual(platform.actions.boundCount(), 0);
  assert.strictEqual(platform.actions.bind(root), true);
  assert.strictEqual(platform.actions.bind(root), false);
  assert.strictEqual(platform.actions.isBound(root), true);
  assert.strictEqual(platform.actions.boundCount(), 1);
  assert.strictEqual(root.listeners.click({ target, preventDefault() {} }), 'created');
  assert.strictEqual(platform.actions.unbind(root), true);
  assert.strictEqual(platform.actions.unbind(root), false);
  assert.strictEqual(platform.actions.isBound(root), false);
  assert.strictEqual(platform.actions.boundCount(), 0);
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

  const status = platform.components.status({ status: 'pending' });
  assert.strictEqual(status.className, 'platform-status badge text-bg-warning');
  assert.strictEqual(status.dataset.statusKey, 'pending');
  assert.strictEqual(status.textContent, 'Cho xu ly');

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

  const form = platform.components.form({
    id: 'household-form',
    formKey: 'householdForm',
    moduleKey: 'households',
    title: 'Ho gia dinh',
    noValidate: true,
    on: { submit() {} },
    actions: [{ label: 'Luu', action: 'households.save', variant: 'success' }]
  }, [platform.components.input({ name: 'code', value: 'H001' })]);
  assert.strictEqual(form.tagName, 'FORM');
  assert.strictEqual(form.className, 'platform-form');
  assert.strictEqual(form.attributes.id, 'household-form');
  assert.strictEqual(form.attributes.method, 'post');
  assert.strictEqual(form.attributes.novalidate, 'novalidate');
  assert.strictEqual(form.dataset.formKey, 'householdForm');
  assert.strictEqual(form.dataset.moduleKey, 'households');
  assert.strictEqual(form.children[0].className, 'platform-form-header');
  assert.strictEqual(form.children[1].attributes.name, 'code');
  assert.strictEqual(form.children[2].children[0].dataset.platformAction, 'households.save');
  assert.strictEqual(typeof form.listeners.submit, 'function');

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
  assert.strictEqual(stateNode.dataset.moduleKey, 'households');
  assert.strictEqual(stateNode.dataset.stateStatus, platform.STATE.LOADING);

  platform.state.error('households', 'Mat ket noi');
  const errorNode = platform.components.moduleState('households');
  assert.strictEqual(errorNode.textContent, 'Mat ket noi');
  assert.strictEqual(errorNode.dataset.stateStatus, platform.STATE.ERROR);

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
  platform.forms.register({
    key: 'personForm',
    moduleKey: 'persons',
    sections: {
      basic: [{ key: 'fullName', name: 'fullName', label: 'Ho ten', defaultValue: '' }],
      linked: [{ key: 'householdId', name: 'householdId', label: 'Ho gia dinh', defaultValue: '' }],
      extended: [{ key: 'gender', name: 'gender', label: 'Gioi tinh', type: 'select', options: [{ value: 'M', label: 'Nam' }] }],
      attachments: [{ key: 'files', name: 'files', label: 'Ho so', type: 'upload' }]
    },
    actions: [{ key: 'save', label: 'Luu', icon: 'fa-save' }]
  });
  const field = platform.formView.field({ key: 'fullName', name: 'fullName', label: 'Ho ten' }, { fullName: 'Nguyen Van A' });
  assert.strictEqual(field.className, 'platform-form-field');
  assert.strictEqual(field.dataset.fieldKey, 'fullName');
  assert.strictEqual(field.children[1].attributes.value, 'Nguyen Van A');

  const section = platform.formView.section('personForm', 'basic', { fullName: 'Tran B' });
  assert.strictEqual(section.dataset.sectionKey, 'basic');
  assert.strictEqual(section.children[1].dataset.fieldKey, 'fullName');

  const form = platform.formView.form('personForm', { gender: 'M' }, { sections: ['basic', 'extended'] });
  assert.strictEqual(form.className, 'platform-form');
  assert.strictEqual(form.dataset.formKey, 'personForm');
  assert.strictEqual(form.children.length, 3);
  assert.strictEqual(form.children[1].dataset.sectionKey, 'extended');
  assert.strictEqual(form.children[2].className, 'platform-form-actions');
  assert.strictEqual(form.children[2].children[0].dataset.platformAction, 'persons.save');
  assert.strictEqual(form.children[2].children[0].dataset.formKey, 'personForm');
  assert.strictEqual(form.children[2].children[0].children[0].className, 'fa-solid fa-save');

  const actions = platform.formView.actions('personForm');
  assert.strictEqual(actions.dataset.moduleKey, 'persons');
  assert.strictEqual(actions.children[0].dataset.actionKey, 'save');
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
  platform.lists.register({
    key: 'vehicleList',
    moduleKey: 'vehicles',
    screenId: 'vehicles',
    columns: [
      { key: 'plate', label: 'Bien so' },
      { key: 'owner', label: 'Chu so huu' },
      { key: 'internal', label: 'Noi bo', visible: false }
    ],
    filters: [{ key: 'type', label: 'Loai xe', type: 'select', defaultValue: 'car', options: [{ value: 'car', label: 'O to' }] }],
    pagination: { pageSize: 25 },
    rowActions: [{ key: 'detail', label: 'Chi tiet', icon: 'fa-eye' }],
    bulkActions: ['export'],
    states: { empty: 'Chua co xe' }
  });
  const toolbar = platform.listView.toolbar('vehicleList', { search: '30A', filters: { type: 'car' } });
  assert.strictEqual(toolbar.className, 'platform-list-toolbar');
  assert.strictEqual(toolbar.children[0].attributes.value, '30A');
  assert.strictEqual(toolbar.children[1].dataset.listKey, 'vehicleList');

  const table = platform.listView.table('vehicleList', [{ id: 1, plate: '30A-12345', owner: 'A' }]);
  assert.strictEqual(table.children[0].children[0].children.length, 2);
  assert.strictEqual(table.children[1].children[0].dataset.rowKey, 1);

  const pager = platform.listView.pagination('vehicleList', { page: 2, pageSize: 25, total: 80 });
  assert.strictEqual(pager.children[0].dataset.platformAction, 'vehicles.page');
  assert.strictEqual(pager.children[1].textContent, '2/4');

  const rowActions = platform.listView.actions('vehicleList', 'row', { row: { id: 7 } });
  assert.strictEqual(rowActions.dataset.actionScope, 'row');
  assert.strictEqual(rowActions.children[0].dataset.platformAction, 'vehicles.detail');
  assert.strictEqual(rowActions.children[0].dataset.rowId, 7);
  assert.strictEqual(rowActions.children[0].children[0].className, 'fa-solid fa-eye');

  const bulkActions = platform.listView.actions('vehicleList', 'bulk');
  assert.strictEqual(bulkActions.children[0].dataset.platformAction, 'vehicles.export');
  assert.strictEqual(bulkActions.children[0].dataset.actionScope, 'bulk');

  const list = platform.listView.list('vehicleList', [{ id: 2, plate: '30B-67890', owner: 'B' }], { meta: { total: 1 } });
  assert.strictEqual(list.dataset.moduleKey, 'vehicles');
  assert.strictEqual(list.children[0].className, 'platform-list-toolbar');
  assert.strictEqual(list.children[1].children[1].children.length, 1);
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
  platform.forms.register({
    key: 'vehiclesForm',
    moduleKey: 'vehicles',
    modalKey: 'vehiclesModal',
    sections: {
      basic: [{ key: 'plate', name: 'plate', label: 'Bien so' }]
    },
    actions: [{ key: 'vehicles.save', label: 'Luu' }]
  });
  platform.lists.register({
    key: 'vehiclesList',
    moduleKey: 'vehicles',
    screenId: 'vehicles',
    columns: [{ key: 'plate', label: 'Bien so' }]
  });
  platform.crud.register({
    moduleKey: 'vehicles',
    formKey: 'vehiclesForm',
    listKey: 'vehiclesList'
  });

  const list = platform.crudView.list('vehicles', [{ id: 1, plate: '30A' }], { meta: { total: 1 } }, { header: { title: 'Xe co' } });
  assert.strictEqual(list.dataset.moduleKey, 'vehicles');
  assert.strictEqual(list.dataset.operation, 'list');
  assert.strictEqual(list.children[0].children[0].textContent, 'Xe co');
  assert.strictEqual(list.children[1].dataset.listKey, 'vehiclesList');

  const form = platform.crudView.form('vehicles', 'edit', { plate: '30A' }, { header: { title: 'Sua xe' } });
  assert.strictEqual(form.dataset.operation, 'edit');
  assert.strictEqual(form.children[1].dataset.formKey, 'vehiclesForm');

  const detail = platform.crudView.detail('vehicles', { plate: '30C' }, { header: { title: 'Chi tiet xe' } });
  assert.strictEqual(detail.dataset.operation, 'detail');
  assert.strictEqual(detail.children[0].children[0].textContent, 'Chi tiet xe');
  assert.strictEqual(detail.children[1].dataset.formKey, 'vehiclesForm');

  const modal = platform.crudView.modal('vehicles', 'create', { plate: '30B' }, { title: 'Them xe' });
  assert.strictEqual(modal.dataset.modalKey, 'vehiclesModal');
  assert.strictEqual(modal.children[0].children[0].textContent, 'Them xe');
  assert.strictEqual(platform.crudView.workflow('vehicles').list.moduleKey, 'vehicles');
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
  const platform = loadPlatform().window.Thon09Platform;
  assert.strictEqual(platform.modalLayout.presentation({ width: 1280 }).modal, 'dialog');
  assert.strictEqual(platform.modalLayout.presentation({ width: 390 }).fullscreen, true);
  platform.appState.set({ route: '/households', width: 390 });
  assert.strictEqual(platform.modalLayout.presentation().className, 'modal-fullscreen');

  const dialog = { className: 'modal-dialog existing modal-fullscreen', dataset: {} };
  const modal = {
    className: 'modal',
    querySelector(selector) {
      assert.strictEqual(selector, '.modal-dialog');
      return dialog;
    }
  };
  const applied = platform.modalLayout.apply(modal, { width: 1280 });
  assert.strictEqual(applied.className, 'modal-dialog');
  assert.strictEqual(dialog.className, 'existing modal-dialog');
  assert.strictEqual(dialog.dataset.modalPresentation, 'dialog');
  platform.modalLayout.apply(dialog, { width: 390 });
  assert.strictEqual(dialog.className, 'existing modal-fullscreen');
  assert.strictEqual(dialog.dataset.modalPresentation, 'fullscreen');
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
  const platform = sandbox.window.Thon09Platform;
  platform.forms.register({
    key: 'vehicleForm',
    moduleKey: 'vehicles',
    modalKey: 'vehicleModal',
    title: 'Quan ly xe co',
    sections: {
      basic: [{ key: 'plate', name: 'plate', label: 'Bien so', defaultValue: '' }],
      linked: [{ key: 'owner', name: 'owner', label: 'Chu so huu', defaultValue: '' }],
      extended: [{ key: 'status', name: 'status', label: 'Trang thai', type: 'select', options: [{ value: 'active', label: 'Dang dung' }] }],
      attachments: [{ key: 'files', name: 'files', label: 'Tep dinh kem', type: 'upload' }]
    },
    actions: [{ key: 'vehicles.save', label: 'Luu' }, { key: 'vehicles.cancel', label: 'Huy', variant: 'light' }]
  });
  platform.appState.set({ route: '/vehicles', width: 390 });
  const schema = platform.modals.schema({ formKey: 'vehicleForm' });
  assert.strictEqual(schema.key, 'vehicleModal');
  assert.strictEqual(schema.tabs.map((tab) => tab.key).join(','), 'basic,linked,extended,history,attachments');
  assert.strictEqual(schema.footerActions.length, 2);

  const node = platform.modals.renderStandard({ formKey: 'vehicleForm', subtitle: 'Chi tiet' }, { plate: '30A-12345', status: 'active' });
  assert.strictEqual(node.className, 'platform-modal modal-fullscreen');
  assert.strictEqual(node.dataset.modalKey, 'vehicleModal');
  assert.strictEqual(node.children[0].className, 'platform-modal-header');
  assert.strictEqual(node.children[1].className, 'platform-modal-tabs');
  assert.strictEqual(node.children[2].className, 'platform-modal-footer');
  assert.strictEqual(node.children[2].children[0].dataset.platformAction, 'vehicles.save');
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
