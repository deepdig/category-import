<?php

set_time_limit(1440);

ini_set("display_errors",1);
error_reporting(E_ALL);
header("Content-Type: text/html; charset=utf-8");

define('MODX_API_MODE', true);
//require_once('/home/g/g70573wf/new_sablemarket/public_html/index.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/index.php');
$modx = new modX();
$modx->initialize('web');

// загружаем файл импорта из csv
//$file = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_tovar_last.csv'; // имя файла
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
    $productPrice = $csv[3];           // цена (price)
    $productNew = $csv[4];             // для формирования блока "Новинки" (new)
    $productTopSale = $csv[5];         // для формирования блока "Лидеры продаж" (topSale)
    $productProfitPrice = $csv[6];     // для формирования блока "Выгодная цена" (profit_price)
    $productOrder = $csv[7];           // для индентификации товара "Под заказ" (order)
    $productID = $csv[8];              // идентификатор ресурса (GUIDExt)
    $productParent = $csv[9];          // родительский ресурс (GUIDExtParent)
    $productIDAlso = $csv[10];          // список UID товаров через запятую для формирования вкладки "Вам могут понадобиться" (GUIDExt_also)
    
    if ($productName != 'Names') {

        // проверяем наличие ресурса в каталоге
        $result = $modx->runSnippet('pdoResources', array(
            'parents' => $mainParent,
            'limit' => 0,
            'level' => 10,
            'returnIds' => 1,
            'includeTVs' => 'guidext',
            'where' => '{"guidext:LIKE":"' . $productID . '", "OR:pagetitle:LIKE":"' . $productName . '"}',
        ));

        // если ресурс не создан, то создаем
        if (empty($result)) {

            echo 'ресурс отсутствует в каталоге<br>';
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

            // ищем родительский каталог для данного товара
            $parentID = $modx->runSnippet('pdoResources', array(
                'parents' => $mainParent,
                'limit' => 0,
                'level' => 10,
                'returnIds' => 1,
                'includeTVs' => 'guidext',
                'where' => '{"guidext:LIKE":"' . $productParent . '"}',
            ));

            // проверка на наличие родительского ресурса
            if (empty($parentID)) {

                echo 'Родительский ресурс отсутствует<br>';
            } else {

                echo ' ID родительского ресурса: '. $parentID . '<br><br>';
            }

        } else {

            echo $result . '<br>';

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
