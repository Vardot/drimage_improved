const { Then } = require('@cucumber/cucumber');
const assert = require('assert');

/**
 * Assert that at least one <img> matching the selector decoded a real bitmap.
 *
 * A Drimage placeholder that never swaps, or a derivative the server failed to
 * generate (a 4xx/5xx on the /styles/drimage_improved_* request), leaves the
 * image broken with naturalWidth === 0. This step waits until at least one
 * matching image reports complete with a non-zero natural width, so it fails
 * red when the derivatives do not generate. "At least one" keeps it stable on
 * pages where some matches sit in a not-yet-active carousel slide that lazy
 * loading has not reached.
 *
 * Example #1: Then the image "img.drimage-image" should be loaded
 * Example #2: Then the image ".drimage img.drimage-image" should be loaded
 * Example #3: Then the image "img.drimage-image" should be loaded within 20 seconds
 * Example #4: Then the image ".field--name-field-media-image img" should be loaded within 15 seconds
 * Example #5: Then the image "picture img.drimage-image" should be loaded
 */
Then(/^the image "([^"]*)" should be loaded(?: within (\d+) seconds?)?$/, async function (selector, seconds) {
  const timeout = (seconds ? parseInt(seconds, 10) : 2) * 1000;
  await this.page.locator(selector).first().waitFor({ state: 'attached', timeout });
  try {
    await this.page.waitForFunction(
      (sel) => Array.from(document.querySelectorAll(sel)).some((img) => img.complete && img.naturalWidth > 0),
      selector,
      { timeout, polling: 200 },
    );
  } catch (error) {
    const widths = await this.page
      .locator(selector)
      .evaluateAll((imgs) => imgs.map((img) => img.naturalWidth))
      .catch(() => []);
    assert.fail(
      `No image matching "${selector}" decoded a bitmap (naturalWidth values: [${widths.join(', ')}]); the Drimage derivatives are broken or were not generated.`,
    );
  }
});
