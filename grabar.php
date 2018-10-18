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

    public function getAllCategories($client,$url){

        $headers = [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ]
        ];

        $dom = Selector::loadHTML($client->get($url, $headers )->getBody());

        $links = $dom->findAll('//div[starts-with(@class, "subcategory-box well")]')->map(function ($node) {
            return $node->find('.//a/@href')->extract();
        });

        return $links;
    }

    public function getAllAdvert($client,$params = ''){

      	$headers = [
      	    'headers' => [
      	        'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
      	        'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
      	    ]
      	];


      	$dom = Selector::loadHTML($client->get('http://www.aquamonolit.ru'.$params, $headers )->getBody());

      	$links = $dom->findAll('//div[starts-with(@class, "item-box well")]')->map(function ($node) {
      	   return $node->find('.//a/@href')->extract();
      	});

        return $links;
    }

    public function getAllInfo($client, $url) {
        $headers = [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.71 Safari/537.36',
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ]
        ];

        $dom = Selector::loadHTML($client->get($url, $headers )->getBody());


          //preg_match("|phoneToken = '(.*)';|", $result, $token);
          $allInfo["model"] = trim($dom->find('//h1[starts-with(@class, "tovar-title")]/span')->extract());

          $allInfo["price"] = $dom->findAll('//div[starts-with(@class, "jsPrice-3287-b8146a9a-3ade-4bab-ba70-8f32930c277d jsPrice jsJBPrice jsJBPrice-b8146a9a-3ade-4bab-ba70-8f32930c277d-3287 c90ad7bd31c781e1544c1c3e5fe9b50e jbprice jbprice-tmpl-default jbprice-type-plain")]')->map(function ($node) {
        	   return
               $node->find('.//span[starts-with(@class, "jbcurrency-value")]')->extract();
        	});

          $allInfo["description"] = trim($dom->find('//div[starts-with(@class, "item-text")]/div[2]')->extract());

          //$allInfo["picture"] = $dom->find('//div[@id="gallery-1"]//a/img/@src')->extract();
          $allInfo["picture"] = $dom->findAll('//div[@id="gallery-1"]//a')->map(function ($node) {
        	   return
               $node->find('.//img/@src')->extract();
          });

          $table = $dom->find('//table[starts-with(@class, "table table-striped spec_table")]')->extract();

          //$table = $xs->select('//*[@id="borda_bai"][1]');
          $result = array();
          $row = 0;
          foreach ($table->select('tr[position()>1]') as $tr) {
              $row++;
              $column = 0;
              foreach ($tr->select('td') as $td) {
               $column++;
               $result[$row][$column] = $td->extract();
              }
          }
          echo "<pre>";
          var_dump($result);
          die();
        //  $allInfo["title"] = trim($dom->find('//*[@id="offerdescription"]/div[2]/h1')->extract());
        //  $allInfo["city"]  = $dom->findOneOrNull('//*[@id="offerdescription"]/div[2]/div[1]/a')->extract();
        //table table-striped spec_table
          return $allInfo;
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
$url = "http://www.aquamonolit.ru/kupit/oborudovanie-dlya-bassejnov";

$allCategories = $info->getAllCategories($client,$url);

$allInfo = [];
for ($a=0; $a<count($allCategories); $a++) {
  $allUrl[$allCategories[$a]] = $info->getAllAdvert($client,$allCategories[$a]);
  break;
}

//делаем двойной перебор нового массива

foreach ($allUrl as $url_one) {

    for ($a=0; $a<count($url_one); $a++) {
        $res = $info->getAllInfo($client,$url_one[$a]);
        echo "<pre>";
        var_dump($res);
        die();
    }
}
/*
for ($i=1; $i<=34; $i++){

    echo 'Итерация:'.$i."\n";
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
*/
echo 'ti';
