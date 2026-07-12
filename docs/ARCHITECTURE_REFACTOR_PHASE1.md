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
- `view-inline-patches.js` van la noi khoi tao `window.Thon09NavigationController`, nhung controller da doc label, loader, dashboard mapping tu platform metadata thay vi hard-code mapping rieng.
- `app.utf8.min.js` khong con tu tao mobile bottom navigation va khong con wrapper `openScreen()`. Quick actions goi truc tiep `window.Thon09NavigationController.navigate(screen)`.
- `sprint10.js` khong con gan click listener len `.sidebar .nav-link`; cac loader bo sung chuyen sang nghe `thon09:screen-change` do NavigationController phat ra.
- Cac file `admin-panel`, `admin-panel-bridge`, `import`, `sprint8`, `sprint9`, `sprint10` khong con monkey-patch `window.showApp`; tat ca lifecycle bo sung chuyen sang `thon09:auth-state` hoac `thon09:screen-change`.
- `systemAdmin` da duoc dua vao Platform module/menu/route registry (`/system/admin`); `system-admin.js` khong con tu chen nut menu vao sidebar.
- `admin-panel` khong con hard-code fallback menu rieng; label header doc tu `Thon09Platform.modules`.
- `import.js` va `admin.utf8.js` khong con tu chen menu item `import/users/logs/backups`; cac file nay chi con tao screen neu screen DOM chua ton tai.
- `Thon09Platform.modals` da co Bootstrap adapter (`registerBootstrap`, `registerBootstrapAll`) va tu dong dang ky cac `.modal[id]` tinh trong DOM.
- `Thon09Platform.modals.attachApp()` da bridge `App.modals.*` legacy vao modal registry chung. Cac module cu van co the goi `App.modals.user.show()`, nhung platform da co the quan sat/mo/dong cung modal qua mot service duy nhat.
- `Thon09Platform.actions` da duoc them lam Action Registry chuan cho lenh UI/CRUD sau nay. Selector moi la `data-platform-action` de khong va cham voi `data-action` hien dang dung cho permission/module cu.
- `Thon09Platform.state` da duoc them lam Module State Store chuan voi bon trang thai `Loading`, `Loaded`, `Empty`, `Error` va event `thon09:module-state-change`.
- `Thon09Platform.components` da duoc them lam Component Factory nen cho `element`, `button`, `badge`, `stateView`, `moduleState`; button moi co the gan truc tiep `data-platform-action`.
- Da them `tests/navigation-cleanup.test.js` de chan cac pattern dieu huong cu: `window.showApp =`, `hardNavigate`, `window.switchScreen`, `window.showScreen`, `navigationRepairModule`, menu fallback, va menu item tu chen ngoai Platform.
- Cac `document.addEventListener('click')` con lai da phan loai: autocomplete/suggestion close, modal tabs, GPS/photo actions, GIS dirty-state guard va CRUD/module action. Khong co doan nao tu doi active screen ngoai NavigationController.
- `tests/app-platform.test.js` da bao phu route/menu/API/permission/state/navigation facade, Action Registry, Component Factory, va modal bridge legacy `App.modals.*`.
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

Tinh trang hien tai: `Thon09Platform.components` da co factory nho cho cac primitive an toan. Chua migrate table/form/pagination cua module cu de tranh thay doi layout va CRUD dong loat.

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

Tinh trang hien tai: `Thon09Platform.modals` da la service tap trung cho modal registry va Bootstrap adapter. Cac static modal trong DOM duoc auto-register, va `App.modals.*` legacy duoc bridge vao registry de giam trung lap ma chua can sua tung flow CRUD. Viec migrate schema/form/tabs cua tung module van phai lam tuan tu sau khi co FormRegistry.

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

Tinh trang hien tai: `Thon09Platform.state` da co contract chung cho module state va phat event khi thay doi. Cac module cu chua bi ep migrate ngay de tranh thay doi UI/CRUD dong loat.

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

### Permission

Backend da co `requirePermission(module, action)`. Frontend van kiem tra phan tan:

- `window.thon09CanAccess`
- `canAccess`
- `requireUiPermission`
- role checks truc tiep tren `App.user`
- module-specific `can(...)`

Can thay bang `PermissionService` dung action chuan: `View`, `Create`, `Edit`, `Delete`, `Import`, `Export`, `Manage`.

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

6. `PermissionService`
   - Chuan action: `View`, `Create`, `Edit`, `Delete`, `Import`, `Export`, `Manage`.
   - UI chi hoi service, khong doc role truc tiep.

7. `StateService`
   - Moi module chi ghi nhan mot trong bon trang thai `Loading`, `Loaded`, `Empty`, `Error`.
   - State thay doi phat `thon09:module-state-change` de layout/component co the render theo contract chung.

8. `ModalService`
   - Mot component modal chung: Header, Tabs, Basic, Extended, History, Attachments, Footer.
   - Module dang ky form schema/action, khong tu tao popup rieng.

9. Component library
   - Table, Card, Form, Input, Select, Button, Badge, Status, Search, Filter, Modal, Tabs, Upload, Pagination.
   - Cac component phai co loading/empty/error state chuan.
   - Nen tang hien co bat dau tu `element`, `button`, `badge`, `stateView`, `moduleState`; cac component phuc tap hon se them khi migrate tung module.

10. `ActionRegistry`
   - Chuan hoa cac lenh UI bang `Thon09Platform.actions.register(key, handler)`.
   - Markup moi dung `data-platform-action`, khong dung `data-action` vi `data-action` dang co nghia cu trong permission va mot so module.
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
