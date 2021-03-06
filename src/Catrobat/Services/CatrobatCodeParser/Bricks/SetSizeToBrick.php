<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Bricks;

use App\Catrobat\Services\CatrobatCodeParser\Constants;
use App\Catrobat\Services\CatrobatCodeParser\FormulaResolver;

/**
 * Class SetSizeToBrick
 * @package App\Catrobat\Services\CatrobatCodeParser\Bricks
 */
class SetSizeToBrick extends Brick
{
  /**
   *
   */
  protected function create()
  {
    $this->type = Constants::SET_SIZE_TO_BRICK;
    $this->caption = "Set size to "
      . FormulaResolver::resolve($this->brick_xml_properties->formulaList)[Constants::SIZE_FORMULA] . "%";

    $this->setImgFile(Constants::LOOKS_BRICK_IMG);
  }
}