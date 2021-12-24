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

    public function addProducts($url)
    {

        $nextPage = $url;
        static $count = 0;

        do {

            $file = $this->getPage->exec($nextPage);
            $doc = phpQuery::newDocument($file);
            $entries = $doc->find('.products .prod_a');

            static $start = false;


            foreach ($entries as $article) {
                $a = pq($article);
                $href = $a->attr('href');
                $this->getProductData($href);

            }
            $next = $doc->find('.pagination .active')->next()->find('a')->attr('href');

            $nextPage = '' . $next;
            $count++;


        } while (!empty($next) and $count < 50);

        die();
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

    function getProductData($href)
    {
        if (!$this->findProduct($href)) {
            $product_data = array(
                'out_link' => $href,
            );

            $url = '' . $href;
            $file = $this->SSLPage->exec($url);
            $doc = phpQuery::newDocument($file);

            /// TITLE ///

            $product_data['title'] = trim($doc->find('h1 .product-name')->text());
            $product_data['url'] = $this->str2url($product_data['title']);

            /// BRAND ///
            $brands = array(
                'brand1' => 'brandTitle1',
                'brand2' => 'brandTitle2',
                '' =>''

            );

            foreach ($brands as $name) {
                if (!(mb_stripos($product_data['title'], $name) === false)) {
                    $brand = $name;
                }
            }
            /// ART ///
            $art = $doc->find('.vendor')->text();
            $product_data['art'] = $art;


            /// PRICE ///

            $product_data['price'] = trim($doc->find('.price:first')->text());
            $product_data['price'] = preg_replace('/[^0-9.]+/', '', $product_data['price']);


            /// IMG ///

            $images = array();
            $isIMG = false;
            $main_image = $doc->find('#foto-product img:first');
            $main_image = '' . $main_image;

            if ($main_image !== '') {
                $images[0] = $main_image;
                $isIMG = true;
            }



            /// BREADCRUMS ///

            $product_data['group_path'] = array();
            $start = true;
            $entry = $doc->find('section.bread')->find('ul.breadcrumbs')->find('li.active');


            foreach ($entry as $item) {
                $li = pq($item);
                $name = trim($li->text());

                if ($start) {
                    $product_data['group_path'][] = $name;
                }
//            if ($name == 'Главная') {
//                $start = true;
//            }
            }
            $product_data['group_path'] = array_slice($product_data['group_path'], 0, -1);
            $product_data['group_path'][] = $brand;

            /// DESCRIBE ///
            $description = $doc->find('.selector')->text();
            $product_data['description'] = $description;
            ///END DESCRIBE ///

            /// PROPERTIES ///

            $properties = array();
            $entry = $doc->find('#selector .menu:first li');
            $count = 0;
            foreach ($entry as $item) {
                $li = pq($item);
                $name = trim($li->find('.name')->text());
                $value = trim($li->find('.data')->text());

                $properties[$count]['name'] = $name;
                $properties[$count]['value'] = $value;
                $count++;
            }
            $product_data['properties'] = $properties;

            /// END PROPERTIES///

            $this->print_arr($product_data);
            $query = $this->DB->prepare('INSERT INTO products (art, title, url, price, description, out_link) VALUES (:art, :title, :url, :price, :description, :out_link)');
            $query->execute(array(
                'art' => $product_data['art'],
                'title' => $product_data['title'],
                'url' => $product_data['url'],
                'price' => $product_data['price'],
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
                if ($isIMG) {
                    $this->addImages($id, $images);
                }
            }
        }
        sleep(2);
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
$DB = DB::getInstance();
$url = '';
$parse = new Parse($DB);
$parse->addProducts($url);


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