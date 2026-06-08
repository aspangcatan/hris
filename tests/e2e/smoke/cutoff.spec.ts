import { test, expect } from '@playwright/test';

test.describe('Cutoff summaries page', () => {
  test('cutoff summaries page loads', async ({ page }) => {
    await page.goto('/cutoff-summaries/manage');
    await expect(page.locator('.saln-board')).toBeVisible({ timeout: 10000 });
  });

  test('cutoff table is rendered', async ({ page }) => {
    await page.goto('/cutoff-summaries/manage');
    await expect(page.locator('table.saln-table-premium')).toBeVisible({ timeout: 10000 });
  });
});
