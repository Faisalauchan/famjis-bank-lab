// Admin bot: periodically logs into the bank as the branch admin and views
// the support messages page, so a stored XSS payload runs in an admin context.
// The admin session cookie is NOT HttpOnly, so the payload can read it.
const puppeteer = require('puppeteer');

const TARGET = process.env.TARGET || 'http://web';
const USER   = process.env.ADMIN_USER || 'admin';
const PASS   = process.env.ADMIN_PASS || 'S3cretAdmin_pw!';
const EVERY  = parseInt(process.env.INTERVAL_MS || '30000', 10);

const sleep = ms => new Promise(r => setTimeout(r, ms));

async function visit() {
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const page = await browser.newPage();
    await page.goto(`${TARGET}/login.php`, { waitUntil: 'networkidle2' });
    await page.type('input[name="username"]', USER);
    await page.type('input[name="password"]', PASS);
    await Promise.all([
      page.click('button[type="submit"]'),
      page.waitForNavigation({ waitUntil: 'networkidle2' }),
    ]);
    // view the messages where customer payloads are stored
    await page.goto(`${TARGET}/messages.php`, { waitUntil: 'networkidle2' });
    await sleep(4000); // give injected scripts time to fire
    console.log(new Date().toISOString(), 'admin visited messages.php');
  } catch (e) {
    console.error('bot error:', e.message);
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
