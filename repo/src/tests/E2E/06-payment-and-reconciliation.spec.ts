import { test, expect } from '@playwright/test';
import { loginAs } from './helpers';

test.describe('Time Sync API', () => {
  test('returns server_time as a number', async ({ page }) => {
    const r = await page.goto('/api/time-sync');
    expect(r?.status()).toBe(200);
    const json = await r?.json();
    expect(typeof json.server_time).toBe('number');
  });

  test('returns server_time_iso as ISO string', async ({ page }) => {
    const r = await page.goto('/api/time-sync');
    const json = await r?.json();
    expect(json.server_time_iso).toMatch(/^\d{4}-\d{2}-\d{2}T/);
  });

  test('returns timezone', async ({ page }) => {
    const r = await page.goto('/api/time-sync');
    const json = await r?.json();
    expect(json.timezone).toBeTruthy();
    expect(json.timezone).toContain('/'); // e.g. America/Chicago
  });

  test('server time is within 60s of client time', async ({ page }) => {
    const r = await page.goto('/api/time-sync');
    const json = await r?.json();
    const clientTime = Math.floor(Date.now() / 1000);
    expect(Math.abs(json.server_time - clientTime)).toBeLessThan(60);
  });
});

test.describe('Manager Reconciliation', () => {
  test('reconciliation requires auth', async ({ page }) => {
    await page.goto('/manager/reconciliation');
    await expect(page).toHaveURL(/login/);
  });

  test('reconciliation page loads for manager', async ({ page }) => {
    await loginAs(page, 'manager', 'manager123');
    await page.goto('/manager/reconciliation');
    await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});
    const content = await page.textContent('body') || '';
    expect(content.includes('Reconciliation') || content.includes('Incident') || content.includes('tickets')).toBeTruthy();
  });

  test('reconciliation page loads for admin', async ({ page }) => {
    await loginAs(page, 'admin', 'admin123');
    await page.goto('/manager/reconciliation');
    await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});
    const content = await page.textContent('body') || '';
    expect(content.includes('Reconciliation') || content.includes('Incident') || content.includes('tickets')).toBeTruthy();
  });

  test('reconciliation has status filter', async ({ page }) => {
    await loginAs(page, 'manager', 'manager123');
    await page.goto('/manager/reconciliation');
    await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});
    const selects = await page.locator('select').count();
    expect(selects).toBeGreaterThanOrEqual(1);
  });

  test('cashier cannot access reconciliation', async ({ page }) => {
    await loginAs(page, 'cashier', 'cashier123');
    const r = await page.goto('/manager/reconciliation');
    expect(r?.status()).toBe(403);
  });

  test('kitchen cannot access reconciliation', async ({ page }) => {
    await loginAs(page, 'kitchen', 'kitchen123');
    const r = await page.goto('/manager/reconciliation');
    expect(r?.status()).toBe(403);
  });
});

test.describe('Checkout Flow Structure', () => {
  test('checkout has kiosk layout with branding', async ({ page }) => {
    await page.goto('/checkout');
    await expect(page.locator('text=HarborBite')).toBeVisible();
    await expect(page.locator('text=Checkout')).toBeVisible();
  });

  test('checkout has cart link in header', async ({ page }) => {
    await page.goto('/checkout');
    await expect(page.locator('a:has-text("Cart")')).toBeVisible();
  });

  test('checkout shows empty state or order review', async ({ page }) => {
    await page.goto('/checkout');
    await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});
    const content = await page.textContent('body') || '';
    expect(content.includes('empty') || content.includes('Place Order') || content.includes('Review')).toBeTruthy();
  });
});
