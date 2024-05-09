<?php

namespace Drupal\drimage_improved\Controller;

use Drupal\imageapi_optimize\ImageStyleWithPipelineListBuilder as ImageStyleWithPipelineListBuilderBase;

/**
 * Alters the imageapi_optimize image style entity listing.
 */
class ImageStyleWithPipelineListBuilder extends ImageStyleWithPipelineListBuilderBase {

  use ImageStyleListBuilderTrait;

}
