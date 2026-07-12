# Architecture Refactor Phase 1

Ngay lap tuc dung cach sua loi theo tung diem. Tai lieu nay la baseline cho dot refactor tong the, uu tien bao toan du lieu va giu nguyen nghiep vu hien tai.

## Nguyen tac an toan

- Khong xoa, reset, truncate, seed lai hoac tao lai database.
- Khong xoa file trong `uploads/`, `storage/`, anh, tai lieu dinh kem hoac toa do GIS.
- Khong thay doi business logic, cach tinh thong ke, quyen truy cap hoac ket qua bao cao trong giai doan phan tich.
- Neu can thay doi database o cac giai doan sau, bat buoc dung migration additive, co rollback va co kiem thu du lieu truoc/sau.
- Khong chay cac luong import, restore backup, setup hoac thao tac co kha nang ghi du lieu khi chi kiem thu UI.

## Phase 2 progress

- Da them `assets/js/app-platform.js` lam registry tap trung cho module, menu, route metadata, API response adapter, permission service va modal/navigation facade.
- `views/app.php` khong con hard-code sidebar menu chinh; `.gov-nav` va `.mobile-bottom-nav` duoc render tu `Thon09Platform.menuRenderer`.
- `Thon09Platform.menuRenderer` giu `data-screen/data-mobile-screen` cho controller hien tai va bo sung `data-module/data-route/data-action` cho NavigationIntentService.
- `view-inline-patches.js` van la noi khoi tao `window.Thon09NavigationController`, nhung controller da doc label, loader, dashboard mapping tu platform metadata thay vi hard-code mapping rieng.
- `app.utf8.min.js` khong con tu tao mobile bottom navigation va khong con wrapper `openScreen()`. Quick actions goi truc tiep `window.Thon09NavigationController.navigate(screen)`.
- `sprint10.js` khong con gan click listener len `.sidebar .nav-link`; cac loader bo sung chuyen sang nghe `thon09:screen-change` do NavigationController phat ra.
- Cac file `admin-panel`, `admin-panel-bridge`, `import`, `sprint8`, `sprint9`, `sprint10` khong con monkey-patch `window.showApp`; tat ca lifecycle bo sung chuyen sang `thon09:auth-state` hoac `thon09:screen-change`.
- `systemAdmin` da duoc dua vao Platform module/menu/route registry (`/system/admin`); `system-admin.js` khong con tu chen nut menu vao sidebar.
- `admin-panel` khong con hard-code fallback menu rieng; label header doc tu `Thon09Platform.modules`.
- `import.js` va `admin.utf8.js` khong con tu chen menu item `import/users/logs/backups`; cac file nay chi con tao screen neu screen DOM chua ton tai.
- `Thon09Platform.modals` da co Bootstrap adapter (`registerBootstrap`, `registerBootstrapAll`) va tu dong dang ky cac `.modal[id]` tinh trong DOM.
- `Thon09Platform.modals.attachApp()` da bridge `App.modals.*` legacy vao modal registry chung. Cac module cu van co the goi `App.modals.user.show()`, nhung platform da co the quan sat/mo/dong cung modal qua mot service duy nhat.
- `Thon09Platform.actions` da duoc them lam Action Registry chuan cho lenh UI/CRUD sau nay. Selector moi la `data-platform-action` de khong va cham voi `data-action` hien dang dung cho permission/module cu; service co `contextFor`, `bind`, `unbind`, `isBound`, `boundCount` de delegation duoc kiem soat.
- `Thon09Platform.state` da duoc them lam Module State Store chuan voi bon trang thai `Loading`, `Loaded`, `Empty`, `Error`, helper `statusFor/is/summary`, `subscribe/onChange` va event `thon09:module-state-change`.
- `Thon09Platform.components` da duoc them lam Component Factory nen cho `element`, `button`, `badge`, `status`, `card`, `form`, `input`, `select`, `searchBox`, `filterBar`, `tabs`, `upload`, `stateView`, `moduleState`, `table`, `pagination`; button/form/pagination/tabs moi co the gan truc tiep `data-platform-action`, state view co dataset status/module va hien error tu store.
- `Thon09Platform.api` da co JSON helpers chung cho `get`, `post`, `put`, `patch`, `delete/del` va tiep tuc normalize response ve `{ success, message, data, meta }`.
- `Thon09Platform.apiResources` da duoc them lam ApiResourceService cho CRUD endpoint/module operation contract dua tren Router/Crud metadata, gom `methodFor()` va `inspect()` read-only cho endpoint/method/permission.
- `Thon09Platform.crudData` da duoc them lam CrudDataService cho inspect/list/detail/create/update/delete, dung ApiResourceService va cap nhat Module State Store.
- `Thon09Platform.moduleLoader` da duoc them lam ModuleLoaderService chuan hoa resolve module, inspect loader availability, goi loaderName va cap nhat Loading/Loaded/Error state.
- `Thon09Platform.modals` da co standard modal schema/render contract cho Header, Tabs, Basic, Linked, Extended, History, Attachments, Footer dua tren FormRegistry/ComponentService/ModalLayout.
- `Thon09Platform.permissions` da duoc mo rong voi alias module/action, `setMany`, `loadUser`, `loadMatrix`, `canAll`, `canAny` de chuan bi thay the cac permission check rai rac.
- `Thon09Platform.permissionView` da duoc them lam PermissionViewService cho state/attrs/apply/button/action filtering dua tren PermissionService.
- `Thon09Platform.routes` da co metadata CRUD chuan cho cac module nghiep vu: list, create, detail, edit. Vi du `/persons/create`, `/persons/:id`, `/persons/:id/edit`.
- `Thon09Platform.forms` da duoc them lam FormRegistry chung cho schema sections (`basic`, `linked`, `extended`, `attachments`), fields, actions, modalKey va serialize form DOM.
- `Thon09Platform.formView` da duoc them lam renderer form chung cho field, section, action footer va form node dua tren FormRegistry/ComponentService.
- `Thon09Platform.lists` da duoc them lam ListRegistry chung cho metadata table/list: columns, filters, search, pagination, rowActions, bulkActions va query defaults.
- `Thon09Platform.listView` da duoc them lam renderer list chung cho toolbar, table, pagination, row/bulk actions va list container dua tren ListRegistry/ComponentService.
- `Thon09Platform.crud` da duoc them lam CrudRegistry chung cho workflow list/detail/create/edit/delete/import/export/log, gan route/list/form/action/permission metadata ma chua tu goi API hay thay luong module cu.
- `Thon09Platform.crudView` da duoc them lam renderer CRUD chung cho list/detail/form/modal dua tren CrudRegistry/ListView/FormView/ModalService.
- `Thon09Platform.layout` da duoc them lam LayoutRegistry chung cho desktop/tablet/mobile modes, shared regions, navigation mode va modal presentation.
- `Thon09Platform.breadcrumbs` da duoc them lam BreadcrumbService chung, tao breadcrumb tu route/module/action va co render helper cho `[data-platform-breadcrumb]`.
- `Thon09Platform.appState` da duoc them lam AppStateService chung cho route/module/screen/action/params/layout/breadcrumb snapshot, event `thon09:app-state-change`, va `subscribe/onChange`.
- `Thon09Platform.router` da duoc them lam RouterService chung cho `pathFor`, `resolve`, `route -> module -> screen -> action -> params`, va sync vao AppState; chua thay controller runtime khi chua migrate tung module.
- `Thon09Platform.history` da duoc them lam RouteHistoryService chung cho push/replace/popstate sync vao AppState; service nay khong tu goi NavigationController.
- `Thon09Platform.navigation` da delegate sang RouterService/AppState; co `activate/bindHistory` de history/popstate di qua cung executor; `Thon09NavigationController` chi con la executor doi screen va `window.App` chi la mirror legacy state.
- `Thon09Platform.navigationExecutor` da tach viec goi `Thon09NavigationController.navigate()` va mirror `window.App` ra mot adapter duy nhat co `inspect()`.
- `Thon09Platform.navigationTransitions` da duoc them lam transition log chung cho moi lan dieu huong, gom source, route/module/screen/action, previous state, intent va executor status.
- `Thon09Platform.navigationDiagnostics` da duoc them de doc mot report chung ve AppState, transition, executor, visible screens, active screens, z-index va active sidebar/bottom navigation.
- `Thon09Platform.navigationGuard` da duoc them de validate invariant dieu huong: dung mot screen visible, active screen/menu khop AppState va executor khop target screen.
- `Thon09Platform.navigationReadiness` da duoc them de kiem tra controller, `window.App`, DOM roots, screen count va platform-rendered menu truoc khi bat runtime moi.
- `Thon09Platform.navigationRuntimePlan` da duoc them de tao dry-run plan truoc khi start runtime moi, gom readiness, mapping audit, route coverage, DOM coverage, guard, roots va binding contract.
- `Thon09Platform.navigationActivation` da duoc them lam contract `prepare/activate/deactivate` co gate bang runtime plan; khong auto-start tren production.
- `Thon09Platform.navigationRollout` da duoc them lam status read-only cho rollout runtime moi: ready/blocked/canActivate/active/issues.
- `Thon09Platform.navigationMapping` da duoc them de audit mapping `menu -> module -> route -> screen` truoc khi rollout runtime moi.
- `Thon09Platform.navigationScopes` da duoc them de quan ly scope rollout theo ten, gom `requiredBusinessModules`, `desktopModules`, `tabletModules`, `mobileModules` va `navigationRollout` cho 12 module nghiep vu can kiem thu.
- `Thon09Platform.navigationScopes` da co them `dashboardModules` va `migrationDashboard` de co lap 8 dashboard screen cho module migrate dau tien.
- `Thon09Platform.navigationRouteCoverage` da duoc them de audit route CRUD bat buoc trong tung navigation scope truoc khi rollout runtime moi.
- `Thon09Platform.navigationDomCoverage` da duoc them de audit `module.screenId -> DOM screen` va phat hien screen thieu/trung truoc khi rollout.
- `Thon09Platform.moduleMigration` da duoc them de audit readiness va lap migration plan theo module/scope truoc khi migrate tung module, gom route, DOM screen, loader va CRUD metadata.
- `Thon09Platform.moduleMigration` da co progress runtime `progress/markComplete/resetProgress` de theo doi module tiep theo trong tung scope ma khong ghi database hay localStorage.
- `Thon09Platform.moduleMigration` da co report read-only `report/reports` de gom readiness, progress, next module va issue theo tung stage/scope truoc khi tiep tuc migrate module.
- `Thon09Platform.moduleMigration.handoff()` da co checklist read-only cho module ke tiep hoac module chi dinh, gom registered/routes/DOM/loader/CRUD de khong migrate module khi dieu kien nen chua san sang.
- `Thon09Platform.moduleMigration.advance()` va `completeHandoff()` da them guard runtime-memory de chi danh dau module hoan tat khi handoff san sang va dung thu tu migration, tru khi chu dong cho phep out-of-order.
- `Thon09Platform.moduleMigration.queue()` da co snapshot read-only cho completed/remaining/next/upcoming/blocked/percent theo scope va stage de theo doi tien do migrate tung module.
- `Thon09Platform.moduleMigration.blockers()` da co report read-only gom module bi chan, issue count va ma loi theo scope/stage de triage truoc khi migrate tiep.
- `Thon09Platform.navigationIntent` da duoc them de chuan hoa menu/click target tu `data-module`, `data-screen`, `data-route`, `href` thanh navigation intent truoc khi goi controller.
- `Thon09Platform.navigationDelegation` da duoc them de chuan hoa mot listener click chung: event -> navigation intent -> NavigationService.
- `Thon09Platform.navigationView` da duoc them lam NavigationViewService chung de sync active sidebar, bottom navigation va breadcrumb tu AppState.
- `Thon09Platform.screens` da duoc them lam ScreenViewService chung de hide tat ca screen va chi show screen theo AppState.
- `Thon09Platform.shellView` da duoc them lam AppShellViewService de render/bind screen, sidebar, bottom navigation va breadcrumb tu cung mot AppState snapshot.
- `Thon09Platform.navigationRuntime` da duoc them lam coordinator start/stop cho delegation, history va shell render tu mot noi; chua auto-start tren production.
- `Thon09Platform.domRoots` da duoc them lam DomRootService de gom selector sidebar, bottom navigation, breadcrumb, screen root va screen list vao mot contract chung cho navigation runtime.
- `Thon09Platform.modalLayout` da duoc them lam ModalLayoutService de chuan hoa dialog/fullscreen presentation theo Layout/AppState.
- Da them `tests/navigation-cleanup.test.js` de chan cac pattern dieu huong cu: `window.showApp =`, `hardNavigate`, `window.switchScreen`, `window.showScreen`, `navigationRepairModule`, menu fallback, va menu item tu chen ngoai Platform.
- Cac `document.addEventListener('click')` con lai da phan loai: autocomplete/suggestion close, modal tabs, GPS/photo actions, GIS dirty-state guard va CRUD/module action. Khong co doan nao tu doi active screen ngoai NavigationController.
- `tests/app-platform.test.js` da bao phu route CRUD/menu/API client/permission aliases/state/navigation facade, BreadcrumbService, AppStateService, RouterService, Action Registry, Component Factory, Card/Form/Filter/Tabs/Upload primitives, Table/Pagination primitives, FormRegistry, ListRegistry/ListView actions, CrudRegistry, LayoutRegistry, va modal bridge legacy `App.modals.*`.
- Browser navigation spec xac minh cac modal tinh quan trong (`householdModal`, `personModal`, `businessHouseholdModal`, `detailModal`) da duoc dang ky trong platform modal registry.
- Kiem thu hien tai: `npm run check:js`, `npm run test:platform`, `npm run test:navigation-cleanup`, va `npx playwright test tests/browser/navigation-controller.spec.js --reporter=line` deu PASS o lan chay gan nhat. Playwright xac minh desktop/tablet/mobile dung chung platform menu/controller, click doi noi dung that va chi mot screen hien thi.

