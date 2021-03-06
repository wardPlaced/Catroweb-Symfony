<?php

namespace App\Catrobat\Services\CatrobatCodeParser\Bricks;

use App\Catrobat\Services\CatrobatCodeParser\Constants;
use App\Catrobat\Services\CatrobatCodeParser\FormulaResolver;

/**
 * Class AskBrick
 * @package App\Catrobat\Services\CatrobatCodeParser\Bricks
 */
class AskBrick extends Brick
{
  /**
   * @return mixed|void
   */
  protected function create()
  {
    $this->type = Constants::ASK_BRICK;

    $variable = null;
    if ($this->brick_xml_properties->userVariable[Constants::REFERENCE_ATTRIBUTE] != null)
    {
      $variable = $this->brick_xml_properties->userVariable
        ->xpath($this->brick_xml_properties->userVariable[Constants::REFERENCE_ATTRIBUTE])[0];
    }
    else
    {
      $variable = $this->brick_xml_properties->userVariable;
    }
    $this->caption = "Ask \""
      . FormulaResolver::resolve($this->brick_xml_properties->formulaList)[Constants::ASK_QUESTION_FORMULA]
      . "\" and store written answer in " . $variable;

    $this->setImgFile(Constants::LOOKS_BRICK_IMG);
  }
}