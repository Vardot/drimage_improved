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

## Custom step

- `Then the image "<selector>" should be loaded [within N seconds]` — passes only when the
  matched `<img>` reports `complete` and `naturalWidth > 0`, i.e. the derivative decoded.
  (`tests/step-definitions/drimage.steps.js`.)

## Coverage gaps (honest)

- **image_widget_crop (IWC) crop-type derivatives** are not covered here: the base site this
  suite runs on ships `focal_point` but not `image_widget_crop`. Add an IWC scenario on a base
  that enables it.
- **s3fs** derivative path (`drimage_s3fs`) is out of scope (no S3 backend in the test base).

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
| `drimage_improved_test` | Base setup: a "Drimage Test" content type with a multi-value image field rendered through the Drimage formatter, a sample "Drimage test page" (image included) as default content, set as the front page. |
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