## Kien truc hien tai

### Entrypoint

- `index.php` vua la entrypoint web, vua dang ky route API bang PHP.
- `views/app.php` la SPA view lon, chua sidebar, content screens, mobile bottom navigation, modal HTML va danh sach script.
- Frontend assets dang load theo thu tu co dinh tu `views/app.php` va `index.php`, gom `app.utf8.min.js`, `admin-panel*.min.js`, `sprint*.min.js`, `view-inline-patches.min.js`, va cac module rieng nhu `agriculture.min.js`, `houses.min.js`, `livestock.min.js`.

### Navigation

Da co mot controller tap trung trong `assets/js/view-inline-patches.js`:

- `window.Thon09NavigationController.navigate(screen)`
- `hideOtherScreens()`
- `render()`
- `inspect()`
- `getLog()`

Controller nay hien dang la diem dieu huong chinh sau lan sua truoc. Tuy nhien nen xem day la cau noi tam thoi, chua phai kien truc Router hoan chinh vi:

- Frontend van dieu huong bang `screenId`, chua co route chuan nhu `/households/:id`.
- Menu model van nam trong HTML va mot so mapping trong JS.
- State man hinh van ghi truc tiep vao `window.App.screen`.
- Van con cac listener click khac co lien quan den guard/dirty state.

