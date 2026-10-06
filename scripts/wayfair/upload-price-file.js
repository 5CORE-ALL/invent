/**
 * Partner Home price-file upload.
 * Invoked by WayfairBrowserUploadService with a JSON payload path.
 * Does not print credentials. Does not bypass MFA or CAPTCHA.
 */
const fs = require('fs');

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
    emit({ state: 'failed', retryable: false, message: 'Browser upload payload is missing.' });
    return;
  }

  const input = JSON.parse(fs.readFileSync(payloadPath, 'utf8'));
  let playwright;
  try {
    playwright = require(require('path').join(__dirname, 'node_modules', 'playwright'));
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
  });
  const page = await context.newPage();
  page.setDefaultTimeout(input.timeoutMs || 120000);
  const shot = async (label) => {
    const file = `${input.screenshotDir}/upload-${input.uploadId}-${label}-${Date.now()}.png`;
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
      const screenshot = await shot('auth');
      emit({
        state: 'auth_required',
        retryable: false,
        screenshot,
        message: 'Wayfair is asking for MFA or CAPTCHA. Unattended upload stopped. Approve a Wayfair authentication method before retrying.',
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
        const screenshot = await shot('no-login-button');
        emit({
          state: 'failed',
          retryable: false,
          screenshot,
          message: 'Partner Home login form was open, but the Log In control was not found.',
        });
        return;
      }
      await page.waitForURL((url) => !String(url).includes('/auth/realms/'), { timeout: 30000 }).catch(() => {});
      await page.waitForTimeout(2000);
    }

    input.password = '';

    if (page.url().includes('/auth/realms/')) {
      const screenshot = await shot('login-rejected');
      const alertText = await page.locator('.alert-error, .pf-c-alert, #input-error, .kc-feedback-text').innerText().catch(() => '');
      emit({
        state: 'failed',
        retryable: false,
        screenshot,
        message: scrub(alertText) || 'Partner Home did not leave the sign-in page after Log In.',
      });
      return;
    }

    if (await needsManualAuth(page)) {
      const screenshot = await shot('auth');
      emit({
        state: 'auth_required',
        retryable: false,
        screenshot,
        message: 'Wayfair requires manual authentication after login. MFA/CAPTCHA was not bypassed.',
      });
      return;
    }

    await page.goto(input.uploadUrl, { waitUntil: 'domcontentloaded' });
    if (page.url().includes('/auth/realms/')) {
      const screenshot = await shot('session-lost');
      emit({
        state: 'failed',
        retryable: true,
        screenshot,
        message: 'Partner Home sent the browser back to sign-in before the cost-change page opened.',
      });
      return;
    }
    const importLabel = (input.selectors && input.selectors.import_button) || 'Import Product Spreadsheet';
    const importButton = page.getByRole('button', { name: importLabel });
    await importButton.first().waitFor({ state: 'visible', timeout: 30000 }).catch(() => {});
    if (!(await importButton.count())) {
      const fileInput = page.locator((input.selectors && input.selectors.file) || 'input[type="file"]').first();
      if (!(await fileInput.count())) {
        const screenshot = await shot('no-file-input');
        emit({
          state: 'failed',
          retryable: false,
          screenshot,
          message: 'Import Product Spreadsheet was not found. Open the New Cost Change Select Products page and set WAYFAIR_UPLOAD_URL to that address.',
        });
        return;
      }
      await fileInput.setInputFiles(input.filePath);
    } else {
      await importButton.first().click();
      await attachSpreadsheet(page, input.filePath);
    }
    await page.waitForTimeout(2500);

    const bodyText = ((await page.locator('body').innerText().catch(() => '')) || '').toLowerCase();
    const needles = String(input.confirmationText || '')
      .split(',')
      .map((part) => part.trim().toLowerCase())
      .filter(Boolean);
    const onAdjustDetails = bodyText.includes('adjust details') && bodyText.includes('new base cost');
    const confirmed = onAdjustDetails || needles.some((needle) => bodyText.includes(needle));
    const referenceMatch = bodyText.match(/(?:reference|confirmation|batch)\s*[:#]?\s*([a-z0-9-]{4,})/i);

    if (confirmed) {
      emit({
        state: 'processing',
        reference: referenceMatch ? referenceMatch[1] : null,
        message: onAdjustDetails
          ? 'The spreadsheet was imported into the open cost change. Partner Home is on Adjust Details. Confirm Details and Review were not clicked.'
          : 'Wayfair accepted the file submission. Processing is not confirmed until a status page is configured.',
      });
      return;
    }

    const screenshot = await shot('unconfirmed');
    emit({
      state: 'failed',
      retryable: true,
      screenshot,
      message: 'Wayfair did not show an upload confirmation. Check WAYFAIR_UPLOAD_URL and the confirmation text.',
    });
  } catch (error) {
    const screenshot = await shot('error');
    const message = scrub(error && error.message ? error.message : 'Browser crashed');
    emit({
      state: 'failed',
      retryable: /timeout|closed|crash|net::/i.test(message),
      screenshot,
      message,
    });
  } finally {
    input.password = '';
    await browser.close();
  }
}

async function attachSpreadsheet(page, filePath) {
  await page.getByText('Upload Product Details').first().waitFor({ state: 'visible', timeout: 20000 });
  const fileInput = page.locator('input[type="file"]');
  if (await fileInput.count()) {
    await fileInput.first().setInputFiles(filePath);
  } else {
    const chooserPromise = page.waitForEvent('filechooser', { timeout: 20000 });
    await page.getByText(/click to browse/i).click();
    const chooser = await chooserPromise;
    await chooser.setFiles(filePath);
  }

  let panel = page.getByText('Maximum file size is 25 MB').first();
  let button = null;
  for (let depth = 0; depth < 8; depth++) {
    panel = panel.locator('xpath=..');
    const candidate = panel.getByRole('button', { name: /^Continue$/ });
    if (await candidate.count() === 1) {
      button = candidate;
      break;
    }
  }
  if (!button) {
    throw new Error('Upload dialog opened, but its Continue button was not found. The cost-change wizard was not advanced.');
  }
  for (let attempt = 0; attempt < 40; attempt++) {
    if (!(await button.isDisabled().catch(() => true))) {
      break;
    }
    await page.waitForTimeout(500);
  }
  if (await button.isDisabled()) {
    throw new Error('The spreadsheet was attached, but the upload dialog Continue button stayed disabled.');
  }
  await button.click();
  await page.getByText('Upload Product Details').first().waitFor({ state: 'hidden', timeout: 90000 }).catch(() => {});
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
