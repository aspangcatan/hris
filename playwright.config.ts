import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  globalSetup: './tests/e2e/global-setup.ts',
  use: {
    baseURL: process.env.APP_URL ?? 'http://localhost/hris',
    storageState: 'tests/e2e/.auth/user.json',
    headless: true,
  },
  projects: [
    { name: 'chromium', use: { browserName: 'chromium' } },
  ],
});
