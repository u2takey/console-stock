#!/usr/bin/php
<?php
############################################################################
############################################################################
# FileCache <dafei.net@gmail.com>
final class FileCache
{
	private static $_iscache   = true;
	private static $_cachedir  = '/tmp/';
	private static $_cachetime = 3600;
	public static function get($key=false,$d=false)
	{
		if(empty($key) or !self::$_iscache)
		{
			return false;
		}
		$filename  = self::get_filename($key,$d);
		if(!file_exists($filename))
		{
			return false;
		}
		$data = file_get_contents($filename);
		$data = unserialize($data);
		$time = (int)$data['time'];
		$data = $data['data'];
		if($time>time())
		{
			return $data;
		}
		else
		{
			return false;
		}
	}
	public static function set($key=false,$value=false,$t=0,$d=false)
	{
		if(empty($key) or !self::$_iscache)
		{
			return false;
		}
		$t = (int)$t ? (int)$t : self::$_cachetime;
		$filename  = self::get_filename($key,$d);
		if(!self::is_mkdir(dirname($filename)))
		{
			return false;
		}
		$data['time'] = time()+$t;
		$data['data'] = $value;
		$data = serialize($data);
		if(PHP_VERSION >= '5')
		{
			file_put_contents($filename,$data);
		}
		else
		{
			$handle = fopen($filename,'wb');
			fwrite($handle,$data);
			fclose($handle);
		}
		return true;
	}
	public static function un_set($key=false,$d=false)
	{
		if(empty($key))
		{
			return false;
		}
		$filename = self::get_filename($key,$d);
		@unlink($filename);
		return true;
	}
	public static function get_filename($key=false,$d=false)
	{
		if(empty($key))
		{
			return false;
		}
		$dir       = empty($d) ? self::$_cachedir : $d ;
		$key_md5   = md5($key);
		$filename  = rtrim($dir,'/').'/'.substr($key_md5,0,2).'/'.substr($key_md5,2,2).'/'.substr($key_md5,4,2).'/'.$key_md5;
		return $filename;
	}
	public static function is_mkdir($dir='')
	{
		if(empty($dir))
		{
			return false;
		}
		if(!is_writable($dir))
		{
			if(!@mkdir($dir,0777,true))
			{
				return false;
			}
		}
		return true;
	}
}

class QT
{
	private $market;
	private $category;

	function __construct($qtData = false, $market, $category) {
		$this->market = $market;
		$this->category = $category;
		if($qtData) {
			$this->items = $qtData;
		}
	}
	//股票名称
	public function getName(){
		return $this->items[1];
	}
	//获得涨跌幅
	public function getPercent() {
		$ret = $this->items[32];

		if($ret > 0) {
			$ret = '+'.$ret;
		}

		return $ret.'%';
	}
	//得到当前市价
    public function getPrice(){
    	if($this->items[40] && $this->category != 'ZS') {
			return $this->items[40];
		} else {
			return $this->items[3];
		}
	}
    //得到昨收价
    public function getLastClosePrice(){
		return $this->items[4];
	}
    //得到今开盘
    public function getTodayOpenPrice(){
		return $this->items[5];
	}
	//最高
    public function getHighPrice(){
		return $this->items[33];
	}
	//最低
    public function getLowPrice(){
		return $this->items[34];
	}
	//获取状态
	public function getStatus() {
		return $this->items[40];
	}
	//获取普通状态
	public function getErrorStatus() {
		$status = $this->getStatus();
		switch($status) {
			case 'D':
				return '退市';
			case 'S':
				return '停牌';
			case 'U':
				return '未上市';
			case 'Z':
				return '暂停上市';
			break;
		}

		return false;
	}
}

class QTHk extends QT {}

class QTUs extends QT {}

class QTJj extends QT
{
	public function getPrice(){
		return $this->items[3];		
	}

	public function getValueDate() {
		return $this->items[2];
	}

	public function isHBType() {
		return $this->items[18] == '货币型';
	}

	public function getEarnPer() {
		return $this->items[27];
	}

	public function getYearRadio() {
		return $this->items[28].'%';
	}
}

############################################################################
############################################################################
#  Stock <dafei.net@gmail.com>
// weixinchen(weixinchen@tencent.com)
class SmartBox
{
	private $keyword = "";
	private $queryUrl = "http://smartbox.gtimg.cn/s3/?&t=all&format=jsonp&q=";
	private $results = [];

	function setKeyWord($keyword) {
		$this->keyword = $keyword;
	}

