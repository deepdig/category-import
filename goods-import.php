<?php
/*
    Скрипт для импорта и обновления товаров из csv в minishop2
*/

set_time_limit(36000); // 10 часов лимит времени
ini_set('memory_limit', '2048M'); // лимит памяти

ini_set("display_errors",1);
error_reporting(E_ALL);
header("Content-Type: text/html; charset=utf-8");

define('MODX_API_MODE', true);
require_once('/home/g/g70573wf/new_sablemarket/public_html/index.php');
//require_once($_SERVER['DOCUMENT_ROOT'] . '/index.php');
$modx = new modX();
$modx->initialize('web');

// Функция записи массива в лог
function object2file($value, $filename)
{
	$str_value = "\n".serialize($value);
	
	$f = fopen($filename, 'w');
	fwrite($f, $str_value);
	fclose($f);
}

// Функция транслитерации
function translit($s)
{
    $s = (string)$s; // преобразуем в строковое значение
    $s = strip_tags($s); // убираем HTML-теги
    $s = str_replace(array("\n", "\r"), " ", $s); // убираем перевод каретки
    $s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
    $s = trim($s); // убираем пробелы в начале и конце строки
    $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр (иногда надо задать локаль)
    $s = strtr($s, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
    $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s); // очищаем строку от недопустимых символов
    $s = str_replace(" ", "-", $s); // заменяем пробелы знаком минус
    return $s; // возвращаем результат
}

// загружаем файл импорта из csv
$file = MODX_BASE_PATH . 'ajax/import/my_tovar_7.csv'; // имя файла
//$file = $_SERVER['DOCUMENT_ROOT'] . '/ajax/category-import/import/my_tovar_5.csv'; // имя файла
$delimeter = '|'; // разделитель
$delimeterEnd = '^'; // разделитель строк
$mainParent = 2; // Основной контейнер каталога

$handle = fopen($file, "r");
$rows = $updated = 0;

