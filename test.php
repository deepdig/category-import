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
$file = $_SERVER['DOCUMENT_ROOT'] . '/ajax/category-import/import/my_tovar_last.csv'; // имя файла
$delimeter = '||'; // разделитель
$delimeterEnd = '^'; // разделитель строк

$handle = fopen($file, "r");
$rows = $updated = 0;

//цикл для сбора данных из csv
while (($csv = fgetcsv($handle, 0, $delimeter, $delimeterEnd)) !== false) {
    
    $rows++;
    
    if ($productName != 'Names') {

        // проверяем наличие ресурса в каталоге
        $result = $modx->runSnippet('pdoResources', array(
            'parents' => $mainParent,
            'limit' => 0,
            'level' => 10,
            'returnIds' => 1,
            'includeTVs' => 'guidext',
            'where' => '{"guidext:LIKE":"' . $productID . '"}',
        ));

        if (empty($result)) {

            echo '$result - пустой';

        } else {

            echo $result;

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