	function search() {
		$cacheData = false;
		if(!$cacheData) {
			$url = $this->queryUrl.urlencode($this->keyword);

			$request_result = $this->request($url);

			$json = json_decode($request_result);
			$searchData = $json->data;
			if(count($searchData) > 0) {
				FileCache::set('__cache__'.$this->keyword, $searchData, 24*60*60);
			}
		} else {
			$searchData = $cacheData;
		}

		if(count($searchData) > 0) {
			
			$codeArray = array();

			foreach ($searchData as $value) {
				$d = explode('~', $value);
				if(preg_match('/(\..*)$/', $d[1], $re)) {
					$d[1] = str_replace($re[1], "", $d[1]);
				}
				if($d[0] == 'us') {
					$d[1] = strtoupper($d[1]);
				}
				$dCode = $d[0].$d[1];
				if($d[0] == 'hk') {
					$dCode = 'r_'.$dCode;
				}
				if($d[0] == 'jj') {
					$dCode = 's_'.$dCode;
				}
				array_push($codeArray, $dCode);
			}

			$qt = new StockQt();
			$qt->fetchQt(implode(',', $codeArray));
			
			foreach ($searchData as $key => $value) {
				$stock = new Stock($value, $qt);
				$this->result($key, $stock->getLink(), $stock->getTitle(), $stock->getSubTitle(), null);
			}
		} else {
			$this->lastPlaceholder();
		}
	}

	function lastPlaceholder() {
		$this->result(0, 'http://gu.qq.com/i', '没有找到股票？进入我的自选股查找', null, null);		
	}

	public function result( $uid, $arg, $title, $sub, $icon, $valid='yes', $auto=null, $type=null )
	{
		$temp = array(
			'uid' => $uid,
			'arg' => $arg,
			'title' => $title,
			'subtitle' => $sub,
			'icon' => $icon,
			'valid' => $valid,
			'autocomplete' => $auto,
			'type' => $type
		);

		if ( is_null( $type ) ):
			unset( $temp['type'] );
		endif;

		array_push( $this->results, $temp );

		return $temp;
	}

	public function results()
	{
		return $this->results;
	}

	public function request( $url=null, $options=null )
	{
		if ( is_null( $url ) ):
			return false;
		endif;

		$defaults = array(									// Create a list of default curl options
			CURLOPT_RETURNTRANSFER => true,					// Returns the result as a string
			CURLOPT_URL => $url,							// Sets the url to request
			CURLOPT_FRESH_CONNECT => true
		);

		if ( $options ):
			foreach( $options as $k => $v ):
				$defaults[$k] = $v;
			endforeach;
		endif;

		$ch  = curl_init();									// Init new curl object
		curl_setopt_array( $ch, $defaults );				// Set curl options
		$out = curl_exec( $ch );							// Request remote data
		$err = curl_error( $ch );
		curl_close( $ch );									// End curl request

		if ( $err ):
			return $err;
		else:
			return $out;
		endif;
	}
}

class Stock
{
	// 市场: sh|sz|hk|us|jj
	public $market;
	// 市场类类别:
	public $typeName;
	// 代码
	public $code;
	// 详细代码
	public $fullCode;
	// 名称
	public $name;
	// 拼音
	public $pinyin;
	// 类别
	public $category;

	private $qt;

	function __construct($data, $stockQt) {
		$result = explode("~", $data);
		if($result[0] == 'us') {
			if(preg_match('/(\..*)$/', $result[1], $re)) {
				$result[1] = str_replace($re[1], "", $result[1]);
			}
			$result[1] = strtoupper($result[1]);
		}
		$this->market = $result[0];
		$this->code = $result[1];
		$this->fullCode = $this->market.$this->code;
		$this->name = $result[2];
		$this->pinyin = $result[3];
		$this->category = $result[4];
		$qtData = $stockQt->getItem($this->fullCode);
		if($qtData) {
			switch($this->market) {
				case 'sh':
				case 'sz':
					$this->qt = new QT($qtData, $this->market, $this->category);
				break;
				case 'hk':
					$this->qt = new QTHk($qtData, $this->market, $this->category);
				break;
				case 'us':
					$this->qt = new QTUs($qtData, $this->market, $this->category);
				break;
				case 'jj':
					$this->qt = new QTJj($qtData, $this->market, $this->category);
				break;
			}
		}

		$this->parse();
	}

