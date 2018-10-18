<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use XPathSelector\Selector;

class pars {

    private $db;

    public function __construct() {
        $driver = 'mysql';
        $host = 'localhost';
        $db_name = 'test';
        $db_user = 'root';
        $db_pass = '';
        $charset = 'utf8';
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        $this->db = new PDO("$driver:host=$host;dbname=$db_name;charset=$charset",$db_user,$db_pass,$options);
    }

    public function getAllAdvert($client,$params = ''){

	$headers = [
	    'headers' => [
	        'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
	        'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
	    ]
	];
//вытаскиваем все ссылки на объявления
// в $params ложим номер страницы
	$dom = Selector::loadHTML($client->get('https://www.olx.ua/uslugi/'.$params, $headers )->getBody());

	$links = $dom->findAll('//td[starts-with(@class, "offer ")]')->map(function ($node) {
	    return [
	        'cover_url' => $node->find('.//a[@class="marginright5 link linkWithHash detailsLink"]/@href')->extract()
	    ];
	});

    return $links;
    }

    public function getAllInfo($url,$cookie_path) {
        $allInfo = [];
        $id='';
        //$cookie_path = $_SERVER['DOCUMENT_ROOT'].'/cookie.dat';

        preg_match('|-ID(.*).html|', $url, $id);

        $olx = curl_init($url);
        curl_setopt($olx, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($olx, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($olx, CURLOPT_HEADER, 1);
        curl_setopt($olx, CURLOPT_COOKIEFILE, $cookie_path);
        curl_setopt($olx, CURLOPT_COOKIEJAR, $cookie_path);
        $result = curl_exec($olx);
        curl_close($olx);
        $dom = Selector::loadHTML($result);

        try{
          preg_match("|phoneToken = '(.*)';|", $result, $token);
          $allInfo["title"] = trim($dom->find('//*[@id="offerdescription"]/div[2]/h1')->extract());
          $allInfo["city"]  = $dom->findOneOrNull('//*[@id="offerdescription"]/div[2]/div[1]/a')->extract();

          //стучимся заномером
          $olx_number = curl_init('https://www.olx.ua/ajax/misc/contact/phone/' . $id[1] . '/?pt=' . $token[1]);

          curl_setopt($olx_number, CURLOPT_HTTPHEADER, [
                  'Host: www.olx.ua',
                  'Accept: */*',
                  'Accept-Language: uk,ru;q=0.8,en-US;q=0.5,en;q=0.3',
                  'Accept-Encoding: gzip, deflate, br',
                  'Connection: keep-alive',
                  'X-Requested-With: XMLHttpRequest'
              ]);
          curl_setopt($olx_number, CURLOPT_REFERER, $url);
          //curl_setopt($olx_number, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
          curl_setopt($olx_number, CURLOPT_COOKIEFILE, $cookie_path);
          curl_setopt($olx_number, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($olx_number, CURLOPT_RETURNTRANSFER, true);
          $result = curl_exec($olx_number);
          curl_close($olx_number);

          $allInfo['phone'] = $this->phoneValidat(json_decode($result)->value);

          return $allInfo;

        } catch (Exception $e) {
          //echo $e;
          return false;
        }
    }

    private function phoneValidat($phone){

        $pars_phone = explode("</span>", $phone);
        foreach ($pars_phone as $key => $val) {
            $pars_phone[$key] = preg_replace ("/\D/","",$val);
        }

        return implode(', ', array_diff($pars_phone, array('')));
    }

    public function setInfo($info){

        if($this->db){
        $insertNew = $this->db->prepare("INSERT INTO content(city,title,phones)
                    VALUES(:city, :title, :phones)");
                $insertNew->execute(array(
                    "city" => $info['city'],
                    "title" => $info['title'],
                    "phones" => $info['phone'],
                ));
        }
    }
}

$client = new Client();
$info = new pars();

for ($i=1; $i<=34; $i++){

    echo 'Итерация:'.$i."\n";
    // создаём новый файл кук, потому что на один много номеров не дают
    $cookie_path = '/home/stas/parser/cookie/cookie'.$i.'.dat';
    $urls = $info->getAllAdvert($client,'?page='.$i);
    foreach($urls as $url)
    {
        $res = $info->getAllInfo($url['cover_url'],$cookie_path);

        if($res['phone']){
            $info->setInfo($res);
        }else{
            continue;
        }
    }
}

echo 'ti';
