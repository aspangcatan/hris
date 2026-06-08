# Playwright Smoke Tests Design

**Date:** 2026-06-08
**Module:** Testing / QA

---

## Overview

Add Playwright (TypeScript) to the HRIS project for browser-based smoke testing. The goal is a small, fast suite that verifies key pages load and core UI flows are functional — run manually before a deploy.

The app runs at `http://localhost/hris` (WAMP local). Node 18 and npm 9 are already available. Playwright is installed as a devDependency so it is never included in production builds.

---

## Production Safety

- `@playwright/test` added to `devDependencies` only — excluded from `npm install --production`.
- `tests/e2e/` contains only static test files — no impact on the running PHP app.
- Credentials loaded from `.env` — never hardcoded, never committed.
- `tests/e2e/.auth/` (saved session) added to `.gitignore`.
- `PayrollTestSeeder` is dev-only and must be run manually.

---

## 1. Project Structure

```
playwright.config.ts                    ← root-level config
.gitignore                              ← add tests/e2e/.auth/
package.json                            ← add @playwright/test devDep + test:e2e script
tests/
  e2e/
    .auth/                              ← gitignored saved session
    global-setup.ts                     ← one-time login, saves storageState
    smoke/
      login.spec.ts
      dashboard.spec.ts
      overtime.spec.ts
      leave.spec.ts
      payroll.spec.ts
      cutoff.spec.ts
database/seeders/
  PayrollTestSeeder.php                 ← seeds payroll components + settings
```

---

## 2. Configuration

### `playwright.config.ts`

```ts
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
```

### `package.json` additions

```json
"scripts": {
  "test:e2e": "playwright test"
},
"devDependencies": {
  "@playwright/test": "^1.44.0"
}
```

### `.env` additions

```
E2E_USERNAME=apangcatan
E2E_PASSWORD=Asnaui123
```

---

## 3. Global Setup — One-Time Login

### `tests/e2e/global-setup.ts`

Logs in as the test user once and saves the browser session to `.auth/user.json`. All smoke tests reuse this session — no repeated login overhead.

```ts
import { chromium, FullConfig } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

async function globalSetup(config: FullConfig) {
  const baseURL = config.projects[0].use.baseURL ?? 'http://localhost/hris';
  const browser = await chromium.launch();
  const page = await browser.newPage();

  await page.goto(`${baseURL}/login`);
  await page.fill('[name=username]', process.env.E2E_USERNAME ?? '');
  await page.fill('[name=password]', process.env.E2E_PASSWORD ?? '');
  await page.click('[type=submit]');
  await page.waitForURL(`${baseURL}/**`);

  const authDir = path.join('tests', 'e2e', '.auth');
  if (!fs.existsSync(authDir)) fs.mkdirSync(authDir, { recursive: true });
  await page.context().storageState({ path: path.join(authDir, 'user.json') });

  await browser.close();
}

export default globalSetup;
```

---

## 4. Smoke Tests

Each test is a single `test()` call — load a page, assert a key element is visible. No multi-step flows (those belong in regression tests, not smoke tests).

### `login.spec.ts`

- Wrong credentials show an error message.
- Correct credentials redirect away from `/login`.

### `dashboard.spec.ts`

- `/` or post-login redirect loads.
- Sidebar navigation is visible.

### `overtime.spec.ts`

- `/overtime/file` loads.
- Filing form is visible (Date, Time From, Time To fields).

### `leave.spec.ts`

- Leave application page loads.
- Tab structure or filing form is visible.

### `payroll.spec.ts`

- `/payroll` loads.
- Payroll list table is rendered.
- "Create Payroll" button is visible.

### `cutoff.spec.ts`

- `/cutoff-summaries/manage` loads.
- The cutoff table shell is visible.

---

## 5. PayrollTestSeeder

Seeds test data for user `apangcatan` to ensure payroll-related smoke tests have valid supporting data.

**Seeds:**

1. **`payroll_settings`** — upsert for apangcatan's userid:
   - `salary_grade`: 18, `step`: 1, `basic_salary`: 40000.00

2. **`employee_payroll_components`** — for each component below, upsert for apangcatan's userid:
   - GSIS-PS (code: `GSIS_PS`, type: deduction, amount: 9% of basic)
   - PHIC (code: `PHIC`, type: deduction, amount: 5% of basic)
   - HDMF (code: `HDMF`, type: deduction, amount: 100)
   - PERA (code: `PERA`, type: earning, amount: 2000)
   - RATA (code: `RATA`, type: earning, amount: 3000)

Run with:
```bash
php artisan db:seed --class=PayrollTestSeeder
```

---

## 6. Running the Suite

```bash
# Install Playwright (first time only)
npm install
npx playwright install chromium

# Run smoke tests
npm run test:e2e

# Run a single spec
npx playwright test tests/e2e/smoke/payroll.spec.ts

# Show browser (headed mode for debugging)
npx playwright test --headed
```

---

## 7. Out of Scope

- Firefox/WebKit cross-browser testing.
- Multi-step flows (form submit → verify result).
- CI/CD integration.
- Visual screenshot regression.
- Test data teardown (smoke tests are read-only / non-mutating).
