<?php

namespace App\Catrobat\Controller\Web;

use App\Catrobat\Services\CatrobatCodeParser\ParsedSceneProgram;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;


/**
 * Class CodeViewController
 * @package App\Catrobat\Controller\Web
 */
class CodeViewController extends Controller
{

  /**
   * @param $id
   *
   * @var $parsed_program ParsedSceneProgram
   *
   * @return \Symfony\Component\HttpFoundation\Response
   * @throws \Twig\Error\Error
   */
  public function viewCodeAction($id)
  {
    try
    {
      $program = $this->get('programmanager')->find($id);
      $extracted_program = $this->get('extractedfilerepository')->loadProgramExtractedFile($program);

      $parsed_program = $this->get('catrobat_code_parser')->parse($extracted_program);

      $web_path = $extracted_program->getWebPath();
    } catch (\Exception $e)
    {
      $parsed_program = null;
      $web_path = null;
    }

    $code_view_twig_params = [
      'parsed_program' => $parsed_program,
      'path'           => $web_path,
    ];

    return $this->get('templating')->renderResponse('Program/codeview.html.twig', $code_view_twig_params);
  }
}