const { test, expect } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');

const loginConfig = {
  ok: true,
  data: {
    settings: { systemName: 'Test', hamletName: 'Thon 09', communeName: 'Test', slogan: '', version: 'test', copyright: '' },
    metrics: { total_households: 0, total_citizens: 0, party_member_count: 0, male_count: 0, female_count: 0, away_count: 0 }
  }
};

function pngFile(name) {
  const file = path.join(os.tmpdir(), name);
  fs.writeFileSync(file, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', 'base64'));
  return file;
}

async function expectHouseholdModalOpen(page) {
  await expect.poll(() => page.locator('#householdModal').evaluate(modal => {
    const style = getComputedStyle(modal);
    return modal.classList.contains('show')
      && style.display !== 'none'
      && modal.getAttribute('aria-hidden') !== 'true';
  })).toBe(true);
}

test('household photo is uploaded, read back and replaced from library/camera inputs', async ({ page }) => {
  const consoleErrors = [];
  page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
  page.on('pageerror', error => consoleErrors.push(error.message));

  let savedHousehold = null;
  let uploadCount = 0;
  let deleteCount = 0;
  const uploadedFiles = [];
  const previewIds = [];

  await page.route('**/api/**', async route => {
    const request = route.request();
    const url = new URL(request.url());
    const payload = data => route.fulfill({ contentType: 'application/json', body: JSON.stringify({ ok: true, success: true, data }) });

    if (url.pathname === '/api/public/login-config') return payload(loginConfig.data);
    if (url.pathname === '/api/auth/me') return payload({ id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' });
    if (url.pathname === '/api/dashboard/summary') return payload({ metrics: {}, charts: {} });
    if (url.pathname === '/api/persons') return payload({ items: [], total: 0, page: 1, pageSize: 100 });
    if (url.pathname === '/api/profiles/household/123') {
      return payload({ type: 'household', profile: savedHousehold, members: [], files: uploadedFiles.slice().reverse(), logs: [], timeline: [], sections: {} });
    }

    if (url.pathname === '/api/households' && request.method() === 'GET') {
      return payload({ items: savedHousehold ? [savedHousehold] : [], total: savedHousehold ? 1 : 0, page: 1, pageSize: 20 });
    }
    if (url.pathname === '/api/households' && request.method() === 'POST') {
      const body = JSON.parse(request.postData() || '{}');
      savedHousehold = {
        id: 123,
        household_code: body.householdCode,
        head_citizen_name: body.headCitizenName,
        address: body.address,
        phone: body.phone || '',
        at_home_count: 0,
        away_count: 0,
        status: 'ACTIVE',
        photo_file_id: null,
        photo_url: null,
        household_photo_url: null,
        thumbnail_url: null,
        gallery_count: 0
      };
      return payload(savedHousehold);
    }
    if (url.pathname === '/api/households/123' && request.method() === 'GET') return payload(savedHousehold);
    if (url.pathname === '/api/households/123' && request.method() === 'PUT') {
      expect(request.headers()['authorization']).toBe('Bearer test-token');
      expect(request.headers()['x-csrf-token']).toBe('test-csrf');
      const body = JSON.parse(request.postData() || '{}');
      savedHousehold = { ...savedHousehold, household_code: body.householdCode, head_citizen_name: body.headCitizenName, address: body.address };
      return payload(savedHousehold);
    }

    if (url.pathname === '/api/files' && request.method() === 'GET') {
      return payload(uploadedFiles.slice().reverse());
    }
    if (url.pathname === '/api/files' && request.method() === 'POST') {
      expect(request.headers()['authorization']).toBe('Bearer test-token');
      expect(request.headers()['x-csrf-token']).toBe('test-csrf');
      const multipart = request.postDataBuffer().toString('latin1');
      expect(multipart).toContain('name="module"');
      expect(multipart).toContain('household');
      expect(multipart).toContain('name="entityId"');
      expect(multipart).toContain('123');
      expect(multipart).toContain('name="fileType"');
      expect(multipart).toContain('PHOTO');
      expect(multipart).toContain('name="file"');
      uploadCount += 1;
      const id = 900 + uploadCount;
      const file = { id, file_type: 'PHOTO', mime_type: 'image/jpeg', preview_url: `/api/files/${id}/preview` };
      uploadedFiles.push(file);
      savedHousehold = { ...savedHousehold, photo_file_id: 2, photo_url: '/api/files/2/preview', household_photo_url: '/api/files/2/preview', thumbnail_url: '/api/files/2/preview', gallery_count: uploadedFiles.length };
      return payload(file);
    }
    if (/^\/api\/files\/\d+$/.test(url.pathname) && request.method() === 'DELETE') {
      expect(request.headers()['authorization']).toBe('Bearer test-token');
      expect(request.headers()['x-csrf-token']).toBe('test-csrf');
      deleteCount += 1;
      const id = Number(url.pathname.split('/').pop());
      const index = uploadedFiles.findIndex(file => file.id === id);
      if (index >= 0) uploadedFiles.splice(index, 1);
      return payload({ id });
    }
    if (new RegExp('^/api/files/\\d+/preview$').test(url.pathname)) {
      expect(request.headers()['cookie'] || '').toContain('thon09_token=test-token');
      previewIds.push(Number(url.pathname.split('/')[3]));
      return route.fulfill({ contentType: 'image/png', body: fs.readFileSync(pngFile('thon09-preview.png')) });
    }

    return payload({});
  });

  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await page.evaluate(() => {
    const user = { id: 1, email: 'admin@example.test', displayName: 'Admin Test', role: 'SUPER_ADMIN', status: 'ACTIVE' };
    App.token = 'test-token';
    App.csrfToken = 'test-csrf';
    App.user = user;
    window.App = App;
    localStorage.setItem('thon09_token', 'test-token');
    localStorage.setItem('thon09_csrf', 'test-csrf');
    localStorage.setItem('thon09_user', JSON.stringify(user));
    if (typeof window.syncAuthCookie === 'function') window.syncAuthCookie();
    window.showApp();
    window.Thon09NavigationController?.navigate('households');
  });

  await page.evaluate(() => window.openHouseholdForm());
  await expectHouseholdModalOpen(page);
  await page.evaluate(() => window.thon09EnhanceHouseholdPhotoCapture && window.thon09EnhanceHouseholdPhotoCapture());
  await expect(page.locator('#householdPhotoLibraryBtn')).toBeVisible();
  await page.locator('#householdModal input[name="householdCode"]').fill('QA-PHOTO-001');
  await page.locator('#householdModal input[name="headCitizenName"]').fill('Nguyá»…n VÄƒn Test');
  await page.locator('#householdModal input[name="address"]').fill('ThÃ´n 09');
  await page.locator('#householdModal input[name="householdPhoto"]').setInputFiles(pngFile('thon09-library.png'));
  await expect.poll(() => page.locator('#householdPhotoPreview img').count()).toBe(1);
  await page.locator('#householdForm button[type="submit"]').click();
  await expect.poll(() => uploadCount).toBe(1);

  await page.evaluate(() => window.showHousehold(123));
  await expect(page.locator('#detailModal')).toHaveClass(/show/);
  await expect(page.locator('#detailModal .household-detail-photo img')).toBeVisible();
  const detailPreviewButton = page.locator('#detailModal .household-detail-photo [data-platform-action="digitalProfile.file.preview"]');
  await expect(detailPreviewButton).toBeVisible();
  await expect(detailPreviewButton).toHaveAttribute('data-file-id', '901');
  const previewsBeforeClick = previewIds.length;
  await detailPreviewButton.click();
  await expect.poll(() => previewIds.length).toBeGreaterThan(previewsBeforeClick);
  expect(previewIds).not.toContain(2);
  await page.evaluate(() => {
    const modal = document.getElementById('detailModal');
    if (modal) {
      modal.classList.remove('show');
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden', 'true');
      modal.removeAttribute('aria-modal');
    }
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    document.querySelectorAll('.modal-backdrop').forEach(item => item.remove());
  });

  await page.evaluate(() => window.openHouseholdForm(123));
  await expectHouseholdModalOpen(page);
  await expect(page.locator('#householdPhotoViewBtn')).toBeVisible();
  await expect(page.locator('#householdForm input[name="id"]')).toHaveValue('123');
  await page.locator('#householdModal .household-photo-widget input[type="file"]').last().setInputFiles(pngFile('thon09-camera.png'));
  await expect.poll(() => page.locator('#householdPhotoPreview img').count()).toBe(1);
  await page.locator('#householdForm button[type="submit"]').click();
  await expect.poll(() => uploadCount).toBe(2);
  expect(deleteCount).toBeGreaterThanOrEqual(1);
  expect(consoleErrors).toEqual([]);
});

