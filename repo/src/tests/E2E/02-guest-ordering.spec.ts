import { test, expect } from '@playwright/test';

test.describe('Menu Browsing', () => {
  test('homepage loads with HarborBite branding', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('text=HarborBite')).toBeVisible({ timeout: 15000 });
  });

  test('menu page has search input', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('input[placeholder="Search menu..."]')).toBeVisible();
  });

  test('menu displays item cards with prices', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(/\$\d+\.\d{2}/.test(content)).toBeTruthy();
  });

  test('menu items have Add to Cart buttons', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(3000);
    const count = await page.locator('button:has-text("Add to Cart")').count();
    expect(count).toBeGreaterThan(0);
  });

  test('menu shows attribute badges', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    const hasBadge = content.includes('Gluten-Free') || content.includes('Contains Nuts') || content.includes('Spicy');
    expect(hasBadge).toBeTruthy();
  });

  test('menu shows category names', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    const hasCat = content.includes('Burgers') || content.includes('Salads') || content.includes('Sides');
    expect(hasCat).toBeTruthy();
  });

  test('menu shows item count', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    expect(content.includes('Showing')).toBeTruthy();
  });

  test('menu item descriptions are visible', async ({ page }) => {
    await page.goto('/');
    await page.waitForTimeout(3000);
    const content = await page.textContent('body') || '';
    // Seeded items have descriptions
    const hasDesc = content.includes('beef') || content.includes('patty') || content.includes('fries') || content.includes('salad');
    expect(hasDesc).toBeTruthy();
  });
});

test.describe('Cart', () => {
  test('cart page shows empty state', async ({ page }) => {
    await page.goto('/cart');
    await expect(page.locator('text=Your cart is empty')).toBeVisible();
  });

  test('empty cart has Browse Menu link', async ({ page }) => {
    await page.goto('/cart');
    await expect(page.locator('a:has-text("Browse Menu")')).toBeVisible();
  });

  test('cart page has Your Cart heading', async ({ page }) => {
    await page.goto('/cart');
    await expect(page.getByRole('heading', { name: 'Your Cart', exact: true })).toBeVisible();
  });

  test('cart page uses kiosk layout with header', async ({ page }) => {
    await page.goto('/cart');
    await expect(page.locator('text=HarborBite')).toBeVisible();
    await expect(page.locator('a:has-text("Cart")')).toBeVisible();
  });
});

test.describe('Checkout', () => {
  test('checkout page loads with heading', async ({ page }) => {
    await page.goto('/checkout');
    await expect(page.locator('text=Checkout')).toBeVisible();
  });

  test('checkout shows empty or order review state', async ({ page }) => {
    await page.goto('/checkout');
    await page.waitForTimeout(2000);
    const content = await page.textContent('body') || '';
    const hasContent = content.includes('empty') || content.includes('Browse Menu') || content.includes('Place Order') || content.includes('Review');
    expect(hasContent).toBeTruthy();
  });

  test('checkout uses kiosk layout', async ({ page }) => {
    await page.goto('/checkout');
    await expect(page.locator('text=HarborBite')).toBeVisible();
  });
});

test.describe('Order Tracking', () => {
  test('order tracker page responds with 200', async ({ page }) => {
    const r = await page.goto('/order/999');
    expect(r?.status()).toBe(200);
  });

  test('order tracker shows status or not-found', async ({ page }) => {
    await page.goto('/order/999999');
    await page.waitForTimeout(2000);
    const content = await page.textContent('body') || '';
    expect(content.includes('not found') || content.includes('Order Status')).toBeTruthy();
  });

  test('order tracker uses kiosk layout', async ({ page }) => {
    await page.goto('/order/1');
    await expect(page.locator('text=HarborBite')).toBeVisible();
  });
});

test.describe('Login Page', () => {
  test('login page has form fields', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('#username')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('button:has-text("Sign In")')).toBeVisible();
  });

  test('login page shows Staff Login heading', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('text=Staff Login')).toBeVisible();
  });
});
