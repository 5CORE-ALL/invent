/**
 * Partner Home "Export Product Spreadsheet" download.
 * Invoked by WayfairBaseCostPullService with a JSON payload path.
 * The exported workbook carries "Current Base Cost" for every product, which is Wayfair's real price.
 * Does not print credentials. Does not bypass MFA or CAPTCHA.
 */
const fs = require('fs');
const path = require('path');

function emit(payload) {
  process.stdout.write(JSON.stringify(payload) + '\n');
}

function scrub(text) {
  return String(text || '')
    .replace(/(password|passwd|client_secret|access_token|authorization|cookie)\s*[=:]\s*\S+/gi, '$1=[redacted]')
    .slice(0, 500);
}

async function main() {
  const payloadPath = process.argv[2];
  if (!payloadPath || !fs.existsSync(payloadPath)) {
    emit({ state: 'failed', retryable: false, message: 'Export payload is missing.' });
    return;
  }

  const input = JSON.parse(fs.readFileSync(payloadPath, 'utf8'));
  let playwright;
  try {
    playwright = require(path.join(__dirname, 'node_modules', 'playwright'));
  } catch (error) {
    emit({
      state: 'failed',
      retryable: false,
      message: 'Playwright is not installed. From scripts/wayfair run: npm install && npx playwright install chromium',
    });
    return;
  }

  const browser = await playwright.chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    acceptDownloads: true,
  });
  const page = await context.newPage();
  page.setDefaultTimeout(input.timeoutMs || 120000);
  const shot = async (label) => {
    const file = `${input.screenshotDir}/export-${label}-${Date.now()}.png`;
    try {
      await page.screenshot({ path: file, fullPage: true });
      return file;
    } catch (error) {
      return null;
    }
  };

  try {
    await page.goto(input.portalUrl, { waitUntil: 'domcontentloaded' });
    const passwordField = page.locator(input.selectors.password).first();
    await passwordField.waitFor({ state: 'visible', timeout: 30000 }).catch(() => {});

    if (await needsManualAuth(page)) {
      emit({
        state: 'auth_required',
        retryable: false,
        screenshot: await shot('auth'),
        message: 'Wayfair is asking for MFA or CAPTCHA. Unattended export stopped.',
      });
      return;
    }

    if (await passwordField.count()) {
      const userField = page.locator(input.selectors.username).first();
      if (await userField.count()) {
        await userField.fill(input.username);
      }
      await passwordField.fill(input.password);
      const clicked = await clickLogin(page, input.selectors.submit_login);
      if (!clicked) {
        emit({
          state: 'failed',
          retryable: false,
          screenshot: await shot('no-login-button'),
          message: 'Partner Home login form was open, but the Log In control was not found.',
        });
        return;
      }
      await page.waitForURL((url) => !String(url).includes('/auth/realms/'), { timeout: 30000 }).catch(() => {});
      await page.waitForTimeout(2000);
    }

    input.password = '';

    if (page.url().includes('/auth/realms/')) {
      const alertText = await page.locator('.alert-error, .pf-c-alert, #input-error, .kc-feedback-text').innerText().catch(() => '');
      emit({
        state: 'failed',
        retryable: false,
        screenshot: await shot('login-rejected'),
        message: scrub(alertText) || 'Partner Home did not leave the sign-in page after Log In.',
      });
      return;
    }

    if (await needsManualAuth(page)) {
      emit({
        state: 'auth_required',
        retryable: false,
        screenshot: await shot('auth'),
        message: 'Wayfair requires manual authentication after login. MFA/CAPTCHA was not bypassed.',
      });
      return;
    }

    await page.goto(input.exportUrl, { waitUntil: 'domcontentloaded' });
    if (page.url().includes('/auth/realms/')) {
      emit({
        state: 'failed',
        retryable: true,
        screenshot: await shot('session-lost'),
        message: 'Partner Home sent the browser back to sign-in before the export page opened.',
      });
      return;
    }

    const label = input.exportLabel || 'Export Product Spreadsheet';
    const trigger = await findExportControl(page, label);
    if (!trigger) {
      emit({
        state: 'failed',
        retryable: false,
        screenshot: await shot('no-export-control'),
        message: `"${label}" was not found on the Partner Home page. Set WAYFAIR_EXPORT_URL to the page that has it (WAYFAIR_EXPORT_LABEL if the text differs).`,
      });
      return;
    }

    let download = await clickAndWaitForDownload(page, trigger, 45000);
    if (!download) {
      // Some Partner Home pages open a confirmation dialog with its own Export / Download button.
      const confirm = page.locator('[role="dialog"] button, .modal button')
        .filter({ hasText: /^(export|download|continue|confirm)$/i }).first();
      if (await confirm.count()) {
        download = await clickAndWaitForDownload(page, confirm, input.timeoutMs || 120000);
      }
    }
    if (!download) {
      emit({
        state: 'failed',
        retryable: true,
        screenshot: await shot('no-download'),
        message: 'The export control was clicked but no file download started.',
      });
      return;
    }

    fs.mkdirSync(path.dirname(input.savePath), { recursive: true });
    await download.saveAs(input.savePath);
    const size = fs.existsSync(input.savePath) ? fs.statSync(input.savePath).size : 0;
    if (size < 1024) {
      emit({
        state: 'failed',
        retryable: true,
        screenshot: await shot('empty-download'),
        message: `The downloaded export is only ${size} bytes.`,
      });
      return;
    }

    emit({ state: 'success', path: input.savePath, bytes: size, message: 'Partner Home export downloaded.' });
  } catch (error) {
    const message = scrub(error && error.message ? error.message : 'Browser crashed');
    emit({
      state: 'failed',
      retryable: /timeout|closed|crash|net::/i.test(message),
      screenshot: await shot('error'),
      message,
    });
  } finally {
    input.password = '';
    await browser.close();
  }
}

async function findExportControl(page, label) {
  const exact = new RegExp(label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'i');
  const candidates = [
    page.getByRole('button', { name: exact }),
    page.getByRole('link', { name: exact }),
    page.getByRole('menuitem', { name: exact }),
    page.getByText(exact),
  ];
  for (const candidate of candidates) {
    await candidate.first().waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
    if (await candidate.count()) {
      return candidate.first();
    }
  }
  return null;
}

async function clickAndWaitForDownload(page, locator, timeoutMs) {
  try {
    const [download] = await Promise.all([
      page.waitForEvent('download', { timeout: timeoutMs }),
      locator.click(),
    ]);
    return download;
  } catch (error) {
    return null;
  }
}

async function clickLogin(page, selector) {
  const candidates = ['#kc-login', 'input[name="login"]', selector, 'input[type="submit"]'].filter(Boolean);
  for (const candidate of candidates) {
    const locator = page.locator(candidate).first();
    if (await locator.count()) {
      await locator.click();
      return true;
    }
  }
  const byRole = page.getByRole('button', { name: /^log in$/i }).first();
  if (await byRole.count()) {
    await byRole.click();
    return true;
  }
  return false;
}

async function needsManualAuth(page) {
  const text = ((await page.locator('body').innerText().catch(() => '')) || '').toLowerCase();
  return /captcha|one-time code|verification code|authenticator|two-factor|two-step|multi-factor/.test(text);
}

main().catch((error) => {
  emit({ state: 'failed', retryable: true, message: scrub(error && error.message) });
  process.exitCode = 1;
});
