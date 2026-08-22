# Dynamic Responsive Image (Drimage) - Improved

![Dynamic Responsive Image (Drimage) - Improved](logo.png)

Responsive images without configuring responsive image styles. The browser measures the
space an image gets, and the derivative is generated at that width.

## Table of contents

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Recommended modules](#recommended-modules)
- [Installation](#installation)
- [Configuration](#configuration)
- [Documentation](#documentation)
- [Troubleshooting](#troubleshooting)
- [FAQ](#faq)
- [Maintainers](#maintainers)

## Introduction

Dynamic Responsive Image (`drimage_improved`) is an alternative to the Responsive Image
module in Drupal core. It takes the pain out of configuring and maintaining responsive
image styles, by not needing any configuration.

- Project page: https://www.drupal.org/project/drimage_improved
- Issue queue: https://www.drupal.org/project/issues/drimage_improved

## Requirements

- Image (Drupal core)

## Recommended modules

None required. These integrate when installed:

| Module | What it adds |
| --- | --- |
| [Focal Point](https://www.drupal.org/project/focal_point) | Crops keep the editor's chosen focal point. |
| [Image Widget Crop](https://www.drupal.org/project/image_widget_crop) | Editors crop per crop type, before scaling. |
| [Automated Crop](https://www.drupal.org/project/automated_crop) | Automatic crop calculation. |
| [ImageAPI Optimize WebP](https://www.drupal.org/project/imageapi_optimize_webp) | WebP without core WebP support. |
| [S3 File System](https://www.drupal.org/project/s3fs) | Derivatives on S3, with the bundled Drimage S3fs submodule. |
| [Stage File Proxy](https://www.drupal.org/project/stage_file_proxy) | Fetches missing originals while developing. |

## Installation

```bash
composer require drupal/drimage_improved
drush en drimage_improved
```

Consider uninstalling Responsive Image, since this module replaces it.

## Configuration

1. Configure the Image module the way you want it.
2. On the **Manage display** screen of the entity holding the image field, set the format
   to **Dynamic Responsive Image**. Optionally choose a fallback image style and link the
   image.
3. To limit how many image styles are created, and so how much disk space is used, raise
   the threshold at `/admin/config/media/drimage_improved`. Images are up- and downscaled
   in the browser to cover the difference.

If your content references **media** rather than holding an image field directly, the
formatter belongs on the media type's display. See the documentation.

## Documentation

Full documentation is in the [`docs/`](docs/index.md) folder, split per audience:
setting up image fields and media references, the settings, the image handling modes,
WebP, and an architecture and testing overview for developers.

It is also published with GitLab Pages. This project serves Pages from a generated
domain, so the readable address redirects there:
https://project.pages.drupalcode.org/drimage_improved/
The exact domain is listed on the repository under **Deploy > Pages**.

## Troubleshooting

Visit the [issue queue](https://www.drupal.org/project/issues/drimage_improved).

## FAQ

See [`docs/faq.md`](docs/faq.md), or the [project page](https://www.drupal.org/project/drimage_improved).

## Maintainers

- [josebc](https://www.drupal.org/u/josebc)
- Mohammed J. Razem ([mohammed j. razem](https://www.drupal.org/u/mohammed-j-razem))
- Omar Alahmed ([omar alahmed](https://www.drupal.org/u/omar-alahmed))
- Rajab Natshah ([rajab natshah](https://www.drupal.org/u/rajab-natshah))

### Upstream

This module is a fork of [Drimage](https://www.drupal.org/project/drimage), maintained by
Wesley S. ([weseze](https://www.drupal.org/u/weseze)) and Jurgen R.
([JurgenR](https://www.drupal.org/u/jurgenr)), and sponsored by weseze and
[O2 Agency](http://www.o2agency.be/).
