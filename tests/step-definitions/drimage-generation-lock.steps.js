'use strict';

// Steps for the "image generation in progress" scenarios (issue #3637295).
//
// They need the hidden testing module drimage_improved_lock_test (applied by the
// drimage_improved_test recipe), which removes a derivative and holds its image
// style lock the way a slow generation in another PHP process would.
//
// The library's "the response status code should be N" re-requests the current
// URL, and by then the derivative exists, so it would report 200 even when the
// visitor got a broken image. These steps keep the FIRST response a visitor got.

const { Given, When, Then } = require('@cucumber/cucumber');
const assert = require('assert');

/**
 * Logs in as the webmaster and waits until Drupal has redirected away from the form.
 */
async function logInAsWebmaster(world) {
  const user = world.parameters.users.webmaster;
  await world.page.goto(`${world.launchUrl}/user/login`, { waitUntil: 'domcontentloaded' });
  if (!(await world.page.$('#edit-name'))) {
    // Already logged in: Drupal sends a logged-in user away from the form.
    return;
  }
  await world.page.fill('#edit-name', user.username);
  await world.page.fill('#edit-pass', user.password);
  await Promise.all([
    world.page.waitForURL((u) => !/\/user\/login/.test(u.pathname), { timeout: 15000 }),
    world.page.evaluate(() => document.querySelector('#edit-submit').click()),
  ]);
}

/**
 * Asks the testing module to remove a derivative and hold its lock.
 */
async function holdLock(world, uri, seconds) {
  const query = new URLSearchParams({ uri, seconds: String(seconds) });
  const url = `${world.launchUrl}/drimage-improved-lock-test/hold?${query}`;
  let response = await world.page.request.get(url, { failOnStatusCode: false });
  if (response.status() === 403) {
    // The library's login step does not wait for the session to exist, so the
    // cookie may not be there yet: log in and wait for it, then ask once more.
    await logInAsWebmaster(world);
    response = await world.page.request.get(url, { failOnStatusCode: false });
  }
  const body = await response.text();
  assert.strictEqual(
    response.status(),
    200,
    `The lock test module answered ${response.status()}: ${body.slice(0, 300)}. ` +
      'Log in as a user who may administer image styles, and apply the drimage_improved_test recipe.',
  );
  world.drimageLock = JSON.parse(body);
  return world.drimageLock;
}

/**
 * Holds the generation lock of a derivative for a number of seconds.
 *
 * Example #1: Given another request is generating the drimage image "public://drimage-test/drimage-sample.png" for 1 second
 * Example #2: Given another request is generating the drimage image "public://drimage-test/drimage-sample.png" for 30 seconds
 */
Given(
  /^another request is generating the drimage image "([^"]+)" for (\d+(?:\.\d+)?) seconds?$/,
  async function (uri, seconds) {
    await holdLock(this, uri, parseFloat(seconds));
  },
);

/**
 * Removes a derivative without holding its lock, so the next request generates it.
 *
 * Example #1: Given the drimage image "public://drimage-test/drimage-sample.png" has not been generated yet
 */
Given(/^the drimage image "([^"]+)" has not been generated yet$/, async function (uri) {
  await holdLock(this, uri, 0);
});

/**
 * Opens the derivative as an anonymous visitor and keeps the first response.
 *
 * Example #1: When a visitor requests that drimage image
 */
When(/^a visitor requests that drimage image$/, async function () {
  assert.ok(this.drimageLock && this.drimageLock.url, 'No drimage image was prepared in this scenario.');
  await this.context.clearCookies();
  const started = Date.now();
  const response = await this.page.goto(`${this.launchUrl}${this.drimageLock.url}`, { waitUntil: 'load' });
  this.drimageResponses = [{
    status: response.status(),
    headers: response.headers(),
    body: await response.text().catch(() => ''),
    elapsed: Date.now() - started,
  }];
  // Let the library's header steps read this response.
  this._lastResponseHeaders = response.headers();
  this._lastResponseStatus = response.status();
});

/**
 * Requests the derivative from several visitors at the same moment.
 *
 * Example #1: When 6 visitors request that drimage image at the same time
 */
When(/^(\d+) visitors request that drimage image at the same time$/, async function (count) {
  assert.ok(this.drimageLock && this.drimageLock.url, 'No drimage image was prepared in this scenario.');
  this.drimageResponses = await burst(this, parseInt(count, 10));
});

