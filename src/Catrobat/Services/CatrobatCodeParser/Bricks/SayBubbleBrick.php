<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Bricks;

use App\Catrobat\Services\CatrobatCodeParser\Constants;
use App\Catrobat\Services\CatrobatCodeParser\FormulaResolver;

/**
 * Class SayBubbleBrick
 * @package App\Catrobat\Services\CatrobatCodeParser\Bricks
 */
class SayBubbleBrick extends Brick
{
  /**
   *
   */
  protected function create()
  {
    $this->type = Constants::SAY_BUBBLE_BRICK;
    $this->caption = "Say \""
      . FormulaResolver::resolve($this->brick_xml_properties->formulaList)[Constants::STRING_FORMULA] . "\"";

    $this->setImgFile(Constants::LOOKS_BRICK_IMG);
  }
}