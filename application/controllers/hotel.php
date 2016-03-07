<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class hotel extends CI_Controller
{
    private $hotelUrl = "";
    private $Xcityid = 0;

    private $Ycityid = 0;
    private $Ycityname = "";
    private $Ycountryid = 0;
    private $Ycountryname = "";

    public function __construct()
    {
        parent::__construct();
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', '0');
        $this->load->database();
    }

    /**
     * 抓取网站酒店详情页的url（猫头鹰）
     */
    public function index()
    {
        $cityarr = $this->db->query("select * from flight_detail_all")->result_array();
        foreach($cityarr as $val)
        {
            if(trim($val['m_url']))
            {
                preg_match("/\d+/", $val['m_url'], $matches);
                $mid = $matches[0];

                for($k = 1; $k < 4; $k++)
                {
                    $cat = $k > 1 ? "?cat=".$k : "";
                    $pageType = $k > 1 ? "&cat=".$k : "";

                    $page = $this->_getHotelpage($val['m_url'].$cat);
                    for($i = 1; $i <= $page; $i++)
                    {
                        $offset = ($i - 1) * 30;
                        $pageUrl = "http://www.tripadvisor.cn/Hotels?geo={$mid}&requestingServlet=Hotels&seen=0&o=a{$offset}".$pageType;
                        $urlarr = $this->_getHotelUrl($pageUrl);
                        foreach($urlarr as $v)
                        {
                            $data = array();
                            $data['cid'] = $val['des_city_id'];
                            $data['cname'] = $val['des_city_name'];
                            $data['countryid'] = $val['des_country_id'];
                            $data['countryname'] = $val['des_country_name'];
                            $data['url'] = "http://www.tripadvisor.cn".$v;
                            $data['atime'] = time();

                            ###############
                            $hotelIndex = $this->db->query("select * from hotel_content where url = '{$data['url']}'")->result_array();
                            if(empty($hotelIndex))
                            ###############
                            $this->db->insert("hotel_content", $data);
                        }
                    }
                }
            }
        }
    }

    /**
     * 抓取酒店详情页html（猫头鹰）
     */
    public function getHtml()
    {
        $hotelarr = $this->db->query("select * from hotel_content where status = 0")->result_array();

        foreach($hotelarr as $val)
        {
            $data = array();
            $data['status'] = 1;
            $data['html'] = @file_get_contents($val['url']);
            if(!$data['html'])
                continue;

            $this->db->where("id", $val['id']);
            $this->db->update("hotel_content", $data);
        }
    }

    /**
     * 分析酒店信息（猫头鹰）
     */
    public function addHotelMsg()
    {
        $bigarr = $this->db->query("select max(id) as maxid from hotel_content where status = 1")->first_row();
        $maxid = $bigarr->maxid;

        $prevId = 0;
        $pagesize = 30;
        while($prevId < $maxid)
        {
            $hotelarr = $this->db->query("select * from hotel_content where id > {$prevId} and status = 1 order by id asc limit {$pagesize}")->result_array();
            foreach($hotelarr as $v)
            {
                $prevId = $v['id'];
                $hotelmsg = $this->_getHotelMsg($v['html']);
                $hotel = array();
                if(empty($hotelmsg))
                    $hotel = array("status" => 0, "html" => null);
                else
                {
                    $data['hotel_name'] = trim($hotelmsg['cn']);
                    $data['hotel_name_en'] = trim($hotelmsg['en']);
                    $data['hotel_address'] = trim($hotelmsg['address']);
                    $data['hotel_phone'] = $hotelmsg['phone'];
                    $data['des_city_id'] = $v['cid'];
                    $data['des_city_name'] = $v['cname'];
                    $data['des_country_id'] = $v['countryid'];
                    $data['des_country_name'] = $v['countryname'];
                    $data['hotel_trading_area'] = "";
                    $data['hotel_introduce'] = "";
                    $data['hotel_traffic'] = "";
                    $data['addtime'] = date("Y-m-d H:i:s");

                    ###############
                    $hotelIndex = $this->db->query("select * from hotel_detail_all where hotel_name = '{$data['hotel_name']}'")->result_array();
                    if(empty($hotelIndex))
                    ###############
                    $this->db->insert("hotel_detail_all", $data);

                    $hotel['status'] = 2;
                }

                /*$this->db->where("id", $v['id']);
                $this->db->update("hotel_content", $hotel);*/
            }
        }
    }

    /**
     * 获取地区酒店的总页数（猫头鹰）
     * @param string $url 酒店列表url
     * @return int
     */
    private function _getHotelpage($url)
    {
        $dom = new DomDocument();
        @$dom->loadHTMLFile($url);

        $div = @$dom->getElementById("ACCOM_OVERVIEW");
        $pagediv = @$div->lastChild->previousSibling->lastChild->previousSibling->firstChild->lastChild;
        if(gettype($pagediv) != "object")
            return 0;

        return (int) $pagediv->lastChild->nodeValue;
    }

    /**
     * 获取酒店url(猫头鹰)
     * @param string $url 分页url
     * @return array url数组
     */
    private function _getHotelUrl($url)
    {
        $data = array();
        $dom = new DomDocument();
        @$dom->loadHTMLFile($url);

        $div = $dom->getElementById("ACCOM_OVERVIEW");
        if(gettype($div) != "object")
            return $data;

        $chilDiv = $div->firstChild;

        while($chilDiv = $chilDiv->nextSibling)
        {
            if($chilDiv->nodeName != "div")
                continue;

            $itemnum = $chilDiv->attributes->length;
            if($itemnum)
            {
                for($i = 0; $i < $itemnum; $i++)
                {
                    $item = $chilDiv->attributes->item($i);
                    if($item->name == "id" && preg_match("/hotel_\d+/", $item->value))
                    {
                        $a = $chilDiv->firstChild->nextSibling->firstChild->firstChild->firstChild->firstChild->firstChild->firstChild;
                        $aitem = $a->attributes;
                        for($j = 0; $j < $aitem->length; $j++)
                        {
                            if($aitem->item($j)->name == "href")
                            {
                                $data[] = $aitem->item($j)->value;
                                break;
                            }
                        }
                        break;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * 获取酒店信息（猫头鹰）
     * @param string $html 酒店详情页html
     * @return array $data 酒店信息
     */
    private function _getHotelMsg($html)
    {
        $data = array();
        if(!$html)
            return $data;

        $dom = new DomDocument();
        @$dom->loadHTML($html);

        $h1 = $dom->getElementById("HEADING");
        $data['cn'] = @$h1->firstChild->nextSibling->nextSibling->nodeValue;
        $data['en'] = @$h1->firstChild->nextSibling->nextSibling->nextSibling->nodeValue;
        $div = @$dom->getElementById("HEADING_GROUP")->lastChild->previousSibling->lastChild->previousSibling->firstChild->nextSibling->firstChild->nextSibling->firstChild->nextSibling;
        if(!$data['cn'] || !$data['en'] || gettype($div) != "object")
            return array();
        $data['address'] = @$div->lastChild->previousSibling->nodeValue;

        $phonescriptarr = $div->getElementsByTagName("script");
        for($i = 0; $i < $phonescriptarr->length; $i++)
        {
            $phonestr = $phonescriptarr->item($i);
            if(!preg_match("/\\\u/", $phonestr->nodeValue))
            {
                $phonescript = $phonestr->nodeValue;
                break;
            }
        }
        if(!isset($phonescript))
            return array();

        preg_match("/a=.*document/s", $phonescript, $matches);
        $phonearr = explode("\n", $matches[0]);
        array_pop($phonearr);

        $a = $b = $c = "";
        foreach($phonearr as $val)
        {
            $val = str_replace(" ", "", $val);
            if(strpos($val, 'a') !== false)
            {
                preg_match("/\d+/", $val, $matches);
                $a .= isset($matches[0]) ? $matches[0] : "";
            }
            elseif(strpos($val, 'b') !== false)
            {
                preg_match("/\d+/", $val, $matches);
                $b .= isset($matches[0]) ? $matches[0] : "";
            }
            elseif(strpos($val, 'c') !== false)
            {
                preg_match("/\d+/", $val, $matches);
                $c .= isset($matches[0]) ? $matches[0] : "";
            }
        }
        $data['phone'] = $a.$c.$b;

        foreach($data as $value)
        {
            if(!$value)
                return array();
        }

        return $data;
    }

    /**
     * 处理航班信息
     */
    public function flightContent()
    {
        $baseurl = "http://flights.ctrip.com/Process/FlightStatus/FindByFlightNoWithJson?flightNo=";
        $this->load->model("flightCarddateAll");

        $flightarr = $this->db->query("select distinct flight_code from flight_route")->result_array();

        foreach($flightarr as $v)
        {
            if($flight = trim($v['flight_code']))
            {
                $data = array();
                $url = $baseurl.$flight;
                $flightObj = $this->_getFlightData($flight, $url);
                if(gettype($flightObj) != "object")
                    continue;

                $data['card_id'] = $flight;
                $data['airline_company'] = $flightObj->CompanyShortName;
                $data['take_flight'] = $flightObj->DCityName.$flightObj->DAirportName;
                $data['des_flight'] = $flightObj->ACityName.$flightObj->AAirportName;

                $plandtime = $flightObj->PlanDTime;
                preg_match("/\d{1,2}:\d{1,2}/", $plandtime, $matches);
                $data['flight_take_time'] = $matches[0];

                $planatime = null;
                if(strpos($flightObj->PlanATime, "<") !== false)
                {
                    $planatimearr = explode("<", $flightObj->PlanATime);
                    $planatime = trim($planatimearr[0]);
                }
                $planatime = $planatime ?: $flightObj->PlanATime;
                preg_match("/\d{1,2}:\d{1,2}/", $planatime, $matches);
                $data['flight_des_time'] = $matches[0];

                $data['flight_taxation'] = " ";
                $data['flight_time'] = $flightObj->FlightDuration;
                $data['addtime'] = date("Y-m-d H:i:s");

                $this->flightCarddateAll->insert($data);
            }
        }
    }

    /**
     * 处理酒店信息
     */
    public function hotelContent()
    {
        $this->load->model("hotelDetailAll");
        $cityarr = $this->db->query("select * from flight_detail_all")->result_array();
        foreach($cityarr as $key => $val)
        {
            if($val['city_url'])
            {
                $this->hotelUrl = $val['city_url'];
                preg_match("/\d+$/", $val['city_url'], $matches);
                $this->Xcityid = $matches[0];
                $this->Ycityid = $val['des_city_id'];
                $this->Ycityname = $val['des_city_name'];
                $this->Ycountryid = $val['des_country_id'];
                $this->Ycountryname = $val['des_country_name'];

                $firstdate = date("Y-m-d", time() + 86400*7);
                $secondate = date("Y-m-d", time() + 86400*8);
                $url = "http://hotels.ctrip.com/international/tool/AjaxHotelList.aspx?checkIn={$firstdate}&checkOut={$secondate}&cityId={$this->Xcityid}&pageIndex=";

                $hpages = $this->_getHotelPages();
                for($i = 1; $i <= $hpages; $i++)
                {
                    $hotelarr = $this->_getHotelData($url, $i);
                    foreach($hotelarr['msg'] as $v)
                    {
                        $hotel = array();
                        $hotelmessage = $this->_getHotelMessage($hotelarr['html']->hotelListHtml, $v->id);
                        if(!$hotelmessage)
                            continue;

                        $hotelname = $this->_distinguish($v->name);
                        if(!$hotelname['cn'])
                            continue;

                        $hotel['hotel_name'] = $hotelname['cn'];
                        $hotel['hotel_name_en'] = $hotelname['en'];
                        $hotel['hotel_address'] = $hotelmessage['address'];
                        $hotel['hotel_phone'] = " ";
                        $hotel['des_city_id'] = $this->Ycityid;
                        $hotel['des_city_name'] = $this->Ycityname;
                        $hotel['des_country_id'] = $this->Ycountryid;
                        $hotel['des_country_name'] = $this->Ycountryname;
                        $hotel['hotel_trading_area'] = trim($hotelmessage['tarea']);
                        $hotel['hotel_introduce'] = $hotelmessage['brief'];
                        $hotel['hotel_traffic'] = " ";
                        $hotel['addtime'] = date("Y-m-d H:i:s");

                        $this->hotelDetailAll->insert($hotel);
                    }
                }
            }
        }
    }

    /**
     * 通过接口获取航班信息
     * @param string $flight 航班号
     * @param string $url 接口url
     * @return mixed $data 航班信息
     */
    private function _getFlightData($flight, $url)
    {
        $source = @file_get_contents($url);
        $data = null;
        if($source == false)
            return $data;

        $source = @iconv("GBK", "UTF-8", $source);
        $source = json_decode($source);

        if(gettype($source) == "object" && $source->Status == 200)
        {
            foreach($source->List as $v)
            {
                if($v->FlightNo == $flight)
                {
                    $data = $v;
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * 获取酒店信息
     * @param string $url baseurl
     * @param int $page 页码
     * @return array
     */
    private function _getHotelData($url, $page = 1)
    {
        $data = array();
        $myurl = $url.$page;
        $source = file_get_contents($myurl);

        $hotelListHtmlstr = substr($source, 0, strpos($source, "HotelPositionJSON") - 2)."}";
        $data['html'] = json_decode($hotelListHtmlstr);

        preg_match("/HotelPositionJSON.*TotalMsg/", $source, $matches);
        $datastr = substr($matches[0], 19, -10);
        $data['msg'] = json_decode($datastr);

        return $data;
    }

    /**
     * 获取酒店总页数
     * @return int $pages 总页数
     */
    private function _getHotelPages()
    {
        $dom = new DomDocument();
        @$dom->loadHTMLFile($this->hotelUrl);

        $page_info = $dom->getElementById("page_info");
        if(gettype($page_info) != "object")
            return 0;

        $div = $page_info->getElementsByTagName('div')->item(0);
        $a = $div->getElementsByTagName('a');
        $numOfa = $a->length;
        $pages = (int) $a->item($numOfa - 1)->nodeValue;

        return $pages;
    }

    /**
     * 通过curl获取html页面
     * @param string $url 页面url
     * @return string $source
     */
    private function _getHtmlByCurl($url)
    {
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)");
        $source = curl_exec($ch);
        curl_close($ch);

        return $source;
    }

    /**
     * 区分英文名称和中文名称
     * @param string $name 混合名称 格式：name(名称)
     * @return array $data 名称数组
     */
    private function _distinguish($name)
    {
        $name = str_replace(array("（", "）", ")"), array("(", "", ""), $name);
        $namearr = explode("(", $name);

        $data['en'] = trim($namearr[0]);
        $data['cn'] = @trim($namearr[1]);

        return $data;
    }

    /**
     * 从页面抓取酒店信息
     * @param string $html html内容
     * @param int $hotelid 酒店id
     * @return mixed $data
     */
    private function _getHotelMessage($html, $hotelid)
    {
        $dom = new DomDocument();
        $searchPage = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");
        @$dom->loadHTML($searchPage);

        $idhtml = $dom->getElementById($hotelid);
        if(gettype($idhtml) != "object")
            return false;

        $p = @$idhtml->firstChild->firstChild->nextSibling->firstChild->firstChild->lastChild;
        if(gettype($p) != "object")
            return false;

        $data['brief'] = @$p->lastChild->previousSibling->previousSibling->nodeValue;
        $data['address'] = @$p->firstChild->nextSibling->lastChild->attributes->item(0)->value;
        $data['tarea'] = @$p->firstChild->nextSibling->lastChild->previousSibling->previousSibling->nodeValue;
        if($data['tarea'] == " ")
            $data['tarea'] = @$p->firstChild->nextSibling->lastChild->previousSibling->previousSibling->previousSibling->nodeValue;

        if(!$data['brief'] || !$data['address'] || !$data['tarea'])
            return false;

        return $data;
    }
}