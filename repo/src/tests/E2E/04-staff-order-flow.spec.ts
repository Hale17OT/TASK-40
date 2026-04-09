import { test, expect } from '@playwright/test';
import { loginAs } from './helpers';

test.describe('Staff Authentication', () => {
  test('login page has proper form structure', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('text=Staff Login')).toBeVisible();
    await expect(page.locator('#username')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button:has-text("Sign In")')).toBeVisible();
  });

  test('successful cashier login navigates away', async ({ page }) => {
    const success = await loginAs(page, 'cashier', 'cashier123');
    expect(success).toBeTruthy();
  });

  test('successful admin login navigates away', async ({ page }) => {
    const success = await loginAs(page, 'admin', 'admin123');
    expect(success).toBeTruthy();
  });
});

test.describe('Staff Order Queue', () => {
  test('staff orders page loads after login', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    await page.goto('/staff/orders');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Active Orders') || content.includes('No orders') || content.includes('Logout')).toBeTruthy();
  });

  test('order page has status filter tabs', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    await page.goto('/staff/orders');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Pending') && content.includes('Preparing') && content.includes('Served')).toBeTruthy();
  });

  test('order page shows Settled and Canceled tabs', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    await page.goto('/staff/orders');
    await page.waitForTimeout(2000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Settled') && content.includes('Canceled')).toBeTruthy();
  });

  test('staff page shows logout button', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    await page.goto('/staff/orders');
    await page.waitForTimeout(2000);
    await expect(page.locator('text=Logout')).toBeVisible();
  });

  test('staff page uses Livewire for real-time updates', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    await page.goto('/staff/orders');
    await page.waitForTimeout(2000);
    const html = await page.content();
    // wire:poll is present when orders exist; wire:id is always present for Livewire
    expect(html.includes('wire:id') || html.includes('wire:poll')).toBeTruthy();
  });

  test('staff page shows role indicator', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    await page.goto('/staff/orders');
    await page.waitForTimeout(2000);
    const content = await page.textContent('body') || '';
    expect(content.includes('cashier') || content.includes('Cashier')).toBeTruthy();
  });
});

test.describe('Role-Based Access Control', () => {
  test('cashier gets 403 on admin dashboard', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    const r = await page.goto('/admin/dashboard');
    expect(r?.status()).toBe(403);
  });

  test('cashier gets 403 on admin menu', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    const r = await page.goto('/admin/menu');
    expect(r?.status()).toBe(403);
  });

  test('cashier gets 403 on admin users', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    const r = await page.goto('/admin/users');
    expect(r?.status()).toBe(403);
  });

  test('cashier gets 403 on admin promotions', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    const r = await page.goto('/admin/promotions');
    expect(r?.status()).toBe(403);
  });

  test('cashier gets 403 on admin security', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    const r = await page.goto('/admin/security');
    expect(r?.status()).toBe(403);
  });

  test('kitchen gets 403 on admin dashboard', async ({ page }) => {
    await loginAs(page, 'kitchen', 'kitchen123');
    const r = await page.goto('/admin/dashboard');
    expect(r?.status()).toBe(403);
  });

  test('cashier gets 403 on manager reconciliation', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    const r = await page.goto('/manager/reconciliation');
    expect(r?.status()).toBe(403);
  });
});
