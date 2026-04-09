import { test, expect } from '@playwright/test';
import { loginAs } from './helpers';

test.describe('Admin Dashboard', () => {
  test('dashboard loads with analytics widgets', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/dashboard');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Total GMV')).toBeTruthy();
    expect(content.includes('Total Sessions')).toBeTruthy();
    expect(content.includes('Conversion Rate')).toBeTruthy();
    expect(content.includes('Active Orders')).toBeTruthy();
  });

  test('dashboard has conversion funnel', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/dashboard');
    await page.waitForTimeout(2000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Conversion Funnel')).toBeTruthy();
    expect(content.includes('Page Views')).toBeTruthy();
    expect(content.includes('Orders Placed')).toBeTruthy();
  });

  test('dashboard has date range picker', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/dashboard');
    await page.waitForTimeout(2000);
    const dateInputs = page.locator('input[type="date"]');
    expect(await dateInputs.count()).toBeGreaterThanOrEqual(2);
  });

  test('dashboard has daily GMV section', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/dashboard');
    await page.waitForTimeout(2000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Daily GMV')).toBeTruthy();
  });

  test('dashboard has trending terms management', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/dashboard');
    await page.waitForTimeout(2000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Pinned Trending Terms')).toBeTruthy();
    expect(content.includes('Pin Term')).toBeTruthy();
    expect(content.includes('/ 20 terms pinned')).toBeTruthy();
  });
});

test.describe('Admin Menu Management', () => {
  test('menu management page loads', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/menu');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Menu') || content.includes('Categor') || content.includes('Item')).toBeTruthy();
  });

  test('menu page has form inputs for creating items', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/menu');
    await page.waitForTimeout(3000);
    const inputCount = await page.locator('input').count();
    expect(inputCount).toBeGreaterThan(0);
  });

  test('menu page shows existing items or categories', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/menu');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    // Should show seeded data
    expect(content.includes('Burger') || content.includes('Burgers') || content.includes('BRG')).toBeTruthy();
  });
});

test.describe('Admin Promotion Management', () => {
  test('promotions page loads', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/promotions');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Promotion') || content.includes('Discount') || content.includes('BOGO')).toBeTruthy();
  });

  test('promotions page shows seeded promotions', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/promotions');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('10%') || content.includes('BOGO') || content.includes('percentage_off')).toBeTruthy();
  });

  test('promotions page has type selector and form', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/promotions');
    await page.waitForTimeout(3000);
    const selects = await page.locator('select').count();
    expect(selects).toBeGreaterThan(0);
  });
});

test.describe('Admin User Management', () => {
  test('users page loads with seeded users', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/users');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('admin')).toBeTruthy();
    expect(content.includes('cashier')).toBeTruthy();
  });

  test('users page has role selector', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/users');
    await page.waitForTimeout(3000);
    const selects = await page.locator('select').count();
    expect(selects).toBeGreaterThan(0);
  });

  test('users page has password and name inputs', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/users');
    await page.waitForTimeout(3000);
    const inputs = await page.locator('input').count();
    expect(inputs).toBeGreaterThanOrEqual(3); // name, username, password at minimum
  });
});

test.describe('Admin Security', () => {
  test('security page has blacklist and whitelist sections', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/security');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Blacklist')).toBeTruthy();
    expect(content.includes('Whitelist')).toBeTruthy();
  });

  test('security page has type selectors', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/security');
    await page.waitForTimeout(3000);
    const selects = await page.locator('select').count();
    expect(selects).toBeGreaterThanOrEqual(2);
  });

  test('security audit log loads', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/security/audit');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Security Audit Log')).toBeTruthy();
    expect(content.includes('Rule Hit Logs')).toBeTruthy();
    expect(content.includes('Privilege Escalation')).toBeTruthy();
  });

  test('audit log has type filter buttons', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/security/audit');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('All')).toBeTruthy();
    expect(content.includes('Rate Limit')).toBeTruthy();
  });
});

test.describe('Admin Alerts', () => {
  test('alerts page loads', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    const r = await page.goto('/admin/alerts');
    expect(r?.status()).toBe(200);
  });
});

test.describe('Admin Navigation', () => {
  test('admin sidebar has all nav links', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/dashboard');
    await page.waitForTimeout(2000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Dashboard')).toBeTruthy();
    expect(content.includes('Menu')).toBeTruthy();
    expect(content.includes('Promotions')).toBeTruthy();
    expect(content.includes('Users')).toBeTruthy();
    expect(content.includes('Security')).toBeTruthy();
    expect(content.includes('Alerts')).toBeTruthy();
  });

  test('admin sidebar shows logout', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/dashboard');
    await page.waitForTimeout(2000);
    await expect(page.locator('text=Logout').first()).toBeVisible();
  });

  test('admin sidebar shows Admin badge', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/admin/dashboard');
    await page.waitForTimeout(2000);
    await expect(page.locator('text=Admin').first()).toBeVisible();
  });
});
