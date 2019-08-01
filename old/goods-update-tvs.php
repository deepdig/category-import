<?php

/*
    Скрипт для обновления tv-полей ресурсов (товаров)
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

// загружаем файл импорта из csv
//$file = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_tovar_5.csv'; // имя файла
$file = $_SERVER['DOCUMENT_ROOT'] . '/ajax/category-import/import/test_tovar.csv'; // имя файла
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
    $productPrice = preg_replace("/[^x\d|*\.]/", "", $csv[3]);           // цена (price) удаляем пробелы
    $productNew = $csv[4];             // для формирования блока "Новинки" (new)
    $productTopSale = $csv[5];         // для формирования блока "Лидеры продаж" (topSale)
    $productProfitPrice = $csv[6];     // для формирования блока "Выгодная цена" (profit_price)
    $productOrder = $csv[7];           // для индентификации товара "Под заказ" (order)
    $productID = $csv[8];              // идентификатор ресурса (GUIDExt)
    $productParent = $csv[9];          // родительский ресурс (GUIDExtParent)
    $productIDAlso = $csv[10];          // список UID товаров через запятую для формирования вкладки "Вам могут понадобиться" (GUIDExt_also)
    
    if ($productName != 'Names') {

        // если ресурс не 000000
        if ($productParent != '00000000-0000-0000-0000-000000000000') {
        
            /*
            echo 'Название продукта: '   . $productName . '<br>';
            echo 'Описание продукта: '   . $productContent . '<br>';
            echo 'Остатки: '             . $productRemains . '<br>';
            echo 'Цена: '                . $productPrice . '<br>';
            echo 'Новинка?: '            . $productNew . '<br>';
            echo 'Лидер продаж?: '       . $productTopSale . '<br>';
            echo 'Выгодная цена?: '      . $productProfitPrice . '<br>';
            echo 'Под заказ?: '          . $productOrder . '<br>';
            echo 'ID продукта: '         . $productID . '<br>';
            echo 'Родительский ресурс: ' . $productParent . '<br>';
            echo 'Вам могут понадобиться: ' . $productIDAlso . '<br>';  
            */          
            
            // ищем ID ресурса по "pagetitle", который будем потом обновлять
            $sql = 'SELECT * FROM `modx_sablmse2_intro` WHERE intro="'.$productName.'"';
            $statement = $modx->query($sql);
            if ($statement) {
                $info = $statement->fetchAll(PDO::FETCH_ASSOC);    
                $resourceID = $info[0]['resource'];
            }
            
            if (!empty($resourceID)) {
                
                $product = $modx->getObject('modResource', $resourceID);
              //  $product = $modx->getObject('msProduct', $resourceID);
              //  $product->set('price', $productPrice); // записываем цену
              //  $product->set('article', $productID);
                $product->setTVValue('guidext', $productID);
                $product->setTVValue('guidextparent', $productParent);
                $product->save();
                
            
            } else {
                //echo 'Ресурс отсутствует<br>';   
            }
            
           
        } elseif ($productParent == '00000000-0000-0000-0000-000000000000') { // если родитель не указан

            //echo 'ресурс <b>' . $productName . '</b> не имеет родительского каталога <br>';
            
        } else { // если ресурс уже есть в каталоге проверяем товар на изменение позиции 

            //echo 'ресурс ID:' . $result . ' <b>' . $productName . '</b> - уже есть в каталоге <br>';
           // echo 'Ok! <br>';

        }
    }

    $updated++;
}

fclose($handle);


echo '<pre>';
echo "\nUpdate complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows: $rows\n";
echo "Updated:  $updated\n";
echo '</pre>';