//цикл для сбора данных из csv
while (($csv = fgetcsv($handle, 0, $delimeter, $delimeterEnd)) !== false) {
    
    $rows++;

    // определяем в переменные столбцы из файла    
    $productName = $csv[0];            // Название ресурса (Names)
    $productContent = $csv[1];         // Описание товара (content)
    $productRemains = $csv[2];         // Остатки (remains)
    $productPrice = preg_replace("/[^x\d|*\.]/", "", $csv[3]);           // цена (price)  удаляем пробелы
    $productNew = $csv[4];             // для формирования блока "Новинки" (new)
    $productTopSale = $csv[5];         // для формирования блока "Лидеры продаж" (topSale)
    $productProfitPrice = $csv[6];     // для формирования блока "Выгодная цена" (profit_price)
    $productOrder = $csv[7];           // для индентификации товара "Под заказ" (order)
    $productID = $csv[8];              // идентификатор ресурса (GUIDExt)
    $productParent = $csv[9];          // родительский ресурс (GUIDExtParent)
    $productDopCategory = $csv[10];    // доп.категории товара (GUIDExtParents)
    $productIDAlso = $csv[11];         // список UID товаров через запятую для формирования вкладки "Вам могут понадобиться" (GUIDExt_also)
    $vendor = $csv[12];                // фильтр по производителю (Filter1)
    $filter2 = $csv[13];               // (Filter2)
    $filter3 = $csv[14];               // (Filter3)
    $filter4 = $csv[15];               // (Filter4)
    $filter5 = $csv[16];               // (Filter5)
    
    if ($productName != 'Names') {

        // проверяем наличие ресурса в каталоге
        $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "'.$productID.'"'; // выборка из таблицы
        $statement = $modx->query($sql);
        if ($statement) {
            $info = $statement->fetchAll(PDO::FETCH_ASSOC);    
            $resourceID = $info[0]['contentid']; // id
        }

        // если ресурс не создан, то создаем
        if (empty($resourceID) and $productParent != '00000000-0000-0000-0000-000000000000') {
                    
            /*
            echo 'Название продукта: '      . $productName . '<br>';
            echo 'Описание продукта: '      . $productContent . '<br>';
            echo 'Остатки: '                . $productRemains . '<br>';
            echo 'Цена: '                   . $productPrice . '<br>';
            echo 'Новинка?: '               . $productNew . '<br>';
            echo 'Лидер продаж?: '          . $productTopSale . '<br>';
            echo 'Выгодная цена?: '         . $productProfitPrice . '<br>';
            echo 'Под заказ?: '             . $productOrder . '<br>';
            echo 'ID продукта: '            . $productID . '<br>';
            echo 'Родительский ресурс: '    . $productParent . '<br>';
            echo 'Вам могут понадобиться: ' . $productIDAlso . '<br>';
            echo 'Производитель: '          . $vendor . '<br>';     
            */
            
            // ищем родительский каталог для данного товара
            $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "'.$productParent.'"'; // выборка из таблицы
            $statement = $modx->query($sql);
            if ($statement) {
                $info = $statement->fetchAll(PDO::FETCH_ASSOC);    
                $parentID = $info[0]['contentid']; // id
            }
            
            //echo 'Родительский ресурс ' . $parentID . '<br>';


            // проверка на наличие родительского ресурса
            if (empty($parentID)) {

                echo 'Родительский ресурс отсутствует у товара:' . $productName . '<br>';
            } else {                

                $response = $modx->runProcessor('resource/create', array(
                    'template' => 6, // шаблон с товаром
                    'isfolder' => 0, // это не контейнер
                    'published' => 1, // опубликован
                    'parent' => $parentID,
                    'pagetitle' => $productName,
                    //'alias' => $rows .'-'. translit($productName),
                    'content' => $productContent,
                    // основные параметры msProduct
                    'class_key' => 'msProduct', // указываем что это товар minishop
                    'show_in_tree' => 0, // не показывать товар в древе ресурсов
                    'article' => $productID, // Артикул
                    'price' => $productPrice, // Цена
                    'new' => $productNew, // Новый товар
                    'popular' => $productTopSale, // Популярный товар
                    'vendor' => $vendor, // производитель
                    // устанавливаем TV поля
                    'tv1' =>  $productID,          // TV - guidext
                    'tv2' =>  $productParent,      // TV - guidextparent
                    'tv5' =>  $productRemains,     // TV - product-remains
                    'tv6' =>  $productPrice,       // TV - product-price
                    'tv7' =>  $productNew,         // TV - product-new
                    'tv8' =>  $productTopSale,     // TV - product-topSale
                    'tv9' =>  $productProfitPrice, // TV - product-profitPrice
                    'tv10' => $productOrder,       // TV - product-order
                    'tv11' => $productIDAlso,      // TV - product-also
                    'tv15' => $vendor,             // TV - filter-vendor
                    'tv16' => $productDopCategory  // TV - product-dopCategory
                ));
    
                if ($response->isError()) {
                    //print_r($modx->error->failure($response->getMessage()));
                    $errorArr = $modx->error->failure($response->getMessage());
                    object2file( $errorArr, 'log.txt');
                    file_put_contents('log.txt', PHP_EOL . serialize($errorArr), FILE_APPEND);
                }
                $modx->cacheManager->clearCache(); // очищаем кэш

                // получаем id созданного ресурса
                /*
                $newId = $response->response['object']['id'];
                $page = $modx->getObject('modResource', $newId);
                // записываем псевдоним
                $page->set('alias', $newId . '-' . translit($productName)); // здесь возникает ошибка!!!
                $page->save();
                */

                //echo ' ID родительского ресурса: '. $parentID . '<br>';
                //echo 'ID продукта: '         . $productID . '<br>';
                //echo 'Название продукта: '   . $productName . '<br><br>';
            }      
        
           
        } elseif (empty($resourceID) and $productParent == '00000000-0000-0000-0000-000000000000') { // если родитель не указан и ресурс не создан - создаем в каталоге "Разное"

            //echo 'ресурс <b>' . $productName . '</b> не имеет родительского каталога <br>';
            
            $response = $modx->runProcessor('resource/create', array(
                    'template' => 6, // шаблон с товаром
                    'isfolder' => 0, // это не контейнер
                    'published' => 1, // опубликован
                    'parent' => 23170, // каталог "Разное"
                    'pagetitle' => $productName,
                    //'alias' => $rows .'-'. translit($productName),
                    'content' => $productContent,
                    // основные параметры msProduct
                    'class_key' => 'msProduct', // указываем что это товар minishop
                    'show_in_tree' => 0, // не показывать товар в древе ресурсов
                    'article' => $productID, // Артикул
                    'price' => $productPrice, // Цена
                    'new' => $productNew, // Новый товар
                    'popular' => $productTopSale, // Популярный товар
                    'vendor' => $vendor, // производитель
                    // устанавливаем TV поля
                    'tv1' =>  $productID,          // TV - guidext
                    'tv2' =>  $productParent,      // TV - guidextparent
                    'tv5' =>  $productRemains,     // TV - product-remains
                    'tv6' =>  $productPrice,       // TV - product-price
                    'tv7' =>  $productNew,         // TV - product-new
                    'tv8' =>  $productTopSale,     // TV - product-topSale
                    'tv9' =>  $productProfitPrice, // TV - product-profitPrice
                    'tv10' => $productOrder,       // TV - product-order
                    'tv11' => $productIDAlso,      // TV - product-also
                    'tv15' => $vendor,             // TV - filter-vendor
                    'tv16' => $productDopCategory  // TV - product-dopCategory
                ));
    
                if ($response->isError()) {
                    //print_r($modx->error->failure($response->getMessage()));
                    $errorArr = $modx->error->failure($response->getMessage());
                    object2file( $errorArr, 'log.txt');
                    file_put_contents('log.txt', PHP_EOL . serialize($errorArr), FILE_APPEND);
                }
                $modx->cacheManager->clearCache(); // очищаем кэш
            
        } else { // если ресурс уже есть в каталоге обновляем товар

            //echo 'ресурс ID:' . $result . ' <b>' . $productName . '</b> - уже есть в каталоге <br>';
            
            if($doc = $modx->getObject('modResource', $resourceID)){
                $response = $modx->runProcessor('resource/update', array(
                    'published' => 1, // опубликован
                    'parent' =>    $parentID,
                    'pagetitle' => $productName,
                    'content' =>   $productContent,
                    'article' =>   $productID, // Артикул
                    'price' =>     $productPrice, // Цена
                    'new' =>       $productNew, // Новый товар
                    'popular' =>   $productTopSale, // Популярный товар
                    'vendor' =>    $vendor, // производитель
                    // устанавливаем TV поля
                    'tv1' =>   $productID,          // TV - guidext
                    'tv2' =>   $productParent,      // TV - guidextparent
                    'tv5' =>   $productRemains,     // TV - product-remains
                    'tv6' =>   $productPrice,       // TV - product-price
                    'tv7' =>   $productNew,         // TV - product-new
                    'tv8' =>   $productTopSale,     // TV - product-topSale
                    'tv9' =>   $productProfitPrice, // TV - product-profitPrice
                    'tv10' =>  $productOrder,       // TV - product-order
                    'tv11' =>  $productIDAlso,      // TV - product-also
                    'tv15' =>  $vendor,             // TV - filter-vendor
                    'tv16' =>  $productDopCategory  // TV - product-dopCategory
                ));
                if ($response->isError()) {
                    //print_r($modx->error->failure($response->getMessage()));
                    $errorArr = $modx->error->failure($response->getMessage());
                    object2file( $errorArr, 'log.txt');
                    file_put_contents('log.txt', PHP_EOL . serialize($errorArr), FILE_APPEND);
                }
                $modx->cacheManager->clearCache();
            }
        }
    }

    $updated++;
}

fclose($handle);


echo '<pre>';
echo "\nImport complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows: $rows\n";
echo "Updated:  $updated\n";
echo '</pre>';
