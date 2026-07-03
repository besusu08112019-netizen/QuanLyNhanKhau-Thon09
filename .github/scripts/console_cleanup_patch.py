from pathlib import Path


def read(path: str) -> str:
    return Path(path).read_text(encoding='utf-8')


def write(path: str, text: str) -> None:
    Path(path).write_text(text, encoding='utf-8')


changed = []

# 1) Remove stale dynamic import of deleted mobile-design-system.js.
path = 'assets/js/admin.js'
text = read(path)
original = text
start = text.find("  const loadPopulationCardDesign = () => {")
if start != -1:
    end = text.find("  function installPersonTableRenderFix()", start)
    if end != -1:
        text = text[:start] + text[end:]
text = text.replace('    loadPopulationCardDesign();\n', '')
if text != original:
    write(path, text)
    changed.append(path)

# 2) Harden the shared API wrapper: do not call protected APIs without a token; redirect on 401.
path = 'assets/js/csrf.js'
text = read(path)
original = text
if "const AUTH_REQUIRED_MESSAGE = 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại';" not in text:
    text = text.replace(
        "  App.csrfToken = localStorage.getItem('thon09_csrf') || App.csrfToken || '';\n\n",
        "  App.csrfToken = localStorage.getItem('thon09_csrf') || App.csrfToken || '';\n"
        "  const AUTH_REQUIRED_MESSAGE = 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại';\n\n"
        "  function redirectToLoginOnAuthFailure() {\n"
        "    if (window.__thon09SessionExpired) return;\n"
        "    window.__thon09SessionExpired = true;\n"
        "    if (typeof clearClientSession === 'function') {\n"
        "      clearClientSession();\n"
        "    } else {\n"
        "      App.token = '';\n"
        "      App.user = null;\n"
        "      App.csrfToken = '';\n"
        "      localStorage.removeItem('thon09_token');\n"
        "      localStorage.removeItem('thon09_user');\n"
        "      localStorage.removeItem('thon09_csrf');\n"
        "    }\n"
        "    if (typeof showLogin === 'function') showLogin();\n"
        "  }\n\n"
    )

guard = (
    "      if (!options.public && !App.token) {\n"
    "        redirectToLoginOnAuthFailure();\n"
    "        throw new Error(AUTH_REQUIRED_MESSAGE);\n"
    "      }\n"
)
if guard not in text:
    text = text.replace(
        "      if (options.body && !isFormData) {\n        headers['Content-Type'] = 'application/json';\n      }\n",
        "      if (options.body && !isFormData) {\n        headers['Content-Type'] = 'application/json';\n      }\n" + guard
    )
old_401 = (
    "      if (response.status === 401 && !options.public && !String(url).includes('/api/auth/logout')) {\n"
    "        clearClientSession();\n"
    "        showLogin();\n"
    "      }\n"
    "      if (!response.ok || !payload?.ok) {\n"
    "        throw new Error(payload?.error?.message || 'Không nhận được phản hồi từ hệ thống');\n"
    "      }\n"
)
new_401 = (
    "      if (response.status === 401 && !options.public && !String(url).includes('/api/auth/logout')) {\n"
    "        redirectToLoginOnAuthFailure();\n"
    "        throw new Error(AUTH_REQUIRED_MESSAGE);\n"
    "      }\n"
    "      if (!response.ok || !payload?.ok) {\n"
    "        throw new Error(payload?.error?.message || 'Không nhận được phản hồi từ hệ thống');\n"
    "      }\n"
)
text = text.replace(old_401, new_401)
gis_guard = (
    "    if (!window.App?.token) {\n"
    "      setStatus('Vui lòng đăng nhập lại để tải bản đồ');\n"
    "      return null;\n"
    "    }\n"
)
if gis_guard not in text:
    text = text.replace(
        "  async function loadAreas(options = {}) {\n    if (state.loading) return null;\n",
        "  async function loadAreas(options = {}) {\n    if (state.loading) return null;\n" + gis_guard
    )
if text != original:
    write(path, text)
    changed.append(path)

# 3) Reset the session-expired latch after successful login.
path = 'assets/js/app.js'
text = read(path)
original = text
if "window.__thon09SessionExpired = false;" not in text:
    text = text.replace(
        "    App.user = res.user;\n    localStorage.setItem('thon09_token', App.token);\n",
        "    App.user = res.user;\n    window.__thon09SessionExpired = false;\n    localStorage.setItem('thon09_token', App.token);\n"
    )
if text != original:
    write(path, text)
    changed.append(path)

# 4) Guard GIS household marker requests before login/session expiry.
path = 'assets/js/gis-household-location.js'
text = read(path)
original = text
if "function isAuthenticated()" not in text:
    text = text.replace(
        "  function map() { return window.App && window.App.gis && window.App.gis.map ? window.App.gis.map : null; }\n\n",
        "  function map() { return window.App && window.App.gis && window.App.gis.map ? window.App.gis.map : null; }\n"
        "  function isAuthenticated() { return Boolean(window.App && window.App.token); }\n\n"
    )
