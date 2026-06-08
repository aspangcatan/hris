# Playwright Smoke Tests Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Playwright (TypeScript) smoke tests that verify key HRIS pages load and are functional — run manually before a deploy.

**Architecture:** Playwright installed as a devDependency; tests live in `tests/e2e/smoke/`; a global-setup logs in once as `apangcatan` and saves auth state to `tests/e2e/.auth/user.json`; all 6 smoke specs reuse that session. A `PayrollTestSeeder` seeds payroll master data for the test account. The app must be running at `http://localhost/hris` before tests run.

**Tech Stack:** Node 18, `@playwright/test` ^1.44, TypeScript (via Playwright's built-in transpiler — no separate `tsc` step), Chromium, Laravel 8 PHP (for the seeder).

---

## File Map

| Action | File |
|---|---|
| Modify | `package.json` |
| Create | `playwright.config.ts` |
| Modify | `.gitignore` |
| Create | `tests/e2e/global-setup.ts` |
| Create | `tests/e2e/smoke/login.spec.ts` |
| Create | `tests/e2e/smoke/dashboard.spec.ts` |
| Create | `tests/e2e/smoke/overtime.spec.ts` |
| Create | `tests/e2e/smoke/leave.spec.ts` |
| Create | `tests/e2e/smoke/payroll.spec.ts` |
| Create | `tests/e2e/smoke/cutoff.spec.ts` |
| Create | `database/seeders/PayrollTestSeeder.php` |

---

## Task 1: Install Playwright + configure project

**Files:**
- Modify: `package.json`
- Create: `playwright.config.ts`
- Modify: `.gitignore`
- Modify: `.env`

- [ ] **Step 1: Install @playwright/test**

```bash
cd c:\wamp64\projects\hris
npm install --save-dev @playwright/test@^1.44.0
npx playwright install chromium
```

Expected output ends with: `✔ Chromium ... chromium` installed.

- [ ] **Step 2: Add test:e2e script to package.json**

In `package.json`, add `"test:e2e"` to the `scripts` block:

```json
{
    "private": true,
    "scripts": {
        "dev": "npm run development",
        "development": "cross-env NODE_ENV=development node_modules/webpack/bin/webpack.js --progress --hide-modules --config=node_modules/laravel-mix/setup/webpack.config.js",
        "watch": "npm run development -- --watch",
        "watch-poll": "npm run watch -- --watch-poll",
        "hot": "cross-env NODE_ENV=development node_modules/webpack-dev-server/bin/webpack-dev-server.js --inline --hot --disable-host-check --config=node_modules/laravel-mix/setup/webpack.config.js",
        "prod": "npm run production",
        "production": "cross-env NODE_ENV=production node_modules/webpack/bin/webpack.js --no-progress --hide-modules --config=node_modules/laravel-mix/setup/webpack.config.js",
        "test:e2e": "playwright test"
    },
    "devDependencies": {
        "@playwright/test": "^1.44.0",
        "axios": "^0.19",
        "cross-env": "^7.0",
        "laravel-mix": "^5.0.1",
        "lodash": "^4.17.19",
        "resolve-url-loader": "^3.1.0"
    }
}
```

- [ ] **Step 3: Create playwright.config.ts at project root**

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

- [ ] **Step 4: Add credentials to .env**

Append to `.env` (do NOT commit this file — it is already in `.gitignore`):

```
E2E_USERNAME=apangcatan
E2E_PASSWORD=Asnaui123
```

- [ ] **Step 5: Add .auth/ to .gitignore**

Add this line to `.gitignore`:

```
tests/e2e/.auth/
```

- [ ] **Step 6: Verify Playwright can find its config**

```bash
cd c:\wamp64\projects\hris
npx playwright --version
```

Expected: prints version like `Version 1.44.x`

- [ ] **Step 7: Commit**

```bash
git add package.json playwright.config.ts .gitignore
git commit -m "feat: install Playwright and configure smoke test project"
```

---

## Task 2: Global setup — one-time login

**Files:**
- Create: `tests/e2e/global-setup.ts`

The app login form uses:
- Field: `#inputUsername`
- Field: `#inputPassword`
- Submit: `button[type=submit]`
- Post-login URL: `/profile` (Laravel redirects to `redirect()->intended('/profile')`)
- Error message selector: `.border-rose-200` (shown when credentials are wrong)

- [ ] **Step 1: Create global-setup.ts**

```ts
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
```

- [ ] **Step 2: Verify global setup runs (requires app running at http://localhost/hris)**

```bash
cd c:\wamp64\projects\hris
npx playwright test --list
```

Expected: No error; lists 0 tests (no specs yet).

Then run the setup directly by running any spec (we'll add them next — for now just verify config loads):

```bash
npx playwright test --config playwright.config.ts --list 2>&1
```

Expected: Playwright reads config without error.

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/global-setup.ts
git commit -m "feat: add Playwright global setup for one-time auth session"
```

---

## Task 3: login.spec.ts

**Files:**
- Create: `tests/e2e/smoke/login.spec.ts`

Tests the login page independently — does NOT use saved auth state (since it tests the login flow itself).

- [ ] **Step 1: Create login.spec.ts**

```ts
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
```

- [ ] **Step 2: Run the spec (app must be running)**

```bash
cd c:\wamp64\projects\hris
npx playwright test tests/e2e/smoke/login.spec.ts --reporter=list
```

Expected: 3 tests PASS

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/smoke/login.spec.ts
git commit -m "feat: add login smoke spec"
```

---

## Task 4: dashboard.spec.ts

**Files:**
- Create: `tests/e2e/smoke/dashboard.spec.ts`

Uses saved auth state (default from config).

- [ ] **Step 1: Create dashboard.spec.ts**

```ts
import { test, expect } from '@playwright/test';

test.describe('Dashboard', () => {
  test('dashboard page loads with sidebar', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page.locator('#sidebar')).toBeVisible({ timeout: 10000 });
  });

  test('sidebar contains navigation links', async ({ page }) => {
    await page.goto('/dashboard');
    // Sidebar has at least one nav-link
    const navLinks = page.locator('#sidebar .nav-link');
    await expect(navLinks.first()).toBeVisible({ timeout: 10000 });
  });
});
```

- [ ] **Step 2: Run the spec**

```bash
npx playwright test tests/e2e/smoke/dashboard.spec.ts --reporter=list
```

Expected: 2 tests PASS

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/smoke/dashboard.spec.ts
git commit -m "feat: add dashboard smoke spec"
```

---

## Task 5: overtime.spec.ts

**Files:**
- Create: `tests/e2e/smoke/overtime.spec.ts`

The overtime filing page URL is `/overtime/file`. Key selectors: `.ot-board`, `input[name=ot_date]`, `input[name=time_from]`, `input[name=time_to]`.

- [ ] **Step 1: Create overtime.spec.ts**

```ts
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
```

- [ ] **Step 2: Run the spec**

```bash
npx playwright test tests/e2e/smoke/overtime.spec.ts --reporter=list
```

Expected: 3 tests PASS

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/smoke/overtime.spec.ts
git commit -m "feat: add overtime smoke spec"
```

---

## Task 6: leave.spec.ts

**Files:**
- Create: `tests/e2e/smoke/leave.spec.ts`

The leave application page. Key selectors: `.leave-app-board`, `#leaveTab` (tab navigation), `#tab-leave-applications` (first tab button).

- [ ] **Step 1: Create leave.spec.ts**

```ts
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
```

- [ ] **Step 2: Run the spec**

```bash
npx playwright test tests/e2e/smoke/leave.spec.ts --reporter=list
```

Expected: 3 tests PASS

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/smoke/leave.spec.ts
git commit -m "feat: add leave application smoke spec"
```

---

## Task 7: payroll.spec.ts

**Files:**
- Create: `tests/e2e/smoke/payroll.spec.ts`

The payroll page. Key selectors: `.payroll-board`, `#btn_add` (Create Payroll button), `.payroll-table-shell`.

- [ ] **Step 1: Create payroll.spec.ts**

```ts
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
    // Wait for the JS to load the payroll list
    await expect(page.locator('.payroll-table-shell')).toBeVisible({ timeout: 10000 });
  });
});
```

- [ ] **Step 2: Run the spec**

```bash
npx playwright test tests/e2e/smoke/payroll.spec.ts --reporter=list
```

Expected: 3 tests PASS

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/smoke/payroll.spec.ts
git commit -m "feat: add payroll smoke spec"
```

---

## Task 8: cutoff.spec.ts

**Files:**
- Create: `tests/e2e/smoke/cutoff.spec.ts`

The cutoff summaries management page at `/cutoff-summaries/manage`. Key selector: `.saln-board` (reuses the saln board design pattern).

- [ ] **Step 1: Create cutoff.spec.ts**

```ts
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
```

- [ ] **Step 2: Run the full smoke suite**

```bash
npx playwright test tests/e2e/smoke/ --reporter=list
```

Expected: All specs across all smoke tests PASS (login: 3, dashboard: 2, overtime: 3, leave: 3, payroll: 3, cutoff: 2 = 16 tests total).

- [ ] **Step 3: Commit**

```bash
git add tests/e2e/smoke/cutoff.spec.ts
git commit -m "feat: add cutoff summaries smoke spec"
```

---

## Task 9: PayrollTestSeeder

**Files:**
- Create: `database/seeders/PayrollTestSeeder.php`

Seeds payroll settings and components for the `apangcatan` account so payroll-related pages have valid supporting data.

- [ ] **Step 1: Create PayrollTestSeeder.php**

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayrollTestSeeder extends Seeder
{
    public function run(): void
    {
        $user = DB::table('tdh_user.users')
            ->where('username', 'apangcatan')
            ->first(['id']);

        if (!$user) {
            $this->command->error('User apangcatan not found in tdh_user.users.');
            return;
        }

        $userId = $user->id;
        $basicSalary = 40000.00;

        // Payroll settings
        DB::table('hris.payroll_settings')->updateOrInsert(
            ['userid' => $userId],
            [
                'salary_grade' => 18,
                'step'         => 1,
                'basic_salary' => $basicSalary,
                'updated_at'   => now(),
                'created_at'   => now(),
            ]
        );

        // Component definitions: [code, name, type, taxable, default_amount, amount]
        $components = [
            ['GSIS_PS', 'GSIS Personal Share',  'deduction', false, 0,       round($basicSalary * 0.09, 2)],
            ['PHIC',    'PhilHealth',            'deduction', false, 0,       round($basicSalary * 0.05, 2)],
            ['HDMF',    'Pag-IBIG',              'deduction', false, 100.00,  100.00],
            ['PERA',    'PERA',                  'earning',   true,  2000.00, 2000.00],
            ['RATA',    'RATA',                  'earning',   false, 3000.00, 3000.00],
        ];

        foreach ($components as [$code, $name, $type, $taxable, $defaultAmount, $amount]) {
            // Get or create the component master record
            $componentId = DB::table('hris.payroll_components')
                ->where('code', $code)
                ->value('id');

            if (!$componentId) {
                $componentId = DB::table('hris.payroll_components')->insertGetId([
                    'code'           => $code,
                    'name'           => $name,
                    'type'           => $type,
                    'taxable'        => $taxable ? 1 : 0,
                    'is_global'      => 1,
                    'default_amount' => $defaultAmount,
                    'is_active'      => 1,
                    'sort_order'     => 0,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            }

            // Upsert employee component
            DB::table('hris.employee_payroll_components')->updateOrInsert(
                [
                    'payroll_component_id' => $componentId,
                    'userid'               => $userId,
                ],
                [
                    'amount'       => $amount,
                    'is_active'    => 1,
                    'is_excluded'  => 0,
                    'updated_at'   => now(),
                    'created_at'   => now(),
                ]
            );
        }

        $this->command->info("Seeded payroll settings and components for user apangcatan (id: {$userId}).");
    }
}
```

- [ ] **Step 2: Run the seeder**

```bash
cd c:\wamp64\projects\hris
php artisan db:seed --class=PayrollTestSeeder
```

Expected:
```
Seeded payroll settings and components for user apangcatan (id: <N>).
```

- [ ] **Step 3: Verify the seeded data**

```bash
php artisan tinker --execute="
  \$u = DB::table('tdh_user.users')->where('username','apangcatan')->value('id');
  echo 'userid: ' . \$u . PHP_EOL;
  echo 'settings: ' . json_encode(DB::table('hris.payroll_settings')->where('userid',\$u)->first()) . PHP_EOL;
  echo 'components: ' . DB::table('hris.employee_payroll_components')->where('userid',\$u)->count() . ' rows' . PHP_EOL;
"
```

Expected output shows `settings` row with `basic_salary: 40000` and `components: 5 rows`.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/PayrollTestSeeder.php
git commit -m "feat: add PayrollTestSeeder for smoke test data"
```

---

## Final Verification

- [ ] **Run entire smoke suite**

```bash
npx playwright test tests/e2e/smoke/ --reporter=list
```

Expected: All 16 tests PASS.

- [ ] **Run in headed mode to visually confirm**

```bash
npx playwright test tests/e2e/smoke/ --headed --reporter=list
```

Watch the browser navigate through each page — confirm all pages render correctly.

- [ ] **Run only e2e tests (not PHP tests) to confirm no interference**

```bash
php artisan test tests/Feature/
```

Expected: PHP test suite unchanged — all passing.
