<?php
/*
    Скрипт для теста
    Кучеров М.А 2019. https://github.com/deepdig
*/

set_time_limit(1440);

ini_set("display_errors",1);
error_reporting(E_ALL);
header("Content-Type: text/html; charset=utf-8");

define('MODX_API_MODE', true);
//require_once('/home/g/g70573wf/new_sablemarket/public_html/index.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/index.php');
$modx = new modX();
$modx->initialize('web');

$tv_id=1; 
// выбираем все TV с ID=1
$tvs = $modx->getCollection('modTemplateVarResource', array('tmplvarid'=>$tv_id)); 
//массив для ID ресурсов
$arr_res = array(); 
//перебираем TV 
foreach ($tvs as $tv) { 
//если значение нашего TV = 1 тогда 
if ($tv->value=='b80b1764-3ffb-11e8-a200-88d7f6d608b5') 
//добавляем ID ресурса в массив
$arr_res[] = $tv->contentid; 
}
// возвращаем строку где ID ресурсов разделены
print_r(implode(',',$arr_res));

echo '<pre>';
echo "\nImport complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows: $rows\n";
echo "Updated:  $updated\n";
echo '</pre>';