Ket qua quet source khong tinh file minified va vendor:

- `onclick=`: 39 vi tri, chu yeu cho action tren row/form/modal, can thay bang action registry.
- `data-screen`: 26 vi tri, dang la co che gan menu vao screen hien tai.
- `document.addEventListener('click')`: 7 vi tri, can phan loai guard/action/delegation.
- `window.App.screen`: 9 vi tri, can thay bang state store/router state.

### Router

Chua co frontend router theo route URL. Hien tai SPA dua tren:

- `data-screen`
- static DOM section `.screen`
- JS-injected screens
- localStorage `activeScreen`
- global `window.App.screen`

Backend API route table nam tap trung trong `index.php`, nhung co nhieu alias va duplicate families:

- `household-business` va `household-businesses`
- `citizens` va `persons`
- `accounts` va `users`
- `system/logs` va `logs`
- `system/settings` va `settings`
- `system/backups` va `backups`

Cac alias nay khong duoc xoa dot ngot vi co the dang duoc frontend, mobile, production bookmark hoac tich hop ngoai su dung.

Tinh trang hien tai: `Thon09Platform.routes` da khai bao CRUD route metadata cho cac module nghiep vu. Navigation runtime van co the dung `screenId` trong giai doan qua do; viec bat URL/history router se lam sau khi co test module.

