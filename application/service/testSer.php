<?php defined('BASEPATH') OR exit('No direct script access allowed');
class testSer extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('hotelDetailAll');
    }

    public function test()
    {
        return $this->hotelDetailAll->getFirstRow();
    }
}