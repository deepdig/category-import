<?php
/*
    Скрипт для поиска и обновления ресурсов minishop2 с нулевым родителем.
    Кучеров М.А 2019. https://github.com/deepdig
*/

set_time_limit(36000); // 10 часов лимит времени
ini_set('memory_limit', '2048M'); // лимит памяти

ini_set("display_errors",1);
error_reporting(E_ALL);
header("Content-Type: text/html; charset=utf-8");

define('MODX_API_MODE', true);
//require_once('/home/g/g70573wf/new_sablemarket/public_html/index.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/index.php');
$modx = new modX();
$modx->initialize('web');

$mainParent = 2; // Основной контейнер каталога

// Выборка по родительской категории
$resourceIDs = $modx->runSnippet('msProducts', array(    
    'limit' => 0,
    'level' => 10,
    'returnIds' => 1,
    'includeTVs' => 'guidext',
    'where' => '{"parent:=":0}', // ищем товары с неопределенным родителем
    //'where' => '{"parent:>":578}', // ищем товары с неопределенным родителем, а теперь с родителем больше 578
));

$resourceIDsArr = explode(",", $resourceIDs);

if ($resourceIDsArr) {
    //print_r($resourceIDsArr);

    $tv_id=1; // выбираем все TV с ID=1    
    $tvs = $modx->getCollection('modTemplateVarResource', array('tmplvarid'=>$tv_id)); 
    //массив для ID ресурсов
    $arr_res = array(); 

    foreach ($resourceIDsArr as $id) {

        //echo  $id . '<br>';

        // ПОЛУЧАЕМ НУЖНЫЙ РЕСУРС
        $product = $modx->getObject('msProduct', $id);
        $parentUid = $product->getTVValue('guidextparent');
        //$tv = 'b80b1764-3ffb-11e8-a200-88d7f6d608b5';
        //echo  $tv . '<br>';
        // Ищем родителя        
        //перебираем TV 
        foreach ($tvs as $tv) { 
        //если значение нашего TV = 1 тогда 
            if ($tv->value==$parentUid) { 
                //выводим ID ресурса
                $parentID = $tv->contentid;
                break;
            }
        }

        $product->set('parent', $parentID); // записываем правильного родителя             
        $product->save();
    }
} else {
    echo 'ресурсы с нулевым родителем не найдены';
}

echo '<pre>';
echo "\nImport complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows: $rows\n";
echo "Updated:  $updated\n";
echo '</pre>';
