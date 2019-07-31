<?php
/*
    Скрипт для импорта и обновления товаров из csv в minishop2
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

// Функция записи массива в лог
function object2file($value, $filename)
{
    $str_value = "\n" . serialize($value);

    $f = fopen($filename, 'w');
    fwrite($f, $str_value);
    fclose($f);
}

$data = date("m.d.y.H.i.s");

$mainParent = 2; // Основной контейнер каталога

// Директория с фотографиями
$dir_image = MODX_BASE_PATH . 'assets/img_katalog/';

// загружаем файл импорта из csv
$category_file_original = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_category.csv'; // имя файла импорта категорий
$products_file_original = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_tovar_7.csv'; // имя файла импорта товаров
$category_file = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_category_finished_' . $data . '.csv'; // имя переименованного файла категорий
$products_file = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_tovar_7_finished_' . $data . '.csv'; // имя переименованного файла товаров

// проверяем наличие файла импорта категорий. если есть - запускаем процесс обновления
if (file_exists($category_file_original)) {

    rename($category_file_original, $category_file); // переименовываем оригинальный файл, на случай его случайной замены в процессе импорта

    $delimeter = '|'; // разделитель
    $handle = fopen($file, "r");
    $rows = $updated = 0;

    // получаем имеющиеся ресурсы категорий
    $where = array(
        'template:IN' => array(4, 5), // шаблоны каталога
    );
    $resources = $modx->getIterator('modResource', $where);

    if ($resources) {

        //цикл для сбора данных по уровням
        while (($csv = fgetcsv($handle, 0, $delimeter)) !== false) {
            $rows++;

            // определяем в переменные столбцы из файла
            $cid = $csv[0];            // id ресурса - не используется
            $name = $csv[1];            // Название ресурса
            $level = $csv[2];           // Уровень каталога
            $GoodsIn = $csv[3];         // Идентификатор "является ли каталог" хранилищем товаров
            $GUIDExt = $csv[4];         // GUIDExt - идентификатор ресурса
            $GUIDExtParent = $csv[5];   // GUIDExtParent - родительский ресурс

            // ищем совпадения UID по полю "guidext". 
            $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "' . $GUIDExt . '"'; // выборка из таблицы
            $statement = $modx->query($sql);
            if ($statement) {
                $info = $statement->fetchAll(PDO::FETCH_ASSOC);
                $result = $info[0]['contentid']; // id ресурса
            }

            if (!$result) { // если категория не создана, то создаем

                // назначаем ресурсу нужный шаблон
                if ($GoodsIn == 0) {
                    $template = 4;
                } elseif ($GoodsIn == 1) {
                    $template = 5;
                }

                // если первый столбец число и не пусто имя, начинаем работать
                if ($cid != 'ID' and  !empty($name)) {

                    if (empty($GUIDExtParent)) { // если ключ родителя отсутствует - определяем в корневой каталог

                        $parentID = $mainParent;
                    } else { // иначе назначаем родителя

                        // ищем родителя ресурса по полю "GUIDExtParent"
                        $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "' . $GUIDExtParent . '"'; // выборка из таблицы
                        $statement = $modx->query($sql);
                        if ($statement) {
                            $info = $statement->fetchAll(PDO::FETCH_ASSOC);
                            $parentID = $info[0]['contentid']; // id родителя
                        }
                    }

                    $response = $modx->runProcessor('resource/create', array(
                        'template' => $template,
                        'isfolder' => 1,
                        'published' => 1,
                        'pagetitle' => $name,
                        'parent' => $parentID,
                    ));

                    if ($response->isError()) {
                        return $modx->error->failure($response->getMessage());
                    }
                    $modx->cacheManager->clearCache();

                    $newId = $response->response['object']['id'];

                    $page = $modx->getObject('modResource', $newId);
                    // запись в доп.поле
                    $page->setTVValue('level', $level);
                    $page->setTVValue('guidext', $GUIDExt);
                    $page->setTVValue('guidextparent', $GUIDExtParent);
                    $page->setTVValue('goodsIn', $GoodsIn);
                    if ($GoodsIn == 1) {
                        $page->set('class_key', 'msCategory');
                    }
                    $page->save();
                }
            } else { // если категория существует на сайте - обновляем поля

                //echo 'категория' . $name . ' существует на сайте <br>';    
            }
            $updated++;
        }
    }
}
//----- end. обновление категорий закончено ---------------

// проверяем наличие файла импорта товаров. если есть - запускаем процесс обновления
if (file_exists($category_file_original) and file_exists($products_file_original)) {

    rename($products_file_original, $products_file); // переименовываем оригинальный файл, на случай его случайной замены в процессе импорта

    $delimeter = '|'; // разделитель
    $delimeterEnd = '^'; // разделитель строк
    $mainParent = 2; // Основной контейнер каталога

    $handle = fopen($products_file, "r");
    $rows = $updated = 0;

    //цикл для сбора данных из csv
    while (($csv = fgetcsv($handle, 0, $delimeter, $delimeterEnd)) !== false) {

        $rows++;

        // определяем в переменные столбцы из файла    
        $productName        = $csv[0];                                    // Название ресурса (Names)
        $productContent     = $csv[1];                                    // Описание товара (content)
        $productRemains     = $csv[2];                                    // Остатки (remains)
        $productPrice       = preg_replace("/[^x\d|*\.]/", "", $csv[3]);  // цена (price)  удаляем пробелы
        $productNew         = $csv[4];                                    // для формирования блока "Новинки" (new)
        $productTopSale     = $csv[5];                                    // для формирования блока "Лидеры продаж" (topSale)
        $productProfitPrice = $csv[6];                                    // для формирования блока "Выгодная цена" (profit_price)
        $productOrder       = $csv[7];                                    // для индентификации товара "Под заказ" (order)
        $productID          = $csv[8];                                    // идентификатор ресурса (GUIDExt)
        $productParent      = $csv[9];                                    // родительский ресурс (GUIDExtParent)
        $productDopCategory = $csv[10];                                   // доп.категории товара (GUIDExtParents)
        $productIDAlso      = $csv[11];                                   // список UID товаров через запятую для формирования вкладки "Вам могут понадобиться" (GUIDExt_also)
        $vendor             = $csv[12];                                   // фильтр по производителю (Filter1)
        $filter2            = $csv[13];                                   // (Filter2)
        $filter3            = $csv[14];                                   // (Filter3)
        $filter4            = $csv[15];                                   // (Filter4)
        $filter5            = $csv[16];                                   // (Filter5)

        if ($productPrice == 0) {
            $productPrice == 999999; // записываем цену под заказ 999999
        }

        if ($productName != 'Names' and $productName == 'Кран шаровый 3/4 с американкой Латунь (Р)') {

            echo $productName . '<br>';

            // проверяем наличие ресурса в каталоге
            $sql = 'SELECT * FROM `modx_sablsite_content` WHERE pagetitle="' . $productName . '"';
            $statement = $modx->query($sql);
            if ($statement) {
                $info = $statement->fetchAll(PDO::FETCH_ASSOC);
                $resourceID = $info[0]['id'];
            }

            /*
                $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "'.$productID.'"'; // выборка по значению UID
                $statement = $modx->query($sql);
                if ($statement) {
                    $info = $statement->fetchAll(PDO::FETCH_ASSOC);    
                    $resourceID = $info[0]['contentid']; // id
                }
                */

            if (!empty($resourceID)) {

                echo $productName . '--' . $resourceID;

                if ($productParent != '00000000-0000-0000-0000-000000000000') {
                    // ищем родительский каталог для данного товара
                    $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "' . $productParent . '"'; // выборка из таблицы
                    $statement = $modx->query($sql);
                    if ($statement) {
                        $info = $statement->fetchAll(PDO::FETCH_ASSOC);
                        $parentID = $info[0]['contentid']; // id
                    }
                } else {
                    $parentID =   23170; // иначе отправляем в "Разное"  
                }

                if ($parentID) {
                    // обновляем поля товара
                    $product = $modx->getObject('modResource', $resourceID);
                    $product->set('parent', $parentID);
                    $product->set('price', $productPrice); // записываем цену
                    $product->set('content', $productContent);
                    $product->setTVValue('guidext', $productID);
                    $product->setTVValue('guidextparent', $productParent);
                    $product->setTVValue('product-dopCategory', $productDopCategory);
                    $product->setTVValue('product-price', $productPrice);
                    $product->setTVValue('product-new', $productNew);
                    $product->setTVValue('product-topSale', $productTopSale);
                    $product->setTVValue('product-remains', $productRemains);
                    $product->setTVValue('product-profitPrice', $productProfitPrice);
                    $product->setTVValue('product-order', $productOrder);
                    $product->setTVValue('product-also', $productIDAlso);
                    $product->setTVValue('filter-vendor', $vendor);
                    $product->save();

                    //--- Обновляем галерею фото ----
                    $dir_imgs = $dir_image . $productID . '/';
                    foreach (array_diff(scandir($dir_imgs), array('..', '.')) as $file_img) {
                        $file_path = $dir_imgs . $file_img; // полный путь к файлу

                        $data = [
                            'id' => $resourceID, // id - ресурса
                            'file' => $file_path, // путь к картинке
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
            } else {
                //echo 'Ресурс отсутствует<br>';

                if ($productParent != '00000000-0000-0000-0000-000000000000') {
                    // ищем родительский каталог для данного товара
                    $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "' . $productParent . '"'; // выборка из таблицы
                    $statement = $modx->query($sql);
                    if ($statement) {
                        $info = $statement->fetchAll(PDO::FETCH_ASSOC);
                        $parentID = $info[0]['contentid']; // id
                    }
                } else {
                    $parentID =   23170; // иначе отправляем в "Разное"  
                }

                if ($parentID) {
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
                        $errorArr = $modx->error->failure($response->getMessage());
                        object2file($errorArr, 'log.txt');
                        file_put_contents('log.txt', PHP_EOL . serialize($errorArr), FILE_APPEND);
                    }
                    $modx->cacheManager->refresh(); // очищаем кэш

                    // получаем id созданного ресурса
                    $newId = $response->response['object']['id'];

                    //--- Загружаем фото ----
                    $dir_imgs = $dir_image . $productID . '/';
                    foreach (array_diff(scandir($dir_imgs), array('..', '.')) as $file_img) {
                        $file_path = $dir_imgs . $file_img; // полный путь к файлу

                        $data = [
                            'id' => $newId, // id - ресурса
                            'file' => $file_path, // путь к картинке
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
        }

        $updated++;
    }

    fclose($handle);
    //unlink($products_file); // удаляем файл импорта

    echo '<pre>';
    echo "\nImport complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
    echo "\nTotal rows: $rows\n";
    echo "Updated:  $updated\n";
    echo '</pre>';
} else {

    echo "Файлы импорта отсутсвуют";
}
