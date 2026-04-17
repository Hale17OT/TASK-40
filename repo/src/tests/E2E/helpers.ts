import { Page, expect } from '@playwright/test';

/**
 * Login helper that handles Livewire's client-side redirect and potential CAPTCHA.
 * Stores the session so subsequent page.goto() calls are authenticated.
 */
export async function loginAs(page: Page, username: string, password: string): Promise<boolean> {
  await page.goto('/login');
  await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});

  await page.fill('#username', username);
  await page.fill('#password', password);

  // Solve CAPTCHA if visible
  const captchaVisible = await page.locator('text=Security Check').isVisible().catch(() => false);
  if (captchaVisible) {
    const bodyText = await page.textContent('body') || '';
    const match = bodyText.match(/(\d+)\s*([+\-])\s*(\d+)\s*=\s*\?/);
    if (match) {
      const a = parseInt(match[1]);
      const op = match[2];
      const b = parseInt(match[3]);
      const answer = op === '+' ? a + b : a - b;
      await page.locator('input[placeholder="?"]').fill(answer.toString());
    }
  }

  await page.click('button[type="submit"]');
  await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});

  // Check if we left the login page
  const currentUrl = page.url();
  return !currentUrl.includes('/login');
}

/**
 * Navigate to a page as an authenticated user.
 * Returns the page content for assertions.
 */
export async function authenticatedGoto(page: Page, username: string, password: string, path: string): Promise<string> {
  await loginAs(page, username, password);
  await page.goto(path);
  await page.waitForLoadState("networkidle", { timeout: 10000 }).catch(() => {});
  return await page.textContent('body') || '';
}
