import { chromium, FullConfig } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

async function globalSetup(config: FullConfig) {
  const baseURL = config.projects[0].use.baseURL ?? 'http://localhost/hris';
  const username = process.env.E2E_USERNAME ?? '';
  const password = process.env.E2E_PASSWORD ?? '';

  if (!username || !password) {
    throw new Error('E2E_USERNAME and E2E_PASSWORD must be set in .env');
  }

  const browser = await chromium.launch();
  const page = await browser.newPage();

  await page.goto(`${baseURL}/login`);
  await page.fill('#inputUsername', username);
  await page.fill('#inputPassword', password);
  await page.click('button[type=submit]');

  // Wait until we leave the login page
  await page.waitForURL(url => !url.toString().includes('/login'), { timeout: 15000 });

  const authDir = path.join('tests', 'e2e', '.auth');
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  await page.context().storageState({ path: path.join(authDir, 'user.json') });
  await browser.close();

  console.log('✔ Global setup: auth session saved to tests/e2e/.auth/user.json');
}

export default globalSetup;
