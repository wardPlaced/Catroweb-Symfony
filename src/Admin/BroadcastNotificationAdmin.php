<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Route\RouteCollection;


/**
 * Class BroadcastNotificationAdmin
 * @package App\Admin
 */
class BroadcastNotificationAdmin extends AbstractAdmin
{
  /**
   * @var string
   */
  protected $baseRouteName = 'admin_broadcast';

  /**
   * @var string
   */
  protected $baseRoutePattern = 'broadcast';

  /**
   * @param RouteCollection $collection
   */
  protected function configureRoutes(RouteCollection $collection)
  {
    $collection->clearExcept(['list']);
    $collection->add('send');
  }
}