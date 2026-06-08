import { test, expect } from '@playwright/test';

test.describe('Payroll page', () => {
  test('payroll page loads', async ({ page }) => {
    await page.goto('/payroll');
    await expect(page.locator('.payroll-board')).toBeVisible({ timeout: 10000 });
  });

  test('Create Payroll button is visible', async ({ page }) => {
    await page.goto('/payroll');
    await expect(page.locator('#btn_add')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('#btn_add')).toContainText('Create Payroll');
  });

  test('payroll list table renders', async ({ page }) => {
    await page.goto('/payroll');
    await expect(page.locator('.payroll-table-shell')).toBeVisible({ timeout: 10000 });
  });
});