### Menu va layout

Menu hien tai duoc encode truc tiep trong `views/app.php`, co nhom chua trung voi thiet ke moi:

- Dashboard
- Tong quan
- Quan ly dan cu
- Bao cao
- Du lieu
- He thong
- Cau hinh

Layout hien tai la mot SPA file lon:

- Desktop: sidebar + content + modal.
- Mobile: bottom navigation + content, nhung data menu va cach render chua hoan toan dung chung voi desktop.
- Tablet/Mobile/Desktop dang chia hanh vi bang CSS va mot so JS rieng.

Tinh trang hien tai: `Thon09Platform.layout` da co contract chung cho mode desktop/tablet/mobile, regions (`sidebar`, `content`, `bottomNavigation`, `modal`), navigation mode va modal presentation. Chua thay CSS/DOM runtime hien tai; giai doan migrate module/layout se dung contract nay de khong duy tri ba he thong giao dien khac nhau.

### Component

Chua co component library tap trung. Cac module lap lai nhieu mau:

- table rendering
- filter/search
- form/modal
- pagination
- badge/status
- empty/loading/error state
- import/export action

Mot so helper nen giu tam thoi trong giai doan migrate:

- `api()`
- `escapeHtml()`
- `renderPager()`
- cac formatter ngay/thang/so lieu

Sau do can dong goi lai thanh component/service chung.

Tinh trang hien tai: `Thon09Platform.components` da co factory nho cho cac primitive an toan, gom card, form controls, filter bar, tabs, upload, table va pagination. `Thon09Platform.forms` da co FormRegistry cho schema/sections/serialize. `Thon09Platform.lists` da co ListRegistry cho columns/filters/search/pagination/actions/query defaults. Chua migrate table/form/pagination cua module cu de tranh thay doi layout va CRUD dong loat.

Tinh trang hien tai cua CRUD: `Thon09Platform.crud` da co workflow metadata list/detail/create/edit/delete/import/export/log va biet noi route, list schema, form schema, action key, permission action. Service nay chua tu goi API, chua submit form va chua thay event handler cu; module migration se dung contract nay theo tung module.

Tinh trang hien tai cua migration: `Thon09Platform.moduleMigration.report()`, `reports()`, `queue()`, `blockers()` va `handoff()` chi doc registry/DOM/runtime memory de lap bao cao readiness theo scope/stage/module. `advance()` va `completeHandoff()` chi cap nhat progress trong memory sau khi checklist san sang; cac API nay khong auto-start navigation runtime, khong goi API, khong ghi localStorage va khong thay doi database.

### Modal/Popup

Modal hien tai bi phan tan trong `views/app.php` va cac file module:

- `householdModal`
- `personModal`
- `businessHouseholdModal`
- `agriDetailModal`
- `agriFormModal`
- `publicAssetDetailModal`
- `publicAssetFormModal`
- `houseDetailModal`
- `houseFormModal`
- `livestockModal`
- `livestockHouseholdModal`
- `detailModal`
- `publicAssetInventoryModal`

Ngoai ra con nhieu global opener nhu `openHouseholdForm`, `openPersonForm`, `openMovementForm`, `openAgriForm`, `openPublicAssetForm`, `openHouseForm`, `openLivestockForm`, `showHousehold`, `showPerson`.

Day la khu vuc can thay bang `ModalService` va `FormRegistry`. Khong nen xoa tung modal khi chua migrate module tuong ung.

Tinh trang hien tai: `Thon09Platform.modals` da la service tap trung cho modal registry va Bootstrap adapter. Cac static modal trong DOM duoc auto-register, va `App.modals.*` legacy duoc bridge vao registry de giam trung lap ma chua can sua tung flow CRUD. `Thon09Platform.forms` da co contract schema/form/tabs nen viec migrate tung module co the lam tuan tu sau.

### State

State hien tai nam rai rac:

- `window.App`
- `window.App.households`
- `window.App.persons`
- `window.App.movements`
- `window.App.users`
- `window.App.logs`
- `window.App.backups`
- `window.App.modals`
- local `state` trong cac module `agriculture`, `livestock`, `houses`, `public-assets`, `system-admin`, `operation-center`

Chua co state machine chuan `Loading`, `Loaded`, `Empty`, `Error`. Nhieu module tu gan `loaded`, `dataset.loaded`, text loading/empty/error rieng.

Tinh trang hien tai: `Thon09Platform.state` da co contract chung cho module state, phat event khi thay doi va co subscriber noi bo co the loc theo module. Cac module cu chua bi ep migrate ngay de tranh thay doi UI/CRUD dong loat.

### API

Backend co `App\Core\Response`:

- `Response::ok($data)` tra ve `ok`, `success`, `data`.
- `Response::error(...)` tra ve `ok`, `success`, `message`, `errors`, `error`.

Nhung mot so controller tra ve response nested hoac custom:

- `$this->ok(['ok' => true, ...])`
- direct `Response::json(...)`
- HTML/PDF/download response
- API wrappers rieng trong module JS

Chuan moi can la `{ success, message, data, meta }`, nhung de khong pha production, giai doan dau phai them adapter frontend va contract tests truoc, chua doi tat ca controller ngay.

