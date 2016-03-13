<?php
defined('BASEPATH') OR exit('No direct script access allowed');
class MY_Loader extends CI_Loader
{
  /**
   * List of loaded sercices
   * @var array
   * @access protected
   */
  protected $_ci_services = array();

  /**
   * List of paths to load sercices from
   * @var array
   * @access protected
   */
  protected $_ci_service_paths  = array();

  public function __construct()
  {
    parent::__construct();
    $this->_ci_service_paths = array(APPPATH);
  }

  /**
   * Service Loader
   * @param string the name of the class
   * @param mixed the optional parameters
   * @param string an optional object name
   * @return void
   */
  public function service($service = '', $params = NULL, $object_name = NULL)
  {
    if(is_array($service))
    {
      foreach($service as $class)
        $this->service($class, $params);

      return;
    }

    if($service == '' or isset($this->_ci_services[$service]))
      return FALSE;

    if(!is_null($params) && !is_array($params))
      $params = NULL;

    $subdir = '';
    if(($last_slash = strrpos($service, '/')) !== FALSE)
    {
      $subdir = substr($service, 0, $last_slash + 1);
      $service = substr($service, $last_slash + 1);
    }

    if(!class_exists('CI_Model', FALSE))
      load_class('Model', 'core');

    foreach($this->_ci_service_paths as $path)
    {
      $filepath = $path .'service/'.$subdir.$service.'.php';
      if(!file_exists($filepath))
        continue;

      include_once($filepath);
      if(empty($object_name))
        $object_name = $service;

      $service = ucfirst($service);
      $CI = &get_instance();
      if($params !== NULL)
        $CI->$object_name = new $service($params);
      else
        $CI->$object_name = new $service();

      $this->_ci_services[] = $object_name;
    }
  }
}