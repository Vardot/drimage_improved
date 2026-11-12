# Drimage - browser-based functional test suite

Front-end acceptance coverage for the Drimage responsive image formatter, driven through a
real browser (Playwright + Cucumber-js; the shared step definitions come from the
`@vardot/varbase-e2e` npm package).

## REASONS

- **Requirements:** A Drimage-formatted image field renders as a responsive, lazy image; the
  generated derivative actually loads; the settings form is reachable and its values persist.
- **Entities:** The `drimage_improved` field formatter output (`.drimage` container,
  `img.drimage-image`, `data-drimage_improved`, `<noscript>` fallback, WebP `<source>`); the
  on-the-fly derivative at `/styles/drimage_improved_*`; the settings form at
  `/admin/config/media/drimage_improved`.
- **Approach:** Drive the real front end as an anonymous visitor for rendering + derivative
  generation; log in as `webmaster` for the settings form. Assert what the visitor sees and the
  behavior provided, never theme markup or page reachability.
- **Operations (one scenario each):** responsive container + image render · no-JS fallback ·
  WebP source · focal-point derivative decodes · settings form opens · a changed setting persists.
- **Norms:** Anonymous read for the front end; `administer image styles` for the settings form.
- **Safeguards:** The focal-point scenario fails red if the derivative does not generate
  (naturalWidth stays 0) — the regression guard for the focal off-by-one fixed in
  drimage_improved 1.0.11 (issue #3618235).

## Steps

Every Drimage assertion this suite uses ships in the step library, in the Drimage Improved
step pack (`@vardot/varbase-e2e` 2.0.4 or later,
`node_modules/@vardot/varbase-e2e/docs/steps/drupal-drimage-improved.md`). The steps carry no
CSS selectors: the pack knows the formatter markup and the derivative path, so a scenario says
what a visitor sees.

All the images on the page:

- `Then the drimage images should be rendered [within N seconds]`
- `Then the drimage images should be loaded [within N seconds]`
- `Then the drimage images should offer webp [within N seconds]`
- `Then the drimage images should have a noscript fallback [within N seconds]`
- `Then the drimage images should use lazy loading [within N seconds]`
- `Then the drimage images should be sized for the viewport [within N seconds]`
- `Then no drimage image should be broken [within N seconds]`

One image, named the way a person points at a picture — by alt text, title, caption, order in
the page or number, never by a selector:

- `Then the drimage image "<alt text or title>" should be loaded [within N seconds]`
- `Then the drimage image titled "<title>" should be loaded`
- `Then the drimage image captioned "<caption>" should be loaded`
- `Then the drimage image with the alt text "<alt>" should be loaded`
- `Then the first drimage image should be loaded` / `the 3rd` / `the last`
- `Then the drimage image number 3 should be loaded`
- `Then the <target> drimage image should still be a placeholder`
- `Then the <target> drimage image should be in a figure`

The regression guard for the focal off-by-one fixed in drimage_improved 1.0.11
(issue #3618235) is `the drimage images should be loaded`: it passes only when an `<img>`
reports `complete` with `naturalWidth > 0` on a derivative under `/styles/drimage_improved_*`,
so it fails red when the derivative does not generate.

## Image generation in progress (issue #3637295)

`10-09-drimage-generation-in-progress.feature` covers several visitors asking for the same new
image size at once. Core answers "Image generation in progress" at once while another request
holds its lock, and an image tag never retries. Drimage takes a lock of its own around
generation, the way core uses its lock API: a second request waits for it
(`LockBackendInterface::wait()`, up to 2 seconds) and delivers the image the first one made, and
answers a plain 503 that is never cached only when generation takes longer than that.

The race is made deterministic by the hidden testing module
`tests/modules/drimage_improved_lock_test` (enabled by the `drimage_improved_test` recipe): it
removes a derivative and writes Drimage's generation lock (`DrimageManager::generationLockName()`)
into the `semaphore` table, so the lock outlives
the request that took it and expires on its own, exactly like a slow generation in another PHP
process. Its route needs `administer image styles`. Drupal discovers modules under `tests/` only with
`$settings['extension_discovery_scan_tests'] = TRUE;` in `settings.php`, which the CI job sets.

Its steps are local (`tests/step-definitions/drimage-generation-lock.steps.js`), because they
must keep the FIRST response a visitor got; the library's status step re-requests the URL, and
by then the derivative exists:

- `Given another request is generating the drimage image "<uri>" for N seconds`
- `Given the drimage image "<uri>" has not been generated yet`
- `When a visitor requests that drimage image`
- `When N visitors request that drimage image at the same time`
- `Then every visitor should get the drimage image` / `Then the visitor should get the drimage image`
- `Then the visitor should be asked to try the drimage image again shortly`
- `Then the drimage image request should have waited at least N seconds`

## Coverage gaps (honest)

- **image_widget_crop (IWC) crop-type derivatives** are not covered here: the base site this
  suite runs on ships `focal_point` but not `image_widget_crop`. Add an IWC scenario on a base
  that enables it.
- **s3fs** (`drimage_s3fs`): no browser scenarios (no S3 backend on the runner); its subscriber,
  formatter and theme hook are covered by Kernel tests in `modules/drimage_s3fs/tests/src/Kernel`,
  with `drupal/s3fs` as a `require-dev` dependency.

## Run

```bash
FEATURES="tests/features/drimage/**/*.feature" npx cucumber-js --config cucumber.js
# one scenario:
npx cucumber-js --config cucumber.js tests/features/drimage/10-02-drimage-focal-point.feature
```

## Test setup recipes

`tests/recipes/` holds hidden, testing-only Drupal recipes (`type: Testing`, never for production):

| Recipe | Adds |
|---|---|
| `drimage_improved_test` | Base setup: a "Drimage Test" content type with a multi-value image field rendered through the Drimage formatter, a sample "Drimage test page" (image included) as default content, set as the front page, and the hidden `drimage_improved_lock_test` module (needs `extension_discovery_scan_tests`). |
| `drimage_improved_test_focal_point` | Base + Focal Point. |
| `drimage_improved_test_image_widget_crop` | Base + Image Widget Crop with a `square` crop type. |
| `drimage_improved_test_webp_off` | Base with core WebP support disabled. |

Apply one with core's script from the Drupal root (Drush re-discovers commands right after a
module install and fails on this module's legacy `drush.services.yml`):

```bash
php core/scripts/drupal recipe /abs/path/to/modules/contrib/drimage_improved/tests/recipes/drimage_improved_test_focal_point
```

## Scenario tags per setup

Scenarios carry setup tags so each CI setup runs only what applies to it:

| Tag | Meaning | Runs in |
|---|---|---|
| (none) | Applies to every setup. | all |
| `@focal` | Needs Focal Point (focal derivative loads; focal style-name path). | focal_point |
| `@iwc` | Needs Image Widget Crop + the `square` crop type. | image_widget_crop |
| `@webp-on` | Needs core WebP enabled (default). | base, focal_point, image_widget_crop |
| `@webp-off` | Needs core WebP disabled. | webp_off |

Every wait budget is capped at 2 seconds: the module must deliver images and pages within 1 to 2 seconds.

## CI stages

`build` -> `validate` -> `unit` -> `test` -> `report`:

- `unit`: `phpunit` runs the Kernel tests (`tests/src/Kernel`), the back-end coverage.
- `test`: `functional-acceptance` is a matrix of four jobs, one per setup above. Each builds a plain
  Drupal site, applies its recipe, serves it and drives this suite in Chromium with the matching tag
  expression, writing `tests/reports/cucumber_report_<setup>.json`.
- `report`: `create-reports` merges every setup's Cucumber JSON into one HTML + PDF report.

Run the whole pipeline locally with gitlab-ci-local from the module directory
(`gitlab-ci-local --list` shows the jobs; `gitlab-ci-local --job <name>` runs one).