Tinh trang hien tai: `Thon09Platform.api` da co adapter response va JSON request helpers cho cac method CRUD chuan. Backend controllers chua bi doi shape response dong loat.

### Permission

Backend da co `requirePermission(module, action)`. Frontend van kiem tra phan tan:

- `window.thon09CanAccess`
- `canAccess`
- `requireUiPermission`
- role checks truc tiep tren `App.user`
- module-specific `can(...)`

Can thay bang `PermissionService` dung action chuan: `View`, `Create`, `Edit`, `Delete`, `Import`, `Export`, `Manage`.

Tinh trang hien tai: `Thon09Platform.permissions` da co contract chung va alias cho cac ten cu nhu `citizen`, `household`, `public_assets`, `update`. Chua thay the `window.thon09CanAccess` dong loat trong runtime de tranh thay doi hanh vi khi chua migrate module.

### GIS

GIS hien dang vua hien thi ban do/marker/polygon/GPS, vua lien ket truc tiep den popup/household. Theo kien truc moi, GIS chi giu nhiem vu ban do va phat event dieu huong toi module tuong ung khi click marker.

### Dashboard

Dashboard hien gom dashboard tong va dashboard tung module. Can giu ket qua thong ke hien tai. Viec chuan hoa dashboard khong duoc thay query, filter hoac cong thuc tinh.

## Phan loai

### Giu lai tam thoi

- Backend controllers, models, route aliases va database schema hien tai.
- Cac API response hien co, bao gom shape cu, cho den khi co adapter va test hop dong.
- `Thon09NavigationController` nhu mot cau noi on dinh cho navigation trong luc xay router moi.
- Static screens va modals hien co cho module chua migrate.
- Helper frontend dang dung rong rai nhu `api`, formatter, pager, escape HTML.

### Thay the

- Menu hard-coded trong `views/app.php` bang `MenuRegistry`.
- Dieu huong bang `screenId` thuan tuy bang `Router` co route chuan va mapping route -> screen -> module.
- Global modal opener bang `ModalService` va `FormRegistry`.
- API wrappers rieng le bang `ApiClient` chung co adapter legacy.
- Permission checks rai rac bang `PermissionService`.
- Table/form/filter/pagination duplicated bang component library.
- State rai rac bang module store co state `Loading`, `Loaded`, `Empty`, `Error`.

### Ma gay xung dot hoac can kiem soat

- Inline `onclick` cho action trong row/form/modal.
- Nhieu `document.addEventListener('click')` khac nhau.
- Nhieu global opener cung mo mot loai entity.
- Direct use `window.App.screen`.
- Duplicate API endpoints va response shape nested.
- Module JS tu render va tu quan ly loaded/error/empty state.

### Rủi ro cao

- Import/restore backup/setup vi co kha nang ghi du lieu lon.
- Uploads, files, photos va toa do GIS.
- Reports/statistics vi yeu cau khong thay doi ket qua.
- Users/permissions/backups/logs vi lien quan quan tri he thong.
- Worktree hien dang co thay doi san, can tach commit refactor khoi cac thay doi khac.

## Nen tang moi de xay o Giai doan 2

Khong migrate module ngay. Truoc tien tao layer nen:

1. `RouteRegistry`
   - Khai bao route chuan: `/households`, `/households/create`, `/households/:id`, `/households/:id/edit`.
   - Mapping route -> moduleKey -> screenId -> action.
   - Da mo rong metadata CRUD cho cac module nghiep vu, chua doi backend route table.

2. `MenuRegistry`
   - Mot data model duy nhat cho Desktop, Tablet, Mobile.
   - Nhom theo nghiep vu: Dashboard, Quan ly dan cu, Quan ly tai san, Quan ly san xuat, Quan ly phuong tien, Quan ly tai chinh, GIS, Bao cao, He thong.

3. `NavigationController`
   - La single source of truth moi.
   - Chi controller nay duoc hide/show screen, set active menu, update bottom nav, breadcrumb va app state.
   - Cac module chi duoc goi `navigate(routeOrScreen, params)`.

4. `ModuleRegistry`
   - Dang ky moduleKey, screenId, component loader, route handlers, permission scope.
   - Cho phep migrate tung module ma khong pha module khac.

5. `ApiClient`
   - Chuan hoa output frontend thanh `{ success, message, data, meta }`.
   - Van chap nhan legacy `{ ok, success, data }` trong giai doan qua do.
   - Cung cap method CRUD chung: `get`, `post`, `put`, `patch`, `delete/del`.

6. `ApiResourceService`
   - Tao endpoint CRUD theo module/action/params/query dua tren RouterService va CrudRegistry.
   - `inspect()` tra ve endpoint, HTTP method, route, permission/action metadata ma khong goi API.
   - Goi qua ApiClient chung de response tiep tuc duoc normalize.
   - Chua doi route backend hoac bat module cu dung service nay khi chua migrate tung module.

7. `CrudDataService`
   - Chuan hoa CRUD data calls `inspect/list/detail/create/update/delete` qua ApiResourceService.
   - `inspect()` tra ve endpoint/method/operation metadata kem state hien tai, khong goi API.
   - Cap nhat Module State Store theo `Loading`, `Loaded`, `Empty`, `Error` quanh moi request.
   - Chua doi endpoint backend hoac bat module cu dung service nay khi chua migrate tung module.

