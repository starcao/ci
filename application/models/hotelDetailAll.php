<?php defined('BASEPATH') OR exit('No direct script access allowed');
class hotelDetailAll extends CI_Model
{
    public function __construct()
    {
        parent::__construct();

        $this->_tableName = 'hotel_detail_all';
        $this->load->database();
    }

    public function getFirstRow()
    {
        return $this->db->get($this->_tableName)->first_row("array");
    }
}