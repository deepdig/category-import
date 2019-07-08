<?php
/*
    Скрипт для импорта картинок в msgallery
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

// Подключение нужных служб
$modx->getService('error','error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');
// Подключение ms2Gallery
$modx->ms2Gallery = $modx->getService('ms2gallery', 'ms2Gallery', MODX_CORE_PATH . 'components/ms2gallery/model/ms2gallery/');


// Директория с файлами
$base = MODX_BASE_PATH . 'assets/img_katalog/';

// получаем все ресурсы c шаблоном (id = 6 - товар)
$where = array(    
    'template' => 6
    );
$resources = $modx->getCollection('modResource',$where);


foreach ($resources as $id => $res) {
    $modx->error->reset(); // Сброс ошибок  
    
    if ($id == 1207) {
        $uid = $res->getTVValue('guidext'); // получаем ключ товара
        // загружаемые файлы
        $dir_imgs = $base . $uid . '/';
        
        foreach (array_diff( scandir($dir_imgs), array('..', '.')) as $file_img) {
            $all_img[] = $dir_imgs . $file_img;
        }
        
        //print_r($all_img);
        
        foreach($all_img as $value){
            $data = [
                'id' => $id, // id - ресурса
                'file' => $value, // путь к картинке
            ];
            // Вызов процессора загрузки
            $response = $modx->runProcessor('gallery/upload', $data, [
                'processors_path' => MODX_CORE_PATH . 'components/minishop2/processors/mgr/',
            ]);

            // Вывод результата работы процессора
            if ($response->isError()) {
                print_r($response->getAllErrors());
            }
        }
    }  
}


echo '<pre>';
echo "\nImport complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows: $rows\n";
echo "Updated:  $updated\n";
echo '</pre>';
