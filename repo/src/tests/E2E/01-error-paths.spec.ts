import { test, expect } from '@playwright/test';

test.describe('HTTP Error Handling', () => {
  test('404 for nonexistent page', async ({ page }) => {
    const response = await page.goto('/nonexistent-page-xyz');
    expect(response?.status()).toBe(404);
  });

  test('404 for nonexistent API endpoint', async ({ page }) => {
    const response = await page.goto('/api/nonexistent');
    expect(response?.status()).toBe(404);
  });
});

test.describe('Authentication Error Paths', () => {
  test('invalid credentials show error and stay on login page', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#username', 'baduser');
    await page.fill('#password', 'badpass');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(5000);
    await expect(page.locator('#username')).toBeVisible();
    const hasError = await page.locator('.text-red-600').isVisible().catch(() => false);
    const hasErrorText = await page.locator('text=Invalid').isVisible().catch(() => false);
    expect(hasError || hasErrorText).toBeTruthy();
  });

  test('unauthenticated staff route redirects to login', async ({ page }) => {
    await page.goto('/staff/orders');
    await expect(page).toHaveURL(/login/);
  });

  test('unauthenticated admin dashboard redirects', async ({ page }) => {
    await page.goto('/admin/dashboard');
    await expect(page).toHaveURL(/login/);
  });

  test('unauthenticated admin menu redirects', async ({ page }) => {
    await page.goto('/admin/menu');
    await expect(page).toHaveURL(/login/);
  });

  test('unauthenticated admin promotions redirects', async ({ page }) => {
    await page.goto('/admin/promotions');
    await expect(page).toHaveURL(/login/);
  });

  test('unauthenticated admin users redirects', async ({ page }) => {
    await page.goto('/admin/users');
    await expect(page).toHaveURL(/login/);
  });

  test('unauthenticated admin security redirects', async ({ page }) => {
    await page.goto('/admin/security');
    await expect(page).toHaveURL(/login/);
  });

  test('unauthenticated admin alerts redirects', async ({ page }) => {
    await page.goto('/admin/alerts');
    await expect(page).toHaveURL(/login/);
  });

  test('unauthenticated manager reconciliation redirects', async ({ page }) => {
    await page.goto('/manager/reconciliation');
    await expect(page).toHaveURL(/login/);
  });
});

test.describe('Guest Page Accessibility', () => {
  test('homepage accessible without auth (200)', async ({ page }) => {
    const r = await page.goto('/');
    expect(r?.status()).toBe(200);
  });

  test('menu page accessible without auth (200)', async ({ page }) => {
    const r = await page.goto('/menu');
    expect(r?.status()).toBe(200);
  });

  test('cart page accessible without auth (200)', async ({ page }) => {
    const r = await page.goto('/cart');
    expect(r?.status()).toBe(200);
  });

  test('checkout page accessible without auth (200)', async ({ page }) => {
    const r = await page.goto('/checkout');
    expect(r?.status()).toBe(200);
  });

  test('login page accessible without auth (200)', async ({ page }) => {
    const r = await page.goto('/login');
    expect(r?.status()).toBe(200);
  });

  test('order tracker accessible without auth (200)', async ({ page }) => {
    const r = await page.goto('/order/1');
    expect(r?.status()).toBe(200);
  });

  test('time-sync API accessible without auth (200)', async ({ page }) => {
    const r = await page.goto('/api/time-sync');
    expect(r?.status()).toBe(200);
  });
});

test.describe('UI Consistency Across Pages', () => {
  test('all guest pages have HarborBite header', async ({ page }) => {
    for (const path of ['/', '/cart', '/checkout', '/login']) {
      await page.goto(path);
      await expect(page.locator('text=HarborBite').first()).toBeVisible();
    }
  });

  test('kiosk pages have Cart link in header', async ({ page }) => {
    for (const path of ['/', '/cart', '/checkout']) {
      await page.goto(path);
      await expect(page.locator('a:has-text("Cart")').first()).toBeVisible();
    }
  });
});
