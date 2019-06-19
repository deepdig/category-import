<?php
/*
    Скрипт для обновления родительских категорий ресурсов (товаров)
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

// получаем все ресурсы c шаблоном (id = 6)
$where = array(    
    'template' => 6
    );
$resources = $modx->getCollection('modResource',$where);

foreach ($resources as $id => $res) {
    // получаем значение поля "guidextparent"
    $pagetitle = $res->get('pagetitle');
    $parentKey = $res->getTVValue('guidextparent');
    //echo $id . ' - ' . $pagetitle .  ' - ' . $parentKey . '<br>';
    if(!empty($parentKey)) {
        // ищем родителя ресурса по полю "guidext"
        $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "'.$parentKey.'"'; // выборка из таблицы
        $statement = $modx->query($sql);
        if ($statement) {
            $info = $statement->fetchAll(PDO::FETCH_ASSOC);    
            $resourceID = $info[0]['contentid']; // id родителя
        }
        //echo 'id родителя: ' . $resourceID . '<br>';
        if (!empty($resourceID)) {
            $res->set('parent', $resourceID);
            $res->save();
        } else {
            echo 'Ресурс отсутствует<br>';   
        }
    }
}

echo '<pre>';
echo "\nUpdate complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows: $rows\n";
echo "Updated:  $updated\n";
echo '</pre>';
