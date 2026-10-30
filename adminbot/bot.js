// Admin bot: periodically logs into the bank as the branch admin and views
// the support messages page, so a stored XSS payload runs in an admin context.
// The admin session cookie is NOT HttpOnly, so the payload can read it.
const puppeteer = require('puppeteer');

const TARGET = process.env.TARGET || 'http://web';
const USER   = process.env.ADMIN_USER || 'admin';
const PASS   = process.env.ADMIN_PASS || 'S3cretAdmin_pw!';
const EVERY  = parseInt(process.env.INTERVAL_MS || '30000', 10);

const sleep = ms => new Promise(r => setTimeout(r, ms));

// Log in as the branch admin and confirm we actually hold an admin session.
// Returns true only when the post-login page is NOT the login form and shows
// the admin UI, so a silently-failing login never looks like a successful read.
async function loginAsAdmin(page) {
  await page.goto(`${TARGET}/login.php`, { waitUntil: 'networkidle2' });
  await page.type('input[name="username"]', USER);
  await page.type('input[name="password"]', PASS);
  await Promise.all([
    page.click('button[type="submit"]'),
    page.waitForNavigation({ waitUntil: 'networkidle2' }),
  ]);

  // A failed login re-renders login.php with an error; a success lands on
  // dashboard.php and renders the "Administrator" role badge in the header.
  if (/\/login\.php(\?|$)/.test(page.url())) {
    throw new Error('login rejected (still on login.php) — check ADMIN_USER/ADMIN_PASS');
  }
  const isAdmin = await page.evaluate(() =>
    /Administrator/i.test(document.querySelector('.role')?.textContent || ''));
  if (!isAdmin) {
    throw new Error('logged in but not as admin (no Administrator badge)');
  }

  // Confirm the admin session cookie is present (the one the XSS task steals).
  const cookies = await page.cookies();
  const hasSession = cookies.some(c => c.name === 'session');
  if (!hasSession) throw new Error('no "session" cookie after login');
}

async function visit() {
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    // Stored XSS payloads may open alert()/confirm()/prompt() dialogs, which
    // otherwise block headless Chromium forever. Auto-dismiss so the bot keeps
    // running (a real admin browser would just show-and-close the dialog).
    page.on('dialog', d => d.dismiss().catch(() => {}));

    await loginAsAdmin(page);

    // Using that admin session, read the support thread where customer payloads
    // are stored. Use 'domcontentloaded' (not 'networkidle2') — a payload that
    // opens a slow or hanging connection (image beacon, fetch to an unreachable
    // host) would keep the network from ever going idle and stall the visit.
    await page.goto(`${TARGET}/messages.php`, { waitUntil: 'domcontentloaded' });

    // If the session lapsed, messages.php redirects to the login form; treat
    // that as a failed read rather than silently viewing nothing.
    if (/\/login\.php(\?|$)/.test(page.url())) {
      throw new Error('messages.php redirected to login — admin session not active');
    }

    // Report how many support messages the admin actually read this cycle, so a
    // broken read is visible in the logs instead of looking like a quiet success.
    const count = await page.evaluate(() =>
      document.querySelectorAll('.thread .bubble').length);
    await sleep(4000); // give injected scripts time to fire
    console.log(new Date().toISOString(),
      `admin read ${count} support message(s) on messages.php`);
  } catch (e) {
    console.error(new Date().toISOString(), 'bot error:', e.message);
  } finally {
    await browser.close();
  }
}

(async () => {
  // wait for the web service to come up
  await sleep(8000);
  while (true) {
    await visit();
    await sleep(EVERY);
  }
})();