8. `ModuleLoaderService`
   - Resolve module tu moduleKey/screenId/state, inspect loader availability va goi loaderName da dang ky trong ModuleRegistry.
   - Cap nhat Module State Store theo `Loading`, `Loaded`, `Error` quanh moi lan load.
   - `inspect()` chi doc metadata loader/screen/state, khong thuc thi loader; dung de migrate tung module co kiem soat.
   - Chua ep NavigationController production goi service nay cho den khi migrate tung module co test.

9. `PermissionService`
   - Chuan action: `View`, `Create`, `Edit`, `Delete`, `Import`, `Export`, `Manage`.
   - UI chi hoi service, khong doc role truc tiep.
   - Chap nhan alias module/action cu trong qua trinh migrate tung module.

10. `PermissionViewService`
   - Tao state/attrs/apply/button/action filtering dua tren PermissionService chung.
   - Module UI sau nay chi dung service nay de an/disable action theo quyen; `apply()` chuan hoa dataset `permissionModule`, `permissionAction`, `permissionAllowed` tren DOM hien co.
   - Chua thay permission DOM cu cho den khi migrate tung module.

11. `StateService`
   - Moi module chi ghi nhan mot trong bon trang thai `Loading`, `Loaded`, `Empty`, `Error`.
   - State thay doi phat `thon09:module-state-change` de layout/component co the render theo contract chung.
   - Co helper `statusFor`, `is`, `summary`, `subscribe/onChange` de module sau nay khong tu doc local flags rieng hoac tu gan listener rieng.

12. `ModalService`
   - Mot component modal chung: Header, Tabs, Basic, Extended, History, Attachments, Footer.
   - Module dang ky form schema/action, khong tu tao popup rieng.
   - Da co `schema()` va `renderStandard()` lam contract moi; chua ep cac popup cu dung contract nay truoc khi migrate tung module.

13. `FormRegistry`
   - Dang ky form theo module, modalKey, sections, fields va actions.
   - Sections chuan: `basic`, `linked`, `extended`, `attachments`.
   - Cung cap helper serialize form DOM, chua thay the global opener cu khi chua migrate module.

14. `FormViewService`
   - Render field, section, action footer va form node tu FormRegistry bang ComponentService chung.
   - La lop dung chung cho ModalService va cac man hinh detail/create/edit sau nay.
   - Chua thay form DOM cu cho den khi migrate tung module.

15. `ListRegistry`
   - Dang ky list/table theo module, screenId, columns, filters, search, pagination, rowActions va bulkActions.
   - Cung cap query defaults chuan cho page, pageSize, search, sort va filters.
   - Chua thay renderer table cu khi chua migrate tung module.

16. `ListViewService`
   - Render toolbar, table, pagination, row/bulk actions va list container tu ListRegistry bang ComponentService chung.
   - La lop dung chung cho man hinh danh sach sau nay, thay cho table/filter/pagination copy theo module.
   - Chua thay list DOM cu cho den khi migrate tung module.

17. `CrudRegistry`
   - Dang ky workflow CRUD chuan theo module: list, detail, create, edit, delete, import, export, log.
   - Noi metadata route/list/form/action/permission de UI sau nay khong tu hard-code tung module.
   - Chua goi API hay thay flow CRUD cu khi chua migrate tung module.

18. `CrudViewService`
   - Render list/form/modal theo workflow tu CrudRegistry, dung lai ListViewService, FormViewService va ModalService.
   - La contract UI CRUD chung de module sau nay khong tu ghep table/form/modal rieng.
   - Chua goi API hay thay screen/module runtime cu.

19. `LayoutRegistry`
   - Khai bao mot layout model chung cho desktop, tablet va mobile.
   - Quan ly regions dung chung: sidebar, content, bottomNavigation, modal.
   - Chuan hoa navigation mode va modal presentation, chua thay CSS/DOM runtime khi chua migrate layout.

20. `BreadcrumbService`
   - Tao breadcrumb tu route hoac module/action bang Platform metadata.
   - Chuan hoa labels list/detail/create/edit/import/export/log.
   - Co render helper cho vung `[data-platform-breadcrumb]`, chua ep controller runtime doi render khi chua migrate layout.

21. `AppStateService`
   - Luu snapshot route/module/screen/action/params/layout/breadcrumb hien tai.
   - Phat event `thon09:app-state-change` de layout/controller sau nay dong bo tu mot nguon.
   - Co `subscribe/onChange` loc theo module de layout/controller sau nay khong tu gan listener rieng.
   - Chua thay `window.App.screen` hoac controller runtime khi chua migrate navigation controller.

22. `RouterService`
   - Resolve route/module/action ve mot state chuan duy nhat.
   - Tao path tu module/action/params de module sau nay khong hard-code URL.
   - Sync vao AppState, chua tu push browser history hoac goi NavigationController trong giai doan nen.

23. `NavigationService`
   - Delegate resolve/sync state qua RouterService/AppState.
   - Chi goi `NavigationExecutorService.execute(state)` de thuc thi viec doi screen.
   - Co `activate(state)` va `bindHistory(history)` de URL popstate sau nay khong tao luong dieu huong rieng.
   - Khong doc `window.App` nhu source of truth; legacy mirror duoc tach sang NavigationExecutorService.

24. `NavigationExecutorService`
   - La adapter duy nhat goi `Thon09NavigationController.navigate(screen)` trong platform navigation layer.
   - Mirror `window.App.route/moduleKey/screen/action/params` de giu module cu chay trong giai doan qua do.
   - Co `inspect()` de xac minh controller availability, screen vua execute va trang thai mirror legacy.

