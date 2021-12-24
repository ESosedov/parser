<?php

set_time_limit(-1);
ignore_user_abort(true);

include_once __DIR__ . '/phpquery.php';
include_once __DIR__ . '/getPage.php';

const HOST = 'localhost';
const USER = 'root';
const PASS = '';
const DB_NAME = 'local';

try {
    $DB = new PDO('mysql:host='.HOST.';dbname='.DB_NAME, USER, PASS);
    $DB->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    throw new Exception('No base connection: ' . $e->getMessage());
}


class Parse
{

    public function __construct($DB)
    {
        $this->DB = $DB;
        $this->getPage = new getPage();
    }

    public function startParsing($url)
    {
        $html = $this->getPage->exec($url);
        $html = iconv('windows-1251', 'utf-8//TRANSLIT//IGNORE', $html);

        $doc = phpQuery::newDocument($html);

        $categories = $doc->find('selector a');
        foreach ($categories as $li) {
            $subcategory = trim(pq($li)->text());
            $href = pq($li)->attr('href');

            $cats1 = '' . '' . '-' . '' . ' ' . ' ';
            $cats2 = ' ' . ' ' . ' ' . ' ' . ' ';
            $cats3 = '' . ' ' . '' . ' ' . ' ' . ' ' . '' . ' ';
            $cats4 = '' . '  ' . ' ' . ' ' . '  ' . '';
            $cats5 = '';
            if($this->checkSupCategory($subcategory)) {

                if (!(mb_stripos($cats1, $subcategory) === false)) {
                    $cat_title = 'ИмяПодкатеория1';
                } elseif (!(mb_stripos($cats2, $subcategory) === false)) {
                    $cat_title = 'ИмяПодкатеория2';
                } elseif (!(mb_stripos($cats3, $subcategory) === false)) {
                    $cat_title = 'ИмяПодкатеория3';
                } elseif (!(mb_stripos($cats4, $subcategory) === false)) {
                    $cat_title = 'ИмяПодкатеория4';
                } elseif (!(mb_stripos($cats5, $subcategory) === false)) {
                    $cat_title = 'ИмяПодкатеория5';
                }

                if ($this->checkCategoryLoaded($cat_title)) {

                    $this->parseCategory($href, $cat_title);
                }
            }
        }

        die();
    }

    private function checkCategoryLoaded($cat_title)
    {

        if ($cat_title == 'имяКатегоря') {

//            $query = $this->DB->prepare('SELECT id FROM groups WHERE name = :name');
//            $query->execute(array('name' => $cat_title));
//            $result = $query->fetch();
//
//            if (!empty($result)) {
//                $query = $this->DB->prepare('SELECT id FROM groups WHERE id > :id');
//                $query->execute(array('id' => $result['id']));
//                $result = $query->fetch();
//                if (!empty($result)) {
//                    return false;
//                }
//            }
            return true;
        }
        return false;
    }

    private function checkSupCategory($subcategory)
    {
        if ($subcategory == 'имяПодкатегория№') {
            return true;
        }
        return false;
    }

    private function parseCategory($href, $cat_title)
    {

        $url = '' . $href;
        $html = $this->getPage->exec($url);
        $html = iconv('windows-1251', 'utf-8//TRANSLIT//IGNORE', $html);

        $doc = phpQuery::newDocument($html);
        $entries = $doc->find('div[style="line-height:18px;"]');
        $entries = $entries->find('a');
        foreach ($entries as $link) {
            $href = pq($link)->attr('href');
            if ($this->checkCurrentProductHref($href)) {

                $this->parsePage($href, $cat_title);
            }


        }
    }

    private function checkCurrentProductHref($href)
    {
        $pos = strpos($href, 'catalog');
        if ($pos === false) {
            return false;
        }
        return true;
    }

