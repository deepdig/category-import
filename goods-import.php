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

// Функция транслитерации
function translit($s)
{
    $s = (string)$s; // преобразуем в строковое значение
    $s = strip_tags($s); // убираем HTML-теги
    $s = str_replace(array("\n", "\r"), " ", $s); // убираем перевод каретки
    $s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
    $s = trim($s); // убираем пробелы в начале и конце строки
    $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр (иногда надо задать локаль)
    $s = strtr($s, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
    $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s); // очищаем строку от недопустимых символов
    $s = str_replace(" ", "-", $s); // заменяем пробелы знаком минус
    return $s; // возвращаем результат
}

// Функция записи массива в лог
function object2file($value, $filename)
{
    $str_value = "\n" . serialize($value);

    $f = fopen($filename, 'w');
    fwrite($f, $str_value);
    fclose($f);
}

$data = date("d.m.y.H.i.s");

$mainParent = 2; // Основной контейнер каталога

// Директория с фотографиями
$dir_image = MODX_BASE_PATH . 'assets/img_katalog/';

// загружаем файл импорта из csv
$category_file_original = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my.csv'; // имя файла импорта категорий
$products_file_original = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_tovar.csv'; // имя файла импорта товаров
$category_file = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_finished_' . $data . '.csv'; // имя переименованного файла категорий
$products_file = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_tovar_finished_' . $data . '.csv'; // имя переименованного файла товаров

// проверяем наличие файла импорта категорий. если есть - запускаем процесс обновления
if (file_exists($category_file_original)) {
    
    rename($category_file_original, $category_file); // переименовываем оригинальный файл, на случай его случайной замены в процессе импорта
    $fp = fopen($category_file_original, "w");
    fclose($fp);

    $delimeter = '|'; // разделитель
    $handle = fopen($category_file, "r");
    $rows = $updated = 0;

        //цикл для создания категорий
        while (($csv = fgetcsv($handle, 0, $delimeter)) !== false) {
            $rows++;

            // определяем в переменные столбцы из файла
            $cid = $csv[0];                                          // id ресурса - не используется
            $name = preg_replace( '/"([^"]*)"/', "«$1»", $csv[1] );  // Название ресурса - заменяем кавычки если есть
            $level = $csv[2];                                        // Уровень каталога
            $GoodsIn = $csv[3];                                      // Идентификатор "является ли каталог" хранилищем товаров
            $GUIDExt = $csv[4];                                      // GUIDExt - идентификатор ресурса
            $GUIDExtParent = $csv[5];                                // GUIDExtParent - родительский ресурс

            if (!empty($name) and $name != 'Names') {
                
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
                    $modx->cacheManager->refresh();
    
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
                    
                } else { // если ресурс уже создан выполняем обновление категории
                    
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
                    
                    if ($GoodsIn == 0) {
                        $page = $modx->getObject('modResource', $result);
                        //echo $page->get('pagetitle') . ' - ресурс для обновления' . '<br>';
                        $page->set('parent', $parentID);
                        $page->set('pagetitle', $name);
                        $page->setTVValue('level', $level);
                        $page->setTVValue('guidext', $GUIDExt);
                        $page->setTVValue('guidextparent', $GUIDExtParent);
                        $page->setTVValue('goodsIn', $GoodsIn);
                        $page->save();
                    } else {
                        $category = $modx->getObject('msCategory', $result);
                        if ($category) {
                            //echo $result . '--' . $category->get('pagetitle') . ' - ресурс для обновления - категория Minishop' . '<br>';
                            $category->set('parent', $parentID);
                            $category->set('pagetitle', $name);
                            $category->setTVValue('level', $level);
                            $category->setTVValue('guidext', $GUIDExt);
                            $category->setTVValue('guidextparent', $GUIDExtParent);
                            $category->setTVValue('goodsIn', $GoodsIn);
                            $category->set('class_key', 'msCategory');
                            $category->save();
                        }
                    }
                }
            }
            
            $updated++;
        }
        
        fclose($handle);
} else {
    echo 'Файла импорта категорий не существует  <br>';
}
//----- end. обновление категорий закончено ---------------


// =============== ИМПОРТ ИЛИ ОБНОВЛЕНИЕ ТОВАРОВ ================

