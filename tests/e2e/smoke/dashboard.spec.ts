import { test, expect } from '@playwright/test';

test.describe('Dashboard', () => {
  test('dashboard page loads with sidebar', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page.locator('#sidebar')).toBeVisible({ timeout: 10000 });
  });

  test('sidebar contains navigation links', async ({ page }) => {
    await page.goto('/dashboard');
    const navLinks = page.locator('#sidebar .nav-link');
    await expect(navLinks.first()).toBeVisible({ timeout: 10000 });
  });
});