    private function parsePage($href, $cat_title)
    {

        $pre_product_data = array(
            'out_link' => $href
        );

        $url = '' . $href;
        $html = $this->getPage->exec($url);
        $html = iconv('windows-1251', 'utf-8//TRANSLIT//IGNORE', $html);
        $doc = phpQuery::newDocument($html);


        /// BREADCRUMBS ///
        $pre_product_data['group_path'] = array();
        $pre_product_data['group_path'][0] = $cat_title;

        $start = false;
        $entries = $doc->find('.bread a');
        foreach ($entries as $item) {
            $ent = pq($item);
            $name = trim($ent->text());

            if ($start) {
                $pre_product_data['group_path'][] = $name;
            }
            if ($name == 'Каталог') {
                $start = true;
            }

        }
        $entries = $doc->find('.info p a')->text();
        $brand = str_replace('О ', '', $entries);
        $pre_product_data['group_path'][] = $brand;

        ///END BREADCRUMBS ///

        /// DESCRIBE ///
        $entries = $doc->find('#opb');
        foreach ($entries as $item) {
            $p = trim(pq($item)->find('p')->text());
            $description .= $p . "\r" . "\n";
        }

        $pre_product_data['description'] = $description;

        if (($this->IsNullOrEmptyString($description))) {
            $entry = $doc->find('.tech');
            $entry->find('.char')->remove();
            $describe = pq($entry)->text();
            $entries = explode('', $describe);
            $entries = array_slice($entries, 1);
            foreach ($entries as $item) {
                $str = trim($item);
                $description .= $str . "\r" . "\n";

            }
            $pre_product_data['description'] = $description;
        }
        ///END DESCRIBE ///

        /// IMG ///
        $images = array();
        $main_image = $doc->find('.gallery img')->attr('src');
        $pre_product_data['img'] = $main_image;
        $images[0] = $main_image;

        $this->getProductData($href, $pre_product_data);


    }

    private function getProductData($href, $pre_product_data)
    {

        $product_data = $pre_product_data;

        $url = '' . $href;
        $html = $this->getPage->exec($url);
        $html = iconv('windows-1251', 'utf-8//TRANSLIT//IGNORE', $html);
        $doc = phpQuery::newDocument($html);

        $properties = array();
        $outLinkForAll = $product_data['out_link'];

        $entries = $doc->find('.info #mode option');
        foreach ($entries as $item) {
            $idSelect = pq($item)->attr('value');

            ///  TITLE ///
            $entries = $doc->find('.info #blok' . $idSelect); //таблица по idSelect

            $title = trim($doc->find('.info #'.$idSelect)->text());

            $product_data['title'] = $title;
            $product_data['url'] = $this->str2url($title);

            /// END TITLE ///

            /// out_link ///
            $product_data['out_link'] =$outLinkForAll.$product_data['url'];
            /// END out_link ///
            if (!$this->findProduct($product_data['out_link'])) {

                /// PROPERTIES ///

                $prop = $entries->find('$tr');
                $counter = 0;
                foreach ($prop as $item) {
                    $tr = pq($item);
                    $name = trim($tr->find('td:first')->text());
                    $value = trim($tr->find('td:last')->text());

                    $properties[$counter]['name'] = $name;
                    $properties[$counter]['value'] = $value;
                    $counter++;
                }
                $product_data['properties'] = $properties;
                /// IMG ///
                $images[0] = $product_data['img'];
                try {
                    $this->DB->beginTransaction();

                    $query = $this->DB->prepare('INSERT INTO products (title, url, description, out_link) VALUES (:title, :url, :description, :out_link)');
                    $query->execute(array(
                        'title' => $product_data['title'],
                        'url' => $product_data['url'],
                        'description' => $product_data['description'],
                        'out_link' => $product_data['out_link']
                    ));
                    $id = $this->DB->lastInsertID();

                    if ($id > 0) {
                        $group_id = 0;
                        foreach ($product_data['group_path'] as $group) {
                            $query = $this->DB->prepare('SELECT id FROM groups WHERE name = :name');
                            $query->execute(array('name' => $group));
                            $result = $query->fetch();

                            if (!empty($result)) {
                                $group_id = $result['id'];
                            } else {

                                $query = $this->DB->prepare('INSERT INTO ggroups (name, url, parent) VALUES (:name, :url, :parent)');
                                $query->execute(array('name' => $group, 'url' => $this->str2url($group), 'parent' => $group_id));
                                $group_id = $this->DB->lastInsertId();
                            }
                        }
                        $query = $this->DB->prepare('INSERT INTO products_groups (product_id, group_id) VALUES (:product_id, :group_id)');
                        $query->execute(array('product_id' => $id, 'group_id' => $group_id));

                        $this->addProperties($id, $properties, $group_id);
                        //$this->addImages($id, $images);

                        $this->DB->commit();

                    }
                } catch (PDOException $e) {
                    $this->DB->rollback();
                    throw new Exception('Transaction failed: ' . $e->getMessage());
                    exit();
                }
            }
        }
        sleep(2);
    }