if "if (!isAuthenticated()) throw new Error('Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại');" not in text:
    text = text.replace(
        "    const token = localStorage.getItem('thon09_token') || (window.App && window.App.token) || '';\n",
        "    const token = localStorage.getItem('thon09_token') || (window.App && window.App.token) || '';\n"
        "    if (!isAuthenticated()) throw new Error('Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại');\n"
    )
if "  async function loadHouseholdMarkers(search) {\n    if (!isAuthenticated()) return;\n" not in text:
    text = text.replace(
        "  async function loadHouseholdMarkers(search) {\n    const m = map();\n",
        "  async function loadHouseholdMarkers(search) {\n    if (!isAuthenticated()) return;\n    const m = map();\n"
    )
text = text.replace(
    "    setTimeout(() => loadHouseholdMarkers(), 1200);\n",
    "    if (isAuthenticated()) setTimeout(() => loadHouseholdMarkers(), 1200);\n"
)
if text != original:
    write(path, text)
    changed.append(path)

# 5) GIS search must not call API without auth and must include Authorization.
path = 'assets/js/gis-search.js'
text = read(path)
original = text
if "function isAuthenticated()" not in text:
    text = text.replace(
        "  function mapInstance() {\n    return window.App?.gis?.map || window.gisMap || null;\n  }\n\n",
        "  function mapInstance() {\n    return window.App?.gis?.map || window.gisMap || null;\n  }\n\n"
        "  function isAuthenticated() { return Boolean(window.App && window.App.token); }\n\n"
    )
if "headers.Authorization = 'Bearer ' + window.App.token;" not in text:
    text = text.replace(
        "    if (token) headers['X-CSRF-TOKEN'] = token;\n    return headers;\n",
        "    if (token) headers['X-CSRF-TOKEN'] = token;\n    if (window.App && window.App.token) headers.Authorization = 'Bearer ' + window.App.token;\n    return headers;\n"
    )
if "if (!isAuthenticated()) return [];" not in text:
    text = text.replace(
        "  async function search(query) {\n    const url = `${API}?q=${encodeURIComponent(query)}`;\n",
        "  async function search(query) {\n    if (!isAuthenticated()) return [];\n    const url = `${API}?q=${encodeURIComponent(query)}`;\n"
    )
if "if (response.status === 401) {" not in text:
    text = text.replace(
        "    const json = await response.json().catch(() => null);\n    if (!response.ok || !json) {\n",
        "    const json = await response.json().catch(() => null);\n    if (response.status === 401) {\n"
        "      if (typeof window.clearClientSession === 'function') window.clearClientSession();\n"
        "      if (typeof window.showLogin === 'function') window.showLogin();\n"
        "      return [];\n"
        "    }\n    if (!response.ok || !json) {\n"
    )
if text != original:
    write(path, text)
    changed.append(path)

# 6) Favicon route and asset version bump.
path = 'index.php'
text = read(path)
original = text
text = text.replace("define('APP_ASSET_VERSION', '20260703-person-table-admin-4');", "define('APP_ASSET_VERSION', '20260704-console-cleanup-1');")
if "$request->path() === '/favicon.ico'" not in text:
    text = text.replace(
        "$request = Request::capture();\n$router = new Router($request);\n",
        "$request = Request::capture();\n"
        "if ($request->path() === '/favicon.ico') {\n"
        "    header('Content-Type: image/svg+xml; charset=UTF-8');\n"
        "    header('Cache-Control: public, max-age=604800');\n"
        "    echo '<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 64 64\"><rect width=\"64\" height=\"64\" rx=\"14\" fill=\"#0a8f4d\"/><text x=\"32\" y=\"40\" text-anchor=\"middle\" font-size=\"24\" font-family=\"Arial, sans-serif\" font-weight=\"700\" fill=\"#ffffff\">09</text></svg>';\n"
        "    exit;\n"
        "}\n$router = new Router($request);\n"
    )
if text != original:
    write(path, text)
    changed.append(path)

# 7) Add explicit favicon links for browsers that honor document links.
path = 'views/app.php'
text = read(path)
original = text
favicon = (
    '    <link rel="icon" href="/favicon.ico" type="image/svg+xml">\n'
    '    <link rel="shortcut icon" href="/favicon.ico" type="image/svg+xml">\n'
)
if 'rel="icon" href="/favicon.ico"' not in text:
    text = text.replace('    <title>Quản lý Nhân khẩu Thôn 09</title>\n', '    <title>Quản lý Nhân khẩu Thôn 09</title>\n' + favicon)
if text != original:
    write(path, text)
    changed.append(path)

print('Changed files:')
for item in changed:
    print('-', item)