// проверяем наличие файла импорта товаров. если есть - запускаем процесс обновления
if (file_exists($category_file_original) and file_exists($products_file_original)) {
    
    rename($products_file_original, $products_file); // переименовываем оригинальный файл, на случай его случайной замены в процессе импорта
    $fp = fopen($products_file_original, "w");
    fclose($fp);

    $delimeter = '|'; // разделитель
    $delimeterEnd = '^'; // разделитель строк
    $mainParent = 2; // Основной контейнер каталога

    $handle = fopen($products_file, "r");
    $rows = $updated = 0;

    //цикл для сбора данных из csv
    while (($csv = fgetcsv($handle, 0, $delimeter, $delimeterEnd)) !== false) {

        $rows++;

        // определяем в переменные столбцы из файла    
        $productName        = preg_replace( '/"([^"]*)"/', "«$1»", $csv[0] );                   // Название ресурса (Names) - заменяем кавычки на << >>
        $productContent     = preg_replace( '/"([^"]*)"/', "«$1»", $csv[1] );                   // Описание товара (content) - заменяем кавычки на << >>
        $productRemains     = $csv[2];                                                          // Остатки (remains)
        $productPrice       = str_replace(',','.', preg_replace( '/[^,.0-9]/', '', $csv[3]) );  // цена (price)  удаляем пробелы и меняем запятую на точку
        $productNew         = $csv[4];                                                          // для формирования блока "Новинки" (new)
        $productTopSale     = $csv[5];                                                          // для формирования блока "Лидеры продаж" (topSale)
        $productProfitPrice = $csv[6];                                                          // для формирования блока "Выгодная цена" (profit_price)
        $productOrder       = $csv[7];                                                          // для индентификации товара "Под заказ" (order)
        $productID          = $csv[8];                                                          // идентификатор ресурса (GUIDExt)
        $productParent      = $csv[9];                                                          // родительский ресурс (GUIDExtParent)
        $productDopCategory = $csv[10];                                                         // доп.категории товара (GUIDExtParents)
        $productIDAlso      = $csv[11];                                                         // список UID товаров через запятую для формирования вкладки "Вам могут понадобиться" (GUIDExt_also)
        $publicProduct      = $csv[12];                                                         // столбец Delete_Tovar
        $vendor             = $csv[13];                                                         // фильтр по производителю (Filter1)
        $filter2            = $csv[14];                                                         // (Filter2)
        $filter3            = $csv[15];                                                         // (Filter3)
        $filter4            = $csv[16];                                                         // (Filter4)
        $filter5            = $csv[17];                                                         // (Filter5)

        if ($productPrice == 0) {
            $productPrice == 999999; // записываем цену под заказ 999999
        }

        if ($productName != 'Names' and $productName != 'Стройматериалы') {

            //echo $productName . '<br>';

            // проверяем наличие ресурса в каталоге
            /*
            $sql = 'SELECT * FROM `modx_sablsite_content` WHERE pagetitle="' . $productName . '"'; // выборка по значению productName
            $statement = $modx->query($sql);
            if ($statement) {
                $info = $statement->fetchAll(PDO::FETCH_ASSOC);
                $resourceID = $info[0]['id'];
            }
            */
            
            $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "'.$productID.'"'; // выборка по значению UID
            $statement = $modx->query($sql);
            if ($statement) {
                $info = $statement->fetchAll(PDO::FETCH_ASSOC);    
                $resourceID = $info[0]['contentid']; // id
            }

            if (!empty($resourceID)) { // если товар уже имеется в каталоге, обновляем поля

                //echo $productName . '--' . $resourceID;

                if ($productParent != '00000000-0000-0000-0000-000000000000') {
                    // ищем родительский каталог для данного товара
                    $sql = 'SELECT * FROM `modx_sablsite_tmplvar_contentvalues` WHERE tmplvarid = 1 AND value = "' . $productParent . '"'; // выборка из таблицы
                    $statement = $modx->query($sql);
                    if ($statement) {
                        $info = $statement->fetchAll(PDO::FETCH_ASSOC);
                        $parentID = $info[0]['contentid']; // id
                    }
                } else {
                    $parentID = 23170; // иначе отправляем в "Разное"  
                }

                if ($parentID) {
                    //echo "fdf";
                    //echo $productName; 
                    // обновляем поля товара
                    $product = $modx->getObject('modResource', $resourceID);
                    
                    if ($product) {
                        if(($publicProduct == 'False' && $parentID != 23170 && $productRemains > 0) || ($publicProduct == 'False' && $productOrder == 1)){ // если не False или родитель "0000..", то не снимаем с публикации
                            $product->set('published', 1);
                        } 
                        else {
                            $product->set('published', 0);    
                        }
                        
                        if ($publicProduct == 'False' and $productOrder == 1) { // Если у товара стоит галочка "Под заказ", цену делаем (для сортировки и фильтров) 999999
                            $product->set('price', 0); // записываем цену 999999
                            $product->setTVValue('product-price', 999999);
                        } else {
                            $product->set('price', $productPrice); // записываем цену из csv
                            $product->setTVValue('product-price', $productPrice);
                        }
                        
                        $product->set('pagetitle', $productName);
                        $product->set('parent', $parentID);
                        $product->set('content', $productContent);
                        $product->setTVValue('guidext', $productID);
                        $product->setTVValue('guidextparent', $productParent);
                        $product->setTVValue('product-dopCategory', $productDopCategory);
                        $product->setTVValue('product-new', $productNew);
                        $product->setTVValue('product-topSale', $productTopSale);
                        $product->setTVValue('product-remains', $productRemains);
                        $product->setTVValue('product-profitPrice', $productProfitPrice);
                        $product->setTVValue('product-order', $productOrder);
                        $product->setTVValue('product-also', $productIDAlso);
                        $product->setTVValue('filter-vendor', $vendor);
                        $product->save();
                    }
                }
            } else {
                //echo 'Товар: ' . $productName . ' -- отсутствует в каталоге <br>';

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

                if ($parentID and $productName != 'Names' and $productName != 'Стройматериалы') {
                    //echo $parentID;
                    if(($publicProduct == 'False' && $parentID != 23170 && $productRemains > 0) || ($publicProduct == 'False' && $productOrder == 1)){ // если не False или родитель "0000..", то не снимаем с публикации
                        $published = 1;
                    } 
                    else {
                        $published = 0;    
                    }
                    
                    if ($publicProduct == 'False' and $productOrder == 1) { // Если у товара стоит галочка "Под заказ", цену делаем (для сортировки и фильтров) 999999
                        $productPrice = 0; // записываем цену 0
                    }
                    if ($publicProduct == 'False' and $productOrder == 1) { // Если у товара стоит галочка "Под заказ", цену делаем (для сортировки и фильтров) 999999
                        $productPrice = 999999; // записываем цену 999999
                    }
                    
                    $response = $modx->runProcessor('resource/create', array(
                        'processors_path' => MODX_PROCESSORS_PATH,
                        'template' => 6, // шаблон с товаром
                        'isfolder' => 0, // это не контейнер
                        'published' => $published, // опубликован
                        'parent' => $parentID,
                        'pagetitle' => $productName,
                        'alias' => $rows .'-'. translit($productName),
                        'content' => $productContent,
                        // основные параметры msProduct
                        'class_key' => 'msProduct', // указываем что это товар minishop
                        'show_in_tree' => 0, // не показывать товар в древе ресурсов
                        //'article' => $productID, // Артикул
                        //'price' => $productPrice, // Цена
                        //'new' => $productNew, // Новый товар
                        //'popular' => $productTopSale, // Популярный товар
                        //'vendor' => $vendor, // производитель
                        // устанавливаем TV поля
                        //'tv1' =>  $productID,          // TV - guidext
                        //'tv2' =>  $productParent,      // TV - guidextparent
                        //'tv5' =>  $productRemains,     // TV - product-remains
                        //'tv6' =>  $productPriceTv,       // TV - product-price
                        //'tv7' =>  $productNew,         // TV - product-new
                        //'tv8' =>  $productTopSale,     // TV - product-topSale
                        //'tv9' =>  $productProfitPrice, // TV - product-profitPrice
                        //'tv10' => $productOrder,       // TV - product-order
                        //'tv11' => $productIDAlso,      // TV - product-also
                        //'tv15' => $vendor,             // TV - filter-vendor
                        //'tv16' => $productDopCategory  // TV - product-dopCategory
                    ));

                    if ($response->isError()) {
                        $errorArr = $response->getAllErrors();
                        object2file($errorArr, 'log.txt');
                        file_put_contents('log.txt', PHP_EOL . $data . ' -- ' .serialize($errorArr), FILE_APPEND);
                        print_r($response->getAllErrors());
                    }
                    $modx->cacheManager->refresh(); // очищаем кэш
                    
                    // получаем id созданного ресурса и записываем в него тв-поля
                    $newId = $response->response['object']['id'];
                    if ($newId) {
                        $pageNew = $modx->getObject('msProduct', $newId);
                        // основные параметры msProduct
                        $pageNew->set('alias', 'products/' . $newId . '-'. translit($productName));
                        $pageNew->set('article', $productID);
                        $pageNew->set('price', $productPrice);
                        $pageNew->set('new', $productNew);
                        $pageNew->set('popular', $productTopSale);
                        $pageNew->set('vendor', $vendor);
                        // запись в доп.поле
                        $pageNew->setTVValue('guidext', $productID);
                        $pageNew->setTVValue('guidextparent', $productParent);
                        $pageNew->setTVValue('product-remains', $productRemains);
                        $pageNew->setTVValue('product-price', $productPrice);
                        $pageNew->setTVValue('product-new', $productNew);
                        $pageNew->setTVValue('product-topSale', $productTopSale);
                        $pageNew->setTVValue('product-profitPrice', $productProfitPrice);
                        $pageNew->setTVValue('product-order', $productOrder);
                        $pageNew->setTVValue('product-also', $productIDAlso);
                        $pageNew->setTVValue('filter-vendor', $vendor);
                        $pageNew->setTVValue('product-dopCategory', $productDopCategory);
                        $pageNew->save();
                    }
                }
            }
        }

        $updated++;
    }
    
    fclose($handle);
    unlink($category_file_original); // удаляем файл импорта
    unlink($products_file_original); // удаляем файл импорта
    //rename($category_file_original, $category_file); // переименовываем оригинальный файл, на случай его случайной замены в процессе импорта
    //rename($products_file_original, $products_file); // переименовываем оригинальный файл, на случай его случайной замены в процессе импорта
    
    $modx->cacheManager->refresh(); // очищаем кэш

    echo '<pre>';
    echo "\nImport complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
    echo "\nTotal rows: $rows\n";
    echo "Updated:  $updated\n";
    echo '</pre>';
} else {

    echo "Файл импорта товаров отсутствует <br>";
}