25. `NavigationTransitionService`
   - Ghi mot record chuan sau moi lan `NavigationService.activate()`.
   - Record gom source, route, moduleKey, screenId, action, params, previous, intent va executor status.
   - Ho tro `current`, `history`, `count`, `clear` de debug click/menu/history ma khong doc nhieu global rieng.

26. `NavigationDiagnosticsService`
   - Tao report chung tu AppState, NavigationTransitionService, NavigationExecutorService va DOM roots.
   - Kiem tra visible screens, active screens, highest z-index screen, active sidebar va active bottom navigation.
   - Chi doc trang thai de phuc vu debug/test; khong hide/show screen va khong gan listener.

27. `NavigationGuardService`
   - Validate invariant dieu huong tu NavigationDiagnosticsService.
   - Khong cho coi PASS neu chi active menu doi ma visible screen khong doi, hoac co nhieu screen dang hien.
   - Tra ve `ok/issues/report` va co `assert()` de test fail nhanh khi migrate module.

28. `NavigationReadinessService`
   - Kiem tra dieu kien an toan truoc khi bat navigation runtime moi.
   - Xac minh controller legacy, `window.App`, sidebar root, bottom navigation root, screen nodes va menu da duoc Platform render.
   - Chi inspect/bao issue; khong tu start runtime va khong sua DOM.

29. `NavigationRuntimePlanService`
   - Lap dry-run plan truoc khi start runtime moi.
   - Tong hop `NavigationReadinessService`, `NavigationMappingService`, `NavigationRouteCoverageService`, `NavigationDomCoverageService`, `NavigationGuardService`, DOM roots va binding options thanh `canStart/issues`.
   - Ho tro `navigationScope` de gate route/DOM coverage theo tung nhom module thay vi ep migrate tat ca cung luc.
   - Khong gan listener, khong render, khong doi screen; chi cung cap contract de gate rollout sau nay.

30. `NavigationActivationService`
   - Cung cap `prepare()`, `activate()` va `deactivate()` cho runtime moi.
   - `activate()` chi goi `NavigationRuntimeService.start()` khi `NavigationRuntimePlanService` tra ve `canStart=true`.
   - Khong auto-start tren production va khong tao fallback; rollout sau nay phai di qua contract nay.

31. `NavigationRolloutService`
   - Tong hop runtime plan, runtime inspect va activation plan gan nhat thanh mot status read-only.
   - Tra ve `ready`, `blocked`, `canActivate`, `active`, `issues`, `runtime` de rollout khong phai doc nhieu global rieng.
   - Co `assertReady()` cho test/gate rollout; khong start runtime va khong doi DOM.

32. `NavigationMappingService`
   - Audit mapping `menuKey -> moduleKey -> route -> screenId` tu Platform registries.
   - Phat hien menu item thieu module, module thieu list route, route tro sai screen/module, route thieu action va module nam ngoai menu.
   - Chi doc metadata va tra `ok/issues`; khong render menu va khong doi screen.

33. `NavigationDomCoverageService`
   - Audit mapping `module.screenId -> DOM screen` theo cung `DomRootService` cua runtime.
   - Phat hien screen DOM thieu, screenId trung lap va DOM node khong co screenId.
   - Chi inspect DOM va tra `ok/issues`; khong hide/show screen va khong gan listener.

34. `NavigationScopeService`
   - Quan ly scope rollout theo ten thay vi truyen tay danh sach module trong tung test/activation.
   - Cac scope mac dinh `requiredBusinessModules`, `desktopModules`, `tabletModules`, `mobileModules`, `navigationRollout` deu tro den 12 module nghiep vu can kiem thu.
   - Scope `dashboardModules`/`migrationDashboard` tro den 8 dashboard screen va chi yeu cau route `list`, tach rieng khoi CRUD scope.
   - `NavigationRuntimePlanService` tu ap `navigationScope` vao DOM coverage gate va bao loi neu scope thieu module; service chi doc metadata, khong start runtime.

35. `NavigationRouteCoverageService`
   - Audit route CRUD bat buoc `list/create/detail/edit` theo `navigationScope`.
   - Bao loi neu module trong scope thieu route action chuan, vi du module map/bao cao khong duoc coi la CRUD scope neu chua co route tuong ung.
   - `NavigationRuntimePlanService` gate route coverage cung DOM coverage khi co scope; service chi doc RouteRegistry/ModuleRegistry, khong goi API.

36. `ModuleMigrationService`
   - Audit readiness theo module hoac navigation scope truoc khi migrate tung module.
   - Stage `navigation` kiem tra route list, DOM screen va loader metadata; stage `crud` kiem tra route CRUD va list/form metadata.
   - `plan()` tra `nextModuleKey`, ready/blocked/completed modules de khoa thu tu migrate va tiep tuc duoc sau moi commit.
   - `progress()`, `markComplete()` va `resetProgress()` chi luu tien do trong runtime memory, phuc vu test/rollout noi bo va khong ghi du lieu he thong.
   - Ket qua hien tai: dashboard co the qua navigation contract khi co DOM screen; CRUD scope cua 12 module nghiep vu con bi chan cho toi khi migrate list/form metadata theo tung module.
   - Service chi doc registry/DOM va khong goi API, khong render, khong ghi du lieu.

37. `NavigationIntentService`
   - Chuan hoa target tu menu item, DOM element hoac click event thanh `{ moduleKey, screenId, route, action, source }`.
   - Ho tro `data-module`, `data-module-key`, `data-screen`, `data-mobile-screen`, `data-route` va `href`.
   - MenuRenderer da xuat metadata `data-module/data-route/data-action` de migrate click menu khong can doan mapping rieng.
   - Chua tu gan listener runtime; giai doan sau se dung service nay de thay the cac selector click phan tan.

