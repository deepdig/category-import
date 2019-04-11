<?php

set_time_limit(1440);

define('MODX_API_MODE', true);
//require_once('/home/g/g70573wf/new_sablemarket/public_html/index.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/index.php');
$modx = new modX();
$modx->initialize('web');

// транслит
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

// загружаем файл импорта из csv
//$file = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_1c.csv'; // имя файла
$file = $_SERVER['DOCUMENT_ROOT'] . '/ajax/category-import/import/my_tovar_last'; // имя файла
$delimeter = '||'; // разделитель

$handle = fopen($file, "r");
$rows = $updated = 0;

$mainParent = 8472; // Основной контейнер каталога

//цикл для сбора данных по уровням
while (($csv = fgetcsv($handle, 0, $delimeter)) !== false) {
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
    $productIDAlso = $csv[9];          // список UID товаров через запятую для формирования вкладки "Вам могут понадобиться" (GUIDExt_also)    

    // если первый столбец не содержит служебного названия, начинаем работать
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

        // если ресурс не создан, то создаем
        if (empty($result)) {

            // ищем родительский каталог для данного товара
            $parentID = $modx->runSnippet('pdoResources', array(
                'parents' => $mainParent,
                'limit' => 0,
                'level' => 10,
                'returnIds' => 1,
                'includeTVs' => 'guidext',
                'where' => '{"guidext:LIKE":"' . $productParent . '"}',
            ));

            $response = $modx->runProcessor('resource/create', array(
                'template' => 6, // шаблон с товаром
                'isfolder' => 0,
                'published' => 1,
                'parent' => $parentID,
                'pagetitle' => $productName,
                'content' => $productContent,
            ));

            if ($response->isError()) {
                return $modx->error->failure($response->getMessage());
            }
            $modx->cacheManager->clearCache();

            $newId = $response->response['object']['id'];

            $page = $modx->getObject('modResource', $newId);
            // псевдоним
            $page->set('alias', $newId . '-' . translit($name));
            // запись в доп.поле
            $page->setTVValue('product-remains',  $productRemains);
            $page->setTVValue('product-price', $productPrice);
            $page->setTVValue('product-new', $productNew);
            $page->setTVValue('product-topSale', $productTopSale);
            $page->setTVValue('product-profitPrice', $productProfitPrice);
            $page->setTVValue('product-order', $productOrder);
            $page->setTVValue('guidext', $productID);
            $page->setTVValue('guidextparent', $productParent);
            $page->setTVValue('product-also', $productIDAlso);
            if ($GoodsIn == 1) {
                $page->set('class_key', 'msCategory');
            }
            $page->save();
        
        // иначе проверяем имеющийся ресурс на изменение позиции в каталоге
        } else {

            // здесь будет код
        }
    }

    $updated++;
}

fclose($handle);


echo '<pre>';
echo "\nImport complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows:	$rows\n";
echo "Updated:	$updated\n";
echo '</pre>';
