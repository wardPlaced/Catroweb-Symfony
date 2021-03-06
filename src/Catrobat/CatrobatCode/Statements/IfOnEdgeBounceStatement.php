<?php

namespace App\Catrobat\CatrobatCode\Statements;

/**
 * Class IfOnEdgeBounceStatement
 * @package App\Catrobat\CatrobatCode\Statements
 */
class IfOnEdgeBounceStatement extends Statement
{
  const BEGIN_STRING = "if on edge, bounce";
  const END_STRING = "<br/>";

  /**
   * IfOnEdgeBounceStatement constructor.
   *
   * @param $statementFactory
   * @param $xmlTree
   * @param $spaces
   */
  public function __construct($statementFactory, $xmlTree, $spaces)
  {
    parent::__construct($statementFactory, $xmlTree, $spaces,
      self::BEGIN_STRING,
      self::END_STRING);
  }

  /**
   * @return string
   */
  public function getBrickText()
  {
    return "If on edge, bounce";
  }

  /**
   * @return string
   */
  public function getBrickColor()
  {
    return "1h_brick_blue.png";
  }
}
