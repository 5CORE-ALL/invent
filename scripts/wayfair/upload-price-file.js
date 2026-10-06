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
    playwright = require('playwright');
  } catch (error) {
    emit({
      state: 'failed',
      retryable: false,
      message: 'Playwright is not installed. From scripts/wayfair run: npm install && npx playwright install chromium',
    });
    return;
  }

  const browser = await playwright.chromium.launch({ headless: true });
  const page = await browser.newPage();
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

    const passwordField = page.locator(input.selectors.password).first();
    if (await passwordField.count()) {
      const userField = page.locator(input.selectors.username).first();
      if (await userField.count()) {
        await userField.fill(input.username);
      }
      await passwordField.fill(input.password);
      const loginButton = page.locator(input.selectors.submit_login).first();
      if (await loginButton.count()) {
        await loginButton.click();
      }
      await page.waitForTimeout(2000);
    }

    input.password = '';

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
    const fileInput = page.locator(input.selectors.file).first();
    if (!(await fileInput.count())) {
      const screenshot = await shot('no-file-input');
      emit({
        state: 'failed',
        retryable: false,
        screenshot,
        message: 'Upload button not found. Set WAYFAIR_UPLOAD_URL and WAYFAIR_FILE_INPUT_SELECTOR to the Partner Home price upload page.',
      });
      return;
    }

    await fileInput.setInputFiles(input.filePath);
    const submit = page.locator(input.selectors.submit_upload).first();
    if (await submit.count()) {
      await submit.click();
    }
    await page.waitForTimeout(2500);

    const bodyText = ((await page.locator('body').innerText().catch(() => '')) || '').toLowerCase();
    const needles = String(input.confirmationText || '')
      .split(',')
      .map((part) => part.trim().toLowerCase())
      .filter(Boolean);
    const confirmed = needles.some((needle) => bodyText.includes(needle));
    const referenceMatch = bodyText.match(/(?:reference|confirmation|batch)\s*[:#]?\s*([a-z0-9-]{4,})/i);

    if (confirmed) {
      emit({
        state: 'processing',
        reference: referenceMatch ? referenceMatch[1] : null,
        message: 'Wayfair accepted the file submission. Processing is not confirmed until a status page is configured.',
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

async function needsManualAuth(page) {
  const text = ((await page.locator('body').innerText().catch(() => '')) || '').toLowerCase();
  return /captcha|one-time code|verification code|authenticator|two-factor|two-step|multi-factor/.test(text);
}

main().catch((error) => {
  emit({ state: 'failed', retryable: true, message: scrub(error && error.message) });
  process.exitCode = 1;
});
