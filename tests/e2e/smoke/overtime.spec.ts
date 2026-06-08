import { test, expect } from '@playwright/test';

test.describe('Overtime filing page', () => {
  test('overtime filing page loads', async ({ page }) => {
    await page.goto('/overtime/file');
    await expect(page.locator('.ot-board')).toBeVisible({ timeout: 10000 });
  });

  test('overtime filing form fields are visible', async ({ page }) => {
    await page.goto('/overtime/file');
    await expect(page.locator('input[name=ot_date]')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('input[name=time_from]')).toBeVisible();
    await expect(page.locator('input[name=time_to]')).toBeVisible();
  });

  test('overtime filings table shell is visible', async ({ page }) => {
    await page.goto('/overtime/file');
    await expect(page.locator('.ot-table-shell')).toBeVisible({ timeout: 10000 });
  });
});