    function findProduct($href)
    {
        $query = $this->DB->prepare('SELECT * FROM products WHERE out_link = :out_link');
        $query->execute(array('out_link' => $href));
        $result = $query->fetch();

        if (!empty($result)) {
            return $result['id'];
        } else {
            return false;
        }
    }

    private
    function addProperties($id, $properties, $group_id)
    {
        if (!empty($properties)) {
            foreach ($properties as $prop_array) {
                $name = $prop_array['name'];
                $char_id = $this->getProductCharId($name, $group_id);
                if (!empty($char_id)) {
                    $value_id = $this->recordProductCharValue($char_id, $prop_array['value']);
                    $this->recordProductValue($id, $value_id);
                }
            }
        }
    }

    private
    function getProductCharId($name, $group_id)
    {
        $data = array('name' => $name);
        $query = $this->DB->prepare('SELECT id FROM properties WHERE name = :name');
        $query->execute($data);
        $char = $query->fetch();
        if (empty($char)) {
            $query = $this->DB->prepare('INSERT INTO properties (name) VALUES (:name)');
            $query->execute($data);
            $id = $this->DB->lastInsertId();
        } else {
            $id = $char['id'];
        }

        $this->connectPropertyWithGroup($id, $group_id);

        return $id;
    }

    private
    function connectPropertyWithGroup($id, $group_id)
    {
        $query = $this->DB->prepare('SELECT * FROM property_group WHERE property_id = :pid AND group_id = :gid');
        $query->execute(array('pid' => $id, 'gid' => $group_id));
        $result = $query->fetch();
        if (empty($result)) {
            $query = $this->DB->prepare('INSERT INTO property_group (property_id, group_id) VALUES (:pid, :gid)');
            $query->execute(array('pid' => $id, 'gid' => $group_id));
        }
    }

    private
    function recordProductCharValue($property_id, $value_title)
    {
        $data = array('property' => $property_id, 'value' => $value_title);
        $query = $this->DB->prepare('SELECT id FROM p_values WHERE property = :property AND value = :value');
        $query->execute($data);
        $char = $query->fetch();
        if (empty($char)) {
            $query = $this->DB->prepare('INSERT INTO p_values (property, value) VALUES (:property, :value)');
            $query->execute($data);
            $id = $this->DB->lastInsertId();
        } else {
            $id = $char['id'];
        }

        return $id;
    }

    private
    function recordProductValue($product, $value_id)
    {
        $data = array('product' => $product, 'value' => $value_id);
        $query = $this->DB->prepare('INSERT INTO property_product (product, value) VALUES (:product, :value)');
        $query->execute($data);
    }

    private function addImages($id, $images)
    {
        if (!empty($images)) {
            $i = 1;
            foreach ($images as $image) {
                if (!empty($image)) {
                    $new_file_name = $id . '-' . $i . '.jpg';
                    if ($this->setImage($id, $image, $new_file_name) !== false) {
                        $i++;
                        if ($i == 2) {
                            $the_image = $new_file_name;
                            $this->setMainImage($id, $the_image);
                        }
                    }
                }
            }
        }
    }

    private function setMainImage($id, $image)
    {
        $query = $this->DB->prepare('UPDATE products SET image = :image WHERE id = :id');
        $query->execute(array('image' => $image, 'id' => $id));
    }

    private function setImage($id, $image_path, $filename)
    {
        $structure = $_SERVER['DOCUMENT_ROOT'] . '/imgs/products/' . $id . '/';
        if (!is_dir($structure)) {
            $oldmask = umask(0);
            mkdir($structure, 0777, true);
            umask($oldmask);
        }

        $structure = $_SERVER['DOCUMENT_ROOT'] . '/imgs/products/' . $id . '/thumb/';
        if (!is_dir($structure)) {
            $oldmask = umask(0);
            mkdir($structure, 0777, true);
            umask($oldmask);
        }

        $structure = $_SERVER['DOCUMENT_ROOT'] . '/imgs/products/' . $id . '/original/';
        if (!is_dir($structure)) {
            $oldmask = umask(0);
            mkdir($structure, 0777, true);
            umask($oldmask);
        }

        $context = stream_context_create(
            array(
                'http' => array(
                    'header' => array(
                        'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; rv:2.2) Gecko/20110201'
                    ),
                ),
            )
        );

        if (copy(
            $image_path,
            ROOT . '/imgs/products/' . $id . '/original/' . $filename . '',
            $context
        )) {
            resizeImage(
                ROOT . '/imgs/products/' . $id . '/original/' . $filename . '',
                ROOT . '/imgs/products/' . $id . '/thumb/' . $filename . ''
            );

            mainResizeImage(
                ROOT . '/imgs/products/' . $id . '/original/' . $filename . '',
                ROOT . '/imgs/products/' . $id . '/' . $filename . ''
            );

            return true;
        } else {
            return false;
        }
    }


