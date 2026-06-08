import { test, expect } from '@playwright/test';

// Override storageState for login tests — we test unauthenticated flows here
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Login page', () => {
  test('login page renders with username and password fields', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('#inputUsername')).toBeVisible();
    await expect(page.locator('#inputPassword')).toBeVisible();
    await expect(page.locator('button[type=submit]')).toBeVisible();
  });

  test('wrong credentials show error message', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#inputUsername', 'wronguser');
    await page.fill('#inputPassword', 'wrongpass');
    await page.click('button[type=submit]');
    await expect(page.locator('.border-rose-200')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('.border-rose-200')).toContainText('credentials do not match');
  });

  test('correct credentials redirect away from login', async ({ page }) => {
    await page.goto('/login');
    await page.fill('#inputUsername', process.env.E2E_USERNAME ?? '');
    await page.fill('#inputPassword', process.env.E2E_PASSWORD ?? '');
    await page.click('button[type=submit]');
    await page.waitForURL(url => !url.toString().includes('/login'), { timeout: 10000 });
    expect(page.url()).not.toContain('/login');
  });
});
