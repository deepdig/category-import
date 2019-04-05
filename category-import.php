<?php

set_time_limit(1440);

define('MODX_API_MODE', true);
//require_once('/home/g/g70573wf/new_sablemarket/public_html/index.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/index.php');
$modx = new modX();
$modx->initialize('web');

// загружаем файл импорта из csv
//$file = '/home/g/g70573wf/new_sablemarket/public_html/ajax/import/my_1c.csv'; // имя файла
$file = $_SERVER['DOCUMENT_ROOT'] . '/ajax/import/my_1c.csv'; // имя файла
$delimeter = '|'; // разделитель

$handle = fopen($file, "r");
$rows = $updated = 0;

$mainParent = 8472; // Основной контейнер каталога

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

// получаем имеющиеся ресурсы
$where = array(
    'parent' => $mainParent // родительский каталог
);
$resources = $modx->getCollection('modResource', $where);

// если ресурсы не созданы - создаем
if (empty($resources)) {

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

        // назначаем ресурсу нужный шаблон
        if ($GoodsIn == 0) {
            $template = 4;
        } elseif ($GoodsIn == 1) {
            $template = 5;
        }

        // если первый столбец число и не пусто имя, начинаем работать
        if ($cid != 'ID' and  !empty($name)) {

            $response = $modx->runProcessor('resource/create', array(
                'template' => $template,
                'isfolder' => 1,
                'published' => 1,
                'pagetitle' => $name,
                //'alias' => $rows .'-'. translit($name),
                'parent' => $mainParent,
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
            $page->setTVValue('level', $level);
            $page->setTVValue('guidext', $GUIDExt);
            $page->setTVValue('guidextparent', $GUIDExtParent);
            $page->setTVValue('goodsIn', $GoodsIn);
            if ($GoodsIn == 1) {
                $page->set('class_key', 'msCategory');
            }
            $page->save();
        }

        $updated++;
    }

    //запуск второго этапа - размещение по родительским каталогам
    sleep(10); // пауза 10 сек

    $modx->cacheManager->clearCache(); // очистка кеша    
    // получаем имеющиеся ресурсы
    
    $resources = $modx->getIterator('modResource', $where);

    // Цикл по имеющимся ресурсам
    foreach ($resources as $id => $res) { // $id - id ресурса

        $uid = $res->getTVValue('guidext'); // UID - уникальный идентификатор из tv-поля
        $parent = $res->getTVValue('guidextparent'); // уникальный идентификатор родительского каталога
        $minishop = $res->setTVValue('goodsIn'); // идентификатор ресурса магазина

        //echo $uid .', '. $parent . '<br>';

        // если имеется поле с указанием родительского каталога то..
        if ($parent) {
            // ищем родительский каталог..
            $result = $modx->runSnippet('pdoResources', array(
                'parents' => 0,
                'limit' => 0,
                'level' => 10,
                'returnIds' => 1,
                'includeTVs' => 'guidext,guidextparent',
                'where' => '{"guidext:LIKE":"' . $parent . '"}',
            ));
            //echo $result . '<br>';
            // и перемещаем туда дочерний ресурс
            $res->set('parent', $result);
            $res->save();
        }
    }
} else {

    // иначе проверяем на совпадение uid
}

fclose($handle);


echo '<pre>';
echo "\nImport complete in " . number_format(microtime(true) - $modx->startTime, 7) . " s\n";
echo "\nTotal rows:	$rows\n";
echo "Updated:	$updated\n";
echo '</pre>';