38. `NavigationDelegationService`
   - Cung cap `handleClick(event)` va `bind(root)` de mot listener click chung di qua NavigationIntentService roi NavigationService.
   - Tu `preventDefault` khi click co navigation intent va co `unbind` de thao listener ro rang.
   - Chua auto-bind vao `.gov-nav`, `.mobile-bottom-nav` hay document trong production.

39. `NavigationRuntimeService`
   - Gom `NavigationDelegationService`, `NavigationService.bindHistory`, va `AppShellViewService.bind` vao mot `start/stop` contract.
   - Dam bao runtime sau nay co mot noi bat/tat listener, history va render shell.
   - Chua auto-start tren production; controller runtime hien tai van giu nguyen cho den khi migrate co test.

40. `RouteHistoryService`
   - Chuan hoa `pushState`, `replaceState`, va `popstate` de router URL sau nay co mot contract duy nhat.
   - Sync route vao AppState, khong tu goi NavigationController va khong tu bat listener neu chua goi `start`.
   - Popstate co the noi vao NavigationService qua `navigation.bindHistory(history)`, nhung chua auto-start tren production.

41. `NavigationViewService`
   - Cap nhat active sidebar va bottom navigation bang AppState, khong dua vao logic rieng desktop/mobile.
   - Render breadcrumb tu BreadcrumbService bang cung snapshot state.
   - Chua tu dong quet DOM runtime khi chua migrate controller de tranh thay doi hang loat.

42. `ScreenViewService`
   - Hide tat ca screen va show dung screen hien tai tu AppState.
   - Dam bao contract "mot screen hien thi tai mot thoi diem" co test rieng.
   - Chua tu dong thay controller runtime de tranh pha luong cu khi chua migrate module.

43. `AppShellViewService`
   - Gom ScreenViewService va NavigationViewService vao mot render contract duy nhat.
   - Dam bao controller sau nay chi can render tu AppState, khong cap nhat sidebar/bottom/breadcrumb rieng le.
   - Co `bind` de subscribe AppState va render shell tu mot noi, ho tro loc theo module trong qua trinh migrate.
   - Chua auto-run tren DOM production cho den khi migrate NavigationController.

44. `ModalLayoutService`
   - Chuan hoa presentation `dialog` tren desktop/tablet va `fullscreen` tren mobile.
   - Co helper apply class cho `.modal-dialog`, dua theo LayoutRegistry/AppState.
   - Chua tu dong mo/dong modal hay thay flow popup cu.

45. `DomRootService`
   - Tap trung selector shell runtime: sidebar, bottom navigation, breadcrumb, screen root va screen list.
   - Cung cap `resolve`, `screens`, `navigationRoots` va `shellOptions` de runtime khong tu query selector rai rac.
   - Chua auto-start navigation runtime tren production; service chi tao contract DOM chung cho cac phase migrate sau.

46. Component library
   - Table, Card, Form, Input, Select, Button, Badge, Status, Search, Filter, Modal, Tabs, Upload, Pagination.
   - Cac component phai co loading/empty/error state chuan.
   - Nen tang hien co gom `element`, `button`, `badge`, `status`, `card`, `form`, `input`, `select`, `searchBox`, `filterBar`, `tabs`, `upload`, `stateView`, `moduleState`, `table`, `pagination`; cac component phuc tap hon se them khi migrate tung module.
   - `stateView/moduleState` co dataset status/module va hien error message tu StateService.

47. `ActionRegistry`
   - Chuan hoa cac lenh UI bang `Thon09Platform.actions.register(key, handler)`.
   - Markup moi dung `data-platform-action`, khong dung `data-action` vi `data-action` dang co nghia cu trong permission va mot so module.
   - Co contract delegation `contextFor/handleClick/bind/unbind` de thay inline `onclick` theo tung module ma khong tao nhieu listener chong cheo.
   - Giai doan sau se thay inline `onclick` theo tung module, khong thay dong loat khi chua co test module.

## Thu tu migrate module

Chi migrate mot module moi lan:

1. Dashboard
2. Ho gia dinh
3. Nhan khau
4. Tam tru
5. Tam vang
6. Bien dong nhan khau
7. Cong trinh cong cong
8. Ho san xuat va kinh doanh
9. Quan ly vat nuoi
10. Nha o va Cong trinh
11. Quan ly xe co
12. San xuat nong nghiep
13. Dong gop ho
14. GIS
15. Bao cao
16. He thong

## Cong kiem thu sau moi module

- Desktop, Tablet, Mobile dung chung controller.
- Route dung, breadcrumb dung, sidebar active dung, bottom nav active dung.
- Chi mot screen hien thi.
- Khong loi console.
- Khong loi network.
- Modal dung service chung.
- Permission dung service chung.
- API response qua adapter dung `{ success, message, data, meta }`.
- Khong ghi/xoa du lieu ngoai thao tac CRUD duoc test co chu dich.
- Thong ke truoc/sau khong thay doi.
- Uploads/photos/GIS coordinates con nguyen.

## Ket luan Phase 1

Kien truc hien tai da co mot NavigationController tam thoi, nhung he thong van chua dat single source of truth o cap router, modal, API client, permission, state va component. Buoc tiep theo khong nen tiep tuc sua tung module. Can xay platform layer moi song song, them test hop dong, roi migrate tung module theo thu tu da khoa.