	private function parse() {
		if($this->category == 'QH-QH') {
			$this->typeName = '期货';
		} else if($this->category == 'QH-IF') {
			$this->typeName = '股期';
		} else if($this->market == 'us') {
			$this->typeName = '美股';
		} else if($this->market == 'hk') {
			$this->typeName = '港股';
		} else if($this->market == 'jj') {
			$this->typeName = '基金';
		} else if($this->market == 'sh' || $this->market == 'sz') {
			switch($this->category) {
				case 'FJ':
				case 'LOF':
				case 'ETF':
					$this->typeName = '基金';
				break;
				case 'ZS':
				case 'GP-A':
				case 'GP-B':
				case 'ZQ':
				case 'QZ':
				default:
					if($this->market == 'sh') {
						$this->typeName = '上海';
					} else {
						$this->typeName = '深圳';
					}
				break;
			}
		} else {
			$this->typeName = '未知';
		}
	}

	function getTitle() {
		$typeName = $this->typeName;
		$name = $this->name;
		$code = $this->code;

		$return = sprintf("[%s] %-20s %-12s", $typeName, $name, $code);
		if($this->qt) {
			if(!$this->qt->getErrorStatus()) {
				$price = $this->qt->getPrice();
				if($this->market != 'jj') {
					$percent = $this->qt->getPercent();
					$return .= sprintf(" %-12s %-12s", $price, $percent);
				} else {
					if(!$this->qt->isHBType()) {
						$return .= sprintf(" 净值:%-12s", $price);
					} else {
						$price = $this->qt->getEarnPer();
						$return .= sprintf(" 万份收益:%-12s", $price);
					}
				}
			} else {
				$status = $this->qt->getErrorStatus();
				$return .= " {$status}";
			}
		}

		return $return;
	}

	function getSubTitle() {
		$fullCode = $this->fullCode;

		$return = "{$fullCode}";
		if($this->pinyin != '*') {
			$pinyin = strtoupper($this->pinyin);
			$return .= "（{$pinyin}）";
		}

		if($this->qt) {
			if(!$this->qt->getErrorStatus()) {
				if($this->market != 'jj') {
					$lastClose = $this->qt->getLastClosePrice();
					$todayOpen = $this->qt->getTodayOpenPrice();
					$hPrice = $this->qt->getHighPrice();
					$lPrice = $this->qt->getLowPrice();

					$return .= " 高:{$hPrice}  低:{$lPrice}  收:{$lastClose}  开:{$todayOpen}";
				} else {
					if(!$this->qt->isHBType()) {
						$valueDate = $this->qt->getValueDate();
						$return .= " 净值更新时间:{$valueDate}";
					} else {
						$yearRadio = $this->qt->getYearRadio();
						$return .= " 七日年化收益率:{$yearRadio}";
					}
				}
			}
		}

		return $return;
	}

	function getLink() {
		return "http://gu.qq.com/".$this->fullCode;
	}
}

class StockQt 
{
    protected $items = array();

    //查询行情数据
    public function fetchQt($stock_code){
        $url = 'http://qt.gtimg.cn/q='.$stock_code;
        $data = $this->getCurlData($url, 80, 2);
        $data = iconv("GB2312", "UTF-8//IGNORE", $data);
        $data = trim($data);
    	$edatas = explode(';', $data);

    	$codes = array();
    	foreach($edatas as $value){
            $it = explode('~',$value);
            if(trim($it[0])){
            	preg_match('/_([^_]*?)\=/', $it[0], $result);
        		$this->items[$result[1]] = $it;
            }
        }
	}
	//获得数据
	public function getItem($code) {
		$data = $this->items[$code];
		if($data) {
			return $data;
		} else {
			return false;
		}
	}

	private function getCurlData($url, $port=80,$timeout=10) {
		$ch = curl_init();
		 // set port
		curl_setopt($ch, CURLOPT_PORT, $port);
		// drop http header
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		// get data as string
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		// set timeout
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);       
		curl_setopt($ch, CURLOPT_URL, $url);
	    if(defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')){
			curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		}

		// execute fetch
		$data = curl_exec($ch);
		$errno = curl_errno($ch);
		if($errno > 0) {
			//try one time
			$data = curl_exec($ch);
			$errno = curl_errno($ch);
			if($errno > 0){
				return false;
			}
		}
		if(empty($data)) {
		    return array();
		}
		return $data;
	}
}

############################################################################
############################################################################
# Color
class Color{
    public static $Color_Off="\033[0m";      # Text Reset

    # Regular Colors
    public static $Black="\033[0;30m";       # Black
    public static $Red="\033[0;31m";         # Red
    public static $Green="\033[0;32m";       # Green
    public static $Yellow="\033[0;33m";      # Yellow
    public static $Blue="\033[0;34m";        # Blue
    public static $Purple="\033[0;35m";      # Purple
    public static $Cyan="\033[0;36m";        # Cyan
    public static $White="\033[0;37m";       # White

