maximaster.tools
========
Плагин пока еще находится в разработке, использовать на свой страх и риск!

Данный модуль предоставляет средства для упрощения разработки сайтов на основе 1С Битрикс, и обладает следующим функционалом:

 - Автозагрузчик классов (psr-4) 
 - Автозагрузчик обработчиков событий
 - Базовый класс для обработчиков событий
 - Расширение для ORM, которое позволяет получать инфоблоки и их свойства

Для начала использования модуля нужно произвести его установку через административную панель, после чего подключить модуль в init.php:

  ```php
    Bitrix\Main\Loader::includeModule('maximaster.tools');
  ```
    
1. Автозагрузчик классов.
Работает на основе стандарта автозагрузки [PSR-4](http://www.php-fig.org/psr/psr-4/ru/) и представлен классом \Maximaster\Tools\Psr4Loader. Для регистрации своих классов нужно создать инстанс класса, добавить соответствия путей и пространств имен, зарегистрировать автозагрузчик, например, в том же init.php

  ```php
    $psr4 = \Maximaster\Tools\Psr4Autoloader::getInstance();
    $psr4->addNamespace('Maximaster', __DIR__  . '/../classes/Maximaster');
    $psr4->register();
  ```
  После этого все классы из classes/Maximaster/ будут подгружаться автоматически при соблюдении правил именования классов.

2. Автозагрузчик обработчиков событий. Представлен классом \Maximaster\Tools\Events\Listener, построен на основе psr-4 автозагрузчика (предоставляет тот же интерфейс регистрации). В понятии данного класса каждый обработчик события - это метод определенного класса. Класс должен именоваться в соответствии со строковым идентификатором события, имя метода-обработчика значения не имеет. Каждый класс обработчик должен принадлежать пространству имен, которое имеет имя модуля, которому этот обработчик принадлежит. Например, если нужно зарегистрировать обработчик события OnPageStart, то необходимо создать класс, полное имя которого будет кончаться на Main\OnPageStart и создать в этом классе метод с любым именем. Для регистрации директории с обработчиками событий нужно написать примерно следующее

  ```php
    $eventListener = new \Maximaster\Tools\Events\Listener();
    $eventListener->addNamespace('Maximaster\\Main\\EventHandler', __DIR__ . '/../classes/Maximaster/Main/EventHandler');
    $eventListener->register();
  ```
  
3. Базовый класс для обработчиков событий. 
Данный класс предоставляет обработчикам событий расширенные возможности. Для использования этих возможностей нужно класс обработчика события унаследовать от данного класса. Например, при создании класса OnPageStart нужно указать, что он будет унаследован от \Maximaster\Tools\Events\Base

  ```php
    class OnPageStart extends \Maximaster\Tools\Events\Base {}
  ```
    Данный класс предоставляет следующие возможности:
 - Обмен данными между обработчиками событий. Для этого нужно воспользоваться методами setData($key, $value) и getData($key). Особенно это полезно, когда нужно контролировать процесс выполнения какого-то события, сначала до выполнения какого-то действия (например удаления), а потом после. Так можно в обработчике OnEndBufferContent получить информацию обо всех удаленных элементах инфоблока в процессе выполнения скрипта.
 - Управление порядком вызова обработчиков. Чтобы изменить порядок вызова, нужно в классе обработчика переопределить статическую переменную $sort. Она должна хранить в себе массив, где в качестве ключа выступает имя метода, а в качестве значения - порядок сортировки. В стандартных обработчиках событий порядок сортировки, как правило равен 100, поэтому с помощью данного механизма можно вызвать свой обработчик события до битриксового.

4.  Расширение для Orm.
Данный класс предоставляет возможность выполнять выборку данных из инфоблоков вместе со свойствами. Значения свойств выбираются "как есть", модификация данных к ним не применяется.
Для составления запросов к одиночным инфоблокам, нужно создать свою сущность, которая будет описывать инфоблок. Для этого нужно создать класс, который будет являться наследником класса Maximaster\Tools\Orm\Iblock\ElementTable. Этот класс должен реализовывать лишь один метод, который должен вернуть идентификатор инфоблока:
  
  ```php
	class ProductTable extends Maximaster\Tools\Orm\Iblock\ElementTable
	{
		public static function getIblockId()
		{
			return 1;
		}
	}
  ```

Теперь можно пользоваться данным классом для обращения к инфоблоку с ID = 1. Устанавливать фильтр по инфоблоку в этом случае не нужно, при запросе фильтр будет автоматически добавлен. Фильтры со сложной логикой и с модификаторами не отслеживаются, поэтому отслеживать задание фильтров по другим инфоблокам нужно самостоятельно.
Для получения значения свойства нужно использовать имя поля 'PROPERTY_CODE_VALUE', где CODE - это символьный код свойства. Для получения описания значения свойства, нужно использовать имя поля 'PROPERTY_CODE_DESCRPTION'.
Если свойство имеет тип "Привязка к элементу", то в значении будет находиться референс на этот элемент. Можно использовать этот референс, чтобы обратиться к полям и свойствам связанных сущностей, например:

 ```php
	$stadiumList = PriceTable::query()
        ->addFilter('STADIUM_EXTERNAL_ID', 21)
        ->setSelect(array('NAME', 'ACTIVE_FROM'))
        ->addSelect('PROPERTY_MATCH_VALUE.PROPERTY_STADIUM_VALUE.PROPERTY_SEATROOT_ID_VALUE', 'STADIUM_EXTERNAL_ID');
  ```
Сама сущность свойства хранится в поле 'PROPERTY_CODE' для всех свойств (множественных и нет) кроме одиночных свойств инфоблоков 2.0. Для них сущность хранится в 'PROPERTY_TABLE_IBLOCK_N', где N - это идентификатор инфоблока. Для совместимости у каждого свойства 2.0 есть также референс PROPERTY_CODE, который ссылается на таблицу со всеми значениями всех одиночных свойств. Использовать его имеет смысл только для получения ID значения свойства. Например, получить ID значения большинства свойств можно через поле 'PROPERTY_CODE.ID'. Для инфоблоков 1.0, а также для множественных свойств можно получить дополнительную информацию.

Для составления запроса к нескольким инфоблокам сразу, не нужно использовать наследника класса Maximaster\Tools\Orm\Iblock\ElementTable, а напротив, использовать этот класс самостоятельно. Например:

   ```php
	$db = Maximaster\Tools\Orm\Iblock\ElementTable::query()
		->addFilter('@IBLOCK_ID', [22, 20])
		->addSelect('PROPERTY_PAIR_SECTOR_VALUE') // Это из инфоблока 22
		->addSelect('PROPERTY_CALENDAR_ID_VALUE') // А это - из 20
		->addSelect('NAME')
  ```
  Также доступен динамический конструктор сущности, по аналогии с Higload-блоками:
  
  ```php
  	$entity = ElementTable::compileEntity(1)->getDataClass();
  	$entity::query()
  		->addSelect('IBLOCK_ID');
    ```
    
  Результатом выполнения подобного запроса будет таблица, которая будет содержать список всех элементов из 2х инфоблоков. В каждой записи будут доступны те свойства, которые есть у инфоблока. 
  **Внимание - свойства с одинаковыми кодами в нескольких разных инфоблоках пока не поддерживаются.**

Все свойства всех используемых в фильтре инфоблоков будут подцеплены к запросу автоматически и будут подцепляться к нему на каждом запросе, поэтому **будьте внимательны** и **следите за производительностью**. Составление каждого такого запроса может занимать достаточно **длительное время (сотые доли секунды)**.

Для того, чтобы достать URL детальной страницы, нужно использовать поле DETAIL_PAGE_URL. Поскольку это поле - шаблонное, то оно наполняется после выборки. Для того, чтобы оно корректно наполнилось, необходимо позаботиться о том, чтобы среди выбираемых полей были все те, которые используются в шаблоне DETAIL_PAGE_URL.

Буду благодарен за багрепорты и pull-реквесты!