<?php
set_time_limit(720);

define('MODX_API_MODE', true);
require_once($_SERVER['DOCUMENT_ROOT'].'/index.php');
$modx=new modX();
$modx->initialize('web');


// получаем имеющиеся ресурсы
$where = array(
    'parent' => 0 // родительский каталог
    );
$resources = $modx->getCollection('modResource',$where);
foreach ($resources as $k => $res) {

    $res->remove(); //удаляем
}

echo '<pre>';
echo "\nDelete complete in ".number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows: $rows\n";
echo "Updated:  $updated\n";
echo '</pre>';