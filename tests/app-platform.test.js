const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'assets/js/app-platform.js'), 'utf8');

function createSandbox() {
  const listeners = [];
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
  return sandbox;
}

function loadPlatform() {
  const sandbox = createSandbox();
  vm.runInNewContext(source, sandbox, { filename: 'app-platform.js' });
  return sandbox;
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
  assert.strictEqual(sandbox.window.Thon09NavigationController.calls[0].screen, 'persons');
  assert.strictEqual(sandbox.window.App.route, '/persons');
  assert.strictEqual(sandbox.window.App.moduleKey, 'persons');
}

{
  const platform = loadPlatform().window.Thon09Platform;
  assert.strictEqual(platform.permissions.can('households', platform.ACTION.VIEW, { role: 'SUPER_ADMIN' }), true);
  platform.permissions.set('households', platform.ACTION.DELETE, false);
  assert.strictEqual(platform.permissions.can('households', platform.ACTION.DELETE, { role: 'SUPER_ADMIN' }), false);
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

  platform.state.loading('households');
  const stateNode = platform.components.moduleState('households');
  assert.strictEqual(stateNode.className, 'platform-state platform-state-loading');
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