/**
 * Stress: resets the derivative, then requests it from several visitors at
 * once, round after round. With "while another request generates it for N
 * seconds" every round also holds the lock, so every visitor goes through the
 * wait-and-retry path at the same time.
 *
 * Example #1: When 8 visitors request the drimage image "public://drimage-test/drimage-sample.png" at the same time, 10 times over
 * Example #2: When 8 visitors request the drimage image "public://drimage-test/drimage-sample.png" at the same time, 5 times over, while another request generates it for 1 second
 */
When(
  /^(\d+) visitors request the drimage image "([^"]+)" at the same time, (\d+) times over(?:, while another request generates it for (\d+(?:\.\d+)?) seconds?)?$/,
  { timeout: 120000 },
  async function (count, uri, rounds, seconds) {
    this.drimageResponses = [];
    for (let round = 0; round < parseInt(rounds, 10); round++) {
      await holdLock(this, uri, seconds ? parseFloat(seconds) : 0);
      this.drimageResponses.push(...await burst(this, parseInt(count, 10)));
    }
  },
);

/**
 * Requests the prepared derivative from several anonymous visitors at once.
 *
 * The visitors use a browser context of their own, with no cookies, so the
 * administrator session that prepares each round is left alone.
 */
async function burst(world, count) {
  const url = `${world.launchUrl}${world.drimageLock.url}`;
  const visitors = await world.page.context().browser().newContext({ ignoreHTTPSErrors: true });
  try {
    const started = Date.now();
    return await Promise.all(
      Array.from({ length: count }, async () => {
        const response = await visitors.request.get(url, { failOnStatusCode: false, maxRedirects: 0, timeout: 30000 });
        const bytes = await response.body();
        return {
          status: response.status(),
          headers: response.headers(),
          bytes: bytes.length,
          body: /^text\//.test(response.headers()['content-type'] || '') ? bytes.toString('utf8') : '',
          elapsed: Date.now() - started,
        };
      }),
    );
  }
  finally {
    await visitors.close();
  }
}

function describe(responses) {
  return responses
    .map((r, i) => `#${i + 1}: HTTP ${r.status} ${r.headers['content-type'] || '(no content type)'}` +
      `${r.bytes === undefined ? '' : `, ${r.bytes} bytes`} after ${r.elapsed} ms ${r.body.slice(0, 80)}`)
    .join('\n');
}

/**
 * Every visitor got the image itself.
 *
 * Example #1: Then every visitor should get the drimage image
 * Example #2: Then the visitor should get the drimage image
 */
Then(/^(?:every|the) visitor should get the drimage image$/, async function () {
  const responses = this.drimageResponses || [];
  assert.ok(responses.length > 0, 'No drimage image was requested in this scenario.');
  const broken = responses.filter((r) => r.status !== 200 || !/^image\//.test(r.headers['content-type'] || ''));
  assert.strictEqual(
    broken.length,
    0,
    `${broken.length} of ${responses.length} visitors did not get the image:\n${describe(broken.slice(0, 20))}`,
  );
});

/**
 * The visitor was told to try again, with a clean response rather than an error page.
 *
 * Example #1: Then the visitor should be asked to try the drimage image again shortly
 */
Then(/^the visitor should be asked to try the drimage image again shortly$/, async function () {
  const responses = this.drimageResponses || [];
  assert.strictEqual(responses.length, 1, 'Expected exactly one drimage request in this scenario.');
  const [r] = responses;
  assert.strictEqual(r.status, 503, `Expected HTTP 503, got:\n${describe(responses)}`);
  assert.strictEqual(r.headers['retry-after'], '3', `Expected "Retry-After: 3", got:\n${JSON.stringify(r.headers)}`);
  assert.ok(
    /no-store/.test(r.headers['cache-control'] || ''),
    `A "try again" answer must never be cached, got "Cache-Control: ${r.headers['cache-control']}".`,
  );
  assert.ok(
    /Image generation in progress/.test(r.body) && !/<html/i.test(r.body),
    `Expected the plain "Image generation in progress" message, not an error page:\n${r.body.slice(0, 300)}`,
  );
});

/**
 * The visitor's request waited for the other request before answering.
 *
 * Example #1: And the drimage image request should have waited at least 1 second
 */
Then(/^the drimage image request should have waited at least (\d+(?:\.\d+)?) seconds?$/, async function (seconds) {
  const [r] = this.drimageResponses || [];
  assert.ok(r, 'No drimage image was requested in this scenario.');
  assert.ok(r.elapsed >= parseFloat(seconds) * 1000, `The request answered after ${r.elapsed} ms.`);
});
