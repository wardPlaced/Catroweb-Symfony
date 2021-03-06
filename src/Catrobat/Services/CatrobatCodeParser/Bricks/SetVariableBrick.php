<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Bricks;

use App\Catrobat\Services\CatrobatCodeParser\Constants;
use App\Catrobat\Services\CatrobatCodeParser\FormulaResolver;

/**
 * Class SetVariableBrick
 * @package App\Catrobat\Services\CatrobatCodeParser\Bricks
 */
class SetVariableBrick extends Brick
{
  /**
   *
   */
  protected function create()
  {
    $this->type = Constants::SET_VARIABLE_BRICK;

    $variable = null;
    if ($this->brick_xml_properties->userVariable[Constants::REFERENCE_ATTRIBUTE] != null)
    {
      $variable = (string)$this->brick_xml_properties->userVariable
        ->xpath($this->brick_xml_properties->userVariable[Constants::REFERENCE_ATTRIBUTE])[0];
    }
    else
    {
      $variable = (string)$this->brick_xml_properties->userVariable;
    }
    $this->caption = "Set variable " . $variable . " to "
      . FormulaResolver::resolve($this->brick_xml_properties->formulaList)[Constants::VARIABLE_FORMULA];

    $this->setImgFile(Constants::DATA_BRICK_IMG);
  }
}