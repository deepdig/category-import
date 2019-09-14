<?php
/*
    Скрипт для импорта фотографий в  msGallery
*/

set_time_limit(36000); // 10 часов лимит времени
ini_set('memory_limit', '2048M'); // лимит памяти

ini_set("display_errors", 1);
error_reporting(E_ALL);
header("Content-Type: text/html; charset=utf-8");

define('MODX_API_MODE', true);
require_once('/home/g/g70573wf/new_sablemarket/public_html/index.php');
//require_once($_SERVER['DOCUMENT_ROOT'] . '/index.php');
$modx = new modX();
$modx->initialize('web');

//Подключение служб вывода ошибок
$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget(XPDO_CLI_MODE ? 'ECHO' : 'HTML');

// Подключение ms2Gallery
$modx->ms2Gallery = $modx->getService('ms2gallery', 'ms2Gallery', MODX_CORE_PATH . 'components/ms2gallery/model/ms2gallery/');

// Директория с файлами
$base = MODX_BASE_PATH . 'assets/img_katalog/';

// получаем названия папок с фото, которые являются ключом для идентификации ресурcа
$entries = scandir($base);
$filelist = array();
foreach (array_diff( $entries, array('..', '.')) as $dir_img) {
    
    //echo $dir_img . PHP_EOL;

    // ищем ID ресурса по названию исходной папки с фото 
    $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "' . $dir_img . '"';
    $statement = $modx->query($sql);
    if ($statement) {
        $info = $statement->fetchAll(PDO::FETCH_ASSOC);
        $resourceID = $info[0]['contentid']; // id ресурса
    }
    
    if ($resourceID) {
        //$resource = $modx->getObject('modResource', $resourceID);
        //echo $resource->get('pagetitle');
        
        // удаляем старую галерею ресурса
        $whereDel[] = 0;
        $whereDel = array('product_id' => $resourceID, 'parent' => 0, 'type' => 'image');
        $images = $modx->getCollection('msProductFile', $whereDel);
        
        $dataDel[] = 0;    
        foreach ($images as $image) {
            $dataDel = array('id' => $image->id);
            $response = $modx->runProcessor('gallery/remove', $dataDel, [
                'processors_path' => MODX_CORE_PATH . 'components/minishop2/processors/mgr/',
            ]);
            if ($response->isError()) {
                print_r($response->getAllErrors());
            }
        }
        
        $modx->cacheManager->refresh(); // очищаем кэш
        
        // папка с фото
        $dir_imgs = $base . $dir_img . '/';
            
        if (is_dir($dir_imgs)) {// проверяем наличие папки с фото
            
            foreach (array_diff( scandir($dir_imgs), array('..', '.')) as $file_img) {
                $file = $dir_imgs . $file_img; // полный путь к файлу
                            
                $data = [
                    'id' => $resourceID, // id - ресурса
                    'file' => $file, // путь к картинке
                ];
                // Вызов процессора загрузки
                $response = $modx->runProcessor('gallery/upload', $data, [
                    'processors_path' => MODX_CORE_PATH . 'components/minishop2/processors/mgr/',
                ]);
            
                // Вывод результата работы процессора
                if ($response->isError()) {
                    print_r($response->getAllErrors());
                }
                
                //unlink($file); // удаляем фото в папке
            }
        }
        
        //rmdir($dir_imgs); // удаляем папку с фото
    }
}

$modx->cacheManager->refresh(); // очищаем кэш

echo '<pre>';
echo "\nImport complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows: $rows\n";
echo "Updated:  $updated\n";
echo '</pre>';
