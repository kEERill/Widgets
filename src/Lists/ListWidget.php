<?php namespace Keerill\Widgets\Lists;

use Keerill\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListWidget extends Widget
{
    /**
     * @var Collection $allColumns Коллекция столбцов
     */
    protected $allColumns = null;

    /**
     * @var LengthAwarePaginator $records Коллекция найденных моделей
     */
    protected $records = null;

    /**
     * @var integer $recordsToPage Количество записей на страницу
     */
    protected $recordsToPage = 15;

    /**
     * @var boolean $usePagination Показывает пагинацию
     */
    protected $usePagination = true;

    /**
     * @var Model $modelClass Класс модели, которые будем выводить в таблице
     */
    protected $modelClass = null;

    /** 
     * @var string $template Название шаблона таблицы 
     */
    protected $template = 'widgets::lists.layouts.default';

    /**
     * @var string $defaultType Стандартное название типа колонки
     */
    protected $defaultType = 'default';

    /**
     * @var array $availableColumnTypes Массив доступных типов столбцов
     */
    protected $availableColumnTypes = null;

    /**
     * @var string $defaultSort Стандартная сортировка
     */
    protected $defaultSort = 'created_at desc';

    /**
     * @var string $redirectUrl Страница, на которую будет вести ссылка в таблице
     */
    protected $redirectUrl = null;

    /**
     * @var string $redirectMessage Содержимое ссылки
     */
    protected $redirectMessage = 'Редактировать';

    /**
     * @inheritdoc
     */
    protected function boot(array $options)
    {
        parent::boot($options);

        /**
         * Регистрация столбцов виджета
         */
        $this->registerListColumn();
    }

    /**
     * @inheritdoc
     */
    protected function initConfig()
    {
        $this->fillConfig([
                'recordsToPage', 'usePagination', 'defaultSort', 'redirectUrl'
            ]);
    }

    /**
     * Возвращает массив доступных типов столбцов
     *
     * @return array
     */
    public function getAvailableColumnTypes()
    {
        if ($this->availableColumnTypes === null) {
            return $this->availableColumnTypes = config('widgets.columnTypes', []);
        }

        return $this->availableColumnTypes;
    }

    /**
     * Возвращает класс данного типа столбца
     *
     * @param string $columnType название типа столбца
     * @return string Класс столбца
     */
    public function getColumnTypeClass(string $columnType)
    {
        /**
         * Делаем проверку, что данный тип поля существует в системе
         */
        if (!in_array($columnType, array_keys($this->getAvailableColumnTypes()))) {
            throw new \InvalidArgumentException(
                sprintf('Тип столбца [%s] не существует', $columnType)
            );
        }

        return $this->availableColumnTypes[$columnType];
    }

    /**
     * Возвращает колонки таблицы
     *
     * @return Collection
     */
    public function getColumns()
    {
        return $this->allColumns;
    }

    /**
     * Возвращает коллекцию моделей
     *
     * @return LengthAwarePaginator
     */
    public function getRecords()
    {
        return $this->records;
    }

    /**
     * Возваращет ссылку на запись
     *
     * @param Model $record
     * @return string
     */
    public function getRedirectUrl(Model $record)
    {
        return route($this->redirectUrl, $record->getKey());
    }

    /**
     * Возвращает название ссылки
     *
     * @param Model $record
     * @return string
     */
    public function getRedirectMessage(Model $record)
    {
        return $this->redirectMessage;
    }

    /**
     * Возвращает true если свойство redirectUrl заполнен
     *
     * @return bool
     */
    public function hasRedirectUrl()
    {
        return (bool) $this->redirectUrl;
    }

    /**
     * Добавляет столбец к данной талбице
     *
     * @param string $columnName Название столбца
     * @param string $columnType Тип столбца
     * @return ListColumn
     */
    public function add(string $columnName, ?string $columnType = null)
    {
        /**
         * Получаем класс поля по типу
         */
        $columnType = $columnType ?: $this->defaultType;
        $columnClass = $this->getColumnTypeClass($columnType);

        return $this->addColumn($columnName, new $columnClass ($this, $columnName, $columnType));
    }

    /**
     * Прикрепляет данное поле к данной талбице
     *
     * @param string $columnName Название столбца
     * @param ListColumn $column Экземпляр форстолбцамы
     * @return ListColumn
     */
    public function addColumn(string $columnName, ListColumn $column)
    {
        /**
         * Добавляем данный столбец в массив столбцов данной талбицы
         */
        if ($this->allColumns == null) {
            $this->allColumns = collect([]);
        }

        $this->allColumns->put($columnName, $column);

        return $column;
    }

    /**
     * Регистрация столбцов для таблицы
     *
     * @return void
     */
    protected function registerListColumn() {}

    /**
     * Создание модели
     *
     * @return Model
     */
    protected function createModel()
    {
        $className = $this->modelClass;
        return new $className ();
    }
    /**
     * @inheritdoc
     */
    protected function prepareRender()
    {
        /**
         * Получение моделей для таблицы
         */
        $this->prepareModel();
    }

    /**
     * Выборка моделей из базы данных
     * @return void
     */
    protected function prepareModel()
    {
        /**
         * Создаем новый запрос
         */
        $query = $this->createModel()->newQuery();

        /**
         * Делаем наследование столбцов, что бы столбца добавили в запрос, все что требуется
         */
        foreach ($this->allColumns as $columnName => $column) {
            $column->extendQuery($query);
        }

        /**
         * Вызываем событие для наследования запроса
         */
        $this->fireEvent('widget.list.extendQuery', [$this, $query]);

        $this->extendQuery($query);

        /**
         * Сортировка столбцов
         */
        list($orderColumn, $orderType) = explode(' ', $this->defaultSort);
        $query->orderBy($orderColumn, $orderType);

        /**
         * Полученный результат сохраняем
         */
        $this->records = $this->usePagination ? 
            $query->paginate($this->recordsToPage) : 
            $query->simplePaginate($this->recordsToPage);
    }

    /**
     * Наследуем запрос
     *
     * @param Builder $query
     * @return void
     */
    protected function extendQuery(Builder $query) {}

    /**
     * Здесь можно задавать кастомные стили для таблицы
     *
     * @param Model $record
     * @return void|string
     */
    public function extendRowClass(Model $record) {}
}