    # Bold
    public static $BBlack="\033[1;30m";      # Black
    public static $BRed="\033[1;31m";        # Red
    public static $BGreen="\033[1;32m";      # Green
    public static $BYellow="\033[1;33m";     # Yellow
    public static $BBlue="\033[1;34m";       # Blue
    public static $BPurple="\033[1;35m";     # Purple
    public static $BCyan="\033[1;36m";       # Cyan
    public static $BWhite="\033[1;37m";      # White

    # Underline
    public static $UBlack="\033[4;30m";      # Black
    public static $URed="\033[4;31m";        # Red
    public static $UGreen="\033[4;32m";      # Green
    public static $UYellow="\033[4;33m";     # Yellow
    public static $UBlue="\033[4;34m";       # Blue
    public static $UPurple="\033[4;35m";     # Purple
    public static $UCyan="\033[4;36m";       # Cyan
    public static $UWhite="\033[4;37m";      # White

    # Background
    public static $On_Black="\033[40m";      # Black
    public static $On_Red="\033[41m";        # Red
    public static $On_Green="\033[42m";      # Green
    public static $On_Yellow="\033[43m";     # Yellow
    public static $On_Blue="\033[44m";       # Blue
    public static $On_Purple="\033[45m";     # Purple
    public static $On_Cyan="\033[46m";       # Cyan
    public static $On_White="\033[47m";      # White

    # High Intensity
    public static $IBlack="\033[0;90m";      # Black
    public static $IRed="\033[0;91m";        # Red
    public static $IGreen="\033[0;92m";      # Green
    public static $IYellow="\033[0;93m";     # Yellow
    public static $IBlue="\033[0;94m";       # Blue
    public static $IPurple="\033[0;95m";     # Purple
    public static $ICyan="\033[0;96m";       # Cyan
    public static $IWhite="\033[0;97m";      # White

    # Bold High Intensity
    public static $BIBlack="\033[1;90m";     # Black
    public static $BIRed="\033[1;91m";       # Red
    public static $BIGreen="\033[1;92m";     # Green
    public static $BIYellow="\033[1;93m";    # Yellow
    public static $BIBlue="\033[1;94m";      # Blue
    public static $BIPurple="\033[1;95m";    # Purple
    public static $BICyan="\033[1;96m";      # Cyan
    public static $BIWhite="\033[1;97m";     # White

    # High Intensity backgrounds
    public static $On_IBlack="\033[0;100m";  # Black
    public static $On_IRed="\033[0;101m";    # Red
    public static $On_IGreen="\033[0;102m";  # Green
    public static $On_IYellow="\033[0;103m"; # Yellow
    public static $On_IBlue="\033[0;104m";   # Blue
    public static $On_IPurple="\033[0;105m"; # Purple
    public static $On_ICyan="\033[0;106m";   # Cyan
    public static $On_IWhite="\033[0;107m";  # White
}
############################################################################
############################################################################
# Main
if ($argv[1] === "watch"){
    while (true){
        system('clear');
        print Color::$White."\n\n";
        print Color::$White."watch will refresh stocks in every 15s";
        print Color::$White."\n\n";
        search_stock(array_slice($argv, 2));
        sleep(15);
    }
}else{
    search_stock(array_slice($argv, 1));
}

function search_stock($codes) {
    $ret = [];
    foreach($codes as $code){
        $sb = new SmartBox();
        $sb->setKeyWord($code);
        $sb->search();
        $a = $sb->results();
        foreach($a as $b) {
            $r1 = $b['title']." ".$b['subtitle'];
            $r2 = preg_split("/[\s]+/", $r1);
            // [上海]  长电科技  600584  42.69  +1.93%  sh600584（CDKJ）  高:43.58  低:41.61  收:41.88  开:41.61
    
            if(count($r2) == 10){
                $mask = " %-30s %-15s %-15s %-10s %-40s \n";
                $newarry = [$r2[0].$r2[1],$r2[2],$r2[3],$r2[4],$r2[6].','.$r2[7].','.$r2[8].','.$r2[9]];
                array_push($ret, $newarry);
                $price_color=Color::$BGreen;
                if (substr( $r2[4], 0, 1 ) === "+"){
                    $price_color=Color::$BRed;
                }
                if (ctype_digit($code) && $code !== $r2[2]){
                    continue;
                }
    
                printf($mask, Color::$BBlue.$r2[0]." ".$r2[1], Color::$BYellow.$r2[2], 
                        $price_color.$r2[3],$r2[4], Color::$BPurple.$r2[6].','.$r2[7].','.$r2[8].','.$r2[9]);
            }
        }
    }
}
?>