    private function mb_ucfirst($string, $enc = 'UTF-8')
    {
        return mb_strtolower(mb_substr($string, 0, 1, $enc), $enc) .
            mb_substr($string, 1, mb_strlen($string, $enc), $enc);
    }


    private function rus2translit($string)
    {
        $converter = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v',
            'г' => 'g', 'д' => 'd', 'е' => 'e',
            'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
            'и' => 'i', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'h', 'ц' => 'c',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch',
            'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

            'А' => 'A', 'Б' => 'B', 'В' => 'V',
            'Г' => 'G', 'Д' => 'D', 'Е' => 'E',
            'Ё' => 'E', 'Ж' => 'Zh', 'З' => 'Z',
            'И' => 'I', 'Й' => 'Y', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'С' => 'S', 'Т' => 'T', 'У' => 'U',
            'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch',
            'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',

            '½' => '1-2', '©' => 'copyright', '®' => 'original',
            '¼' => '1-4', '⅓' => '1-3', '¾' => '3-4', 'ø' => 'diametr', 'Ø' => 'diametr', '²' => '2', '³' => '3'
        );
        return strtr($string, $converter);
    }

    private function str2url($str)
    {
        $str = str_replace("'", "", $str);
        $str = str_replace("\"", "", $str);
        $str = str_replace(" ", "-", $str);
        $str = strip_tags($str);
        $str = $this->rus2translit($str);
        $str = strtolower($str);
        $str = preg_replace('~[^-a-z0-9_]+~u', '-', $str);
        $str = trim($str);
        return $str;
    }

    private function print_arr($arr)
    {
        echo '<pre><hr>' . print_r($arr, true) . '</pre><hr>';
    }

    private function IsNullOrEmptyString($str)
    {
        return (!isset($str) || trim($str) === '');
    }

}


$url = '';
$parse = new Parse($DB);
$parse->startParsing($url);


use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\ImageInterface;

$imagine = new Imagine\Gd\Imagine();


function resizeImage($get_image, $set_image)
{

     global $imagine;

     $image = $imagine->open($get_image);
     $size = $image->getSize();
     $width = $size->getWidth();
     $height = $size->getHeight();
     if ($width > 600) {
         $coefficient = round($width / 600, 2);
         $width = $width / $coefficient;
         $height = $height / $coefficient;
         $mode = Imagine\Image\ImageInterface::THUMBNAIL_INSET;
         $image->thumbnail(new Box($width, $height), $mode);
         $image->save($set_image, array('flatten' => false));
     } else {
    copy($get_image, $set_image);
     }

     //convertToWebp($set_image);
}

function mainResizeImage($get_image, $set_image)
{

     global $imagine;
   $image = $imagine->open($get_image);
     $size = $image->getSize();

     $width = $size->getWidth();
     $height = $size->getHeight();

     if ($width > 1900) {
         $coefficient = round($width / 1900, 2);
         $width = $width / $coefficient;
         $height = $height / $coefficient;
         $mode = Imagine\Image\ImageInterface::THUMBNAIL_INSET;
         $image->thumbnail(new Box($width, $height), $mode);
         $image->save($set_image);
     } elseif ($height > 1200) {
         $coefficient = round($height / 1200, 2);
         $height = $height / $coefficient;
         $width = $width / $coefficient;
         $mode = Imagine\Image\ImageInterface::THUMBNAIL_INSET;
         $image->thumbnail(new Box($width, $height), $mode);
         $image->save($set_image);
     } else {
    copy($get_image, $set_image);
     }}