import { test, expect } from '@playwright/test';

test.describe('Leave application page', () => {
  test('leave application page loads', async ({ page }) => {
    await page.goto('/leave-application');
    await expect(page.locator('.leave-app-board')).toBeVisible({ timeout: 10000 });
  });

  test('leave tab navigation is visible', async ({ page }) => {
    await page.goto('/leave-application');
    await expect(page.locator('#leaveTab')).toBeVisible({ timeout: 10000 });
  });

  test('leave applications tab is active by default', async ({ page }) => {
    await page.goto('/leave-application');
    await expect(page.locator('#tab-leave-applications')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#tab-leave-applications')).toHaveClass(/active/);
  });
});
