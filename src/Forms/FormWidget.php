<?php namespace Keerill\Widgets\Forms;

use Illuminate\Support\Arr;
use Keerill\Widgets\Traits\Theme;
use Illuminate\Support\Collection;
use App\Widgets\Widget as WidgetBase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Validation\Validator;
use Keerill\Widgets\Exceptions\FormFieldException;
use Keerill\Widgets\Forms\Types\Interfaces\Value as ValueInterface;
use Keerill\Widgets\Forms\Types\Interfaces\Validation as ValidationInterface;

class FormWidget extends WidgetBase
{
    use Theme;
    
    /**
     * @var string Метод формы [POST, GET, PUT, DELETE]
     */
    protected $method = 'POST';

    /**
     * @var string Action URL
     */
    protected $url = null;

    /**
     * @var string Css Styles формы
     */
    protected $wrapperClass = null;

    /**
     * @var string Возвращает название роута
     */
    protected $routeName = null;

    /** 
     * @var string $template Название шаблона формы 
     */
    protected $template = 'forms.layouts.default';

    /**
     * @var Model Модель формы
     */
    protected $model = null;

    /**
     * @var string Класс модели
     */
    protected $modelClass = null;

    /**
     * @var Collection $formFields Поля данной формы
     */
    protected $fields = [];
    
    /** 
     * @var string $arrayName Все параметры будут начинаться на это название например: User[name]
     */
    protected $arrayName = null;

    /**
     * @var array Спец сообщения валидации
     */
    protected $validationMessages = [];

    /**
     * @var string Название используемой темы, null - тема по умолчанию
     */
    protected $theme = null;

    /**
     * @var boolean Использование отправки файлов в форме
     */
    protected $useFiles = false;

    /**
     * @var array Атрибуты формы
     */
    protected $formAttributes = [];

    /**
     * @var array
     */
    private $formAttributesReserved = ['method', 'url', 'files', 'action', 'method'];

    /**
     * @var string
     */
    protected $alias = 'formWidget';

    /**
     * @var string
     */
    protected $errorBag = 'default';
    
    /**
     * @var string
     */
    protected $state = null;

    /**
     * @inheritdoc
     */
    protected function boot(array $options)
    { 
        /**
         * Регистрация столбцов виджета
         */
        $this->registerFields();

        parent::boot($options);
    }

    /**
     * Возвращает true, если в форме есть загрузка файлов
     * @return boolean
     */
    public function getUseFiles()
    {
        return (bool) $this->useFiles;
    }

    /**
     * Возвращает true, если в форме есть загрузка файлов
     * @return boolean
     */
    public function setUseFiles(bool $useFiles)
    {
        return $this->useFiles = $useFiles;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->getTemplateName($this->template);
    }

    /**
     * @return string
     */
    public function getErrorBag()
    {
        return $this->errorBag;
    }

    /**
     * Возвращает классы для основного контейнера
     * @return string
     */
    public function getWrapperClass()
    {
        return $this->wrapperClass;
    }

    /**
     * Изменяет стили формы
     * @param string
     * @return self
     */
    public function setWrapperClass(string $wrapperClass)
    {
        $this->wrapperClass = $wrapperClass;
        return $this;
    }

    /**
     * Возвращает атрибуты для тэга FORM "<form attributes>"
     * @return array
     */
    public function getFormAttributes()
    {
        $attributes = [
            'id' => $this->getId()
        ];

        if (is_array($this->formAttributes) && count($this->formAttributes) > 0) {
            $attributes = array_merge($attributes, $this->formAttributes);
        }

        /**
         * Удаляем запрещенные атрибуты
         */
        $attributes = Arr::except($attributes, $this->formAttributesReserved);

        return $attributes;
    }

    /**
     * Возвращает метод отправки запроса фомой
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Изменяет метод формы
     * @param string
     * @return self
     */
    public function setMethod(string $method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Вовзращает название роута
     * @return string
     */
    public function getRouteName()
    {
        return $this->routeName;
    }

    /**
     * Присваивает новое название роута
     * @param string $routeName
     */
    public function setRouteName(string $routeName)
    {
        $this->routeName = $routeName;
    }

    /**
     * Возвращает ссылку формы
     * @return string
     */
    public function getUrl()
    {
        return !$this->url && $this->getRouteName() ? route($this->getRouteName(), $this->getUrlOptions()) : $this->url;
    }

    /**
     * Изменяет ссылку формы
     * @param string
     * @return self
     */
    public function setUrl(string $url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Возвращает параметры для генерации ссылки роута
     * @return array
     */
    protected function getUrlOptions()
    {
        return [];
    }

    /**
     * Формируем правила для валидации
     * @return array
     */
    public function getValidationRules()
    {
        return [];
    }

    /**
     * Возвращает массив названий полей
     * @return array
     */
    public function getValidationNames()
    {
        if (count($this->fields) > 0) {
            return $this->fields->mapWithKeys(function (FormField $field) {
                return $field instanceof ValidationInterface ? $field->getValidationName() : [];
            })->toArray();
        }

        /**
         * Если вдруг в форме нет полей, то возвращаем пустой массив, логично же?
         */
        return [];
    }

    /**
     * Возвращает массив сообщений полей
     * @return array
     */
    public function getValidationMessages()
    {
        $validationMessages = [];

        /**
         * Теперь берем сообщения об ошибках для валидатора из полей
         */
        if (count($this->fields) > 0) {
            $validationMessages = $this->fields->mapWithKeys(function (FormField $field) {
                return $field instanceof ValidationInterface ? $field->getValidationMessages() : [];
            })->toArray();
        }

        if ($this->validationMessages)
            $validationMessages = array_merge($validationMessages, $this->validationMessages);

        /**
         * Если вдруг в форме нет полей, то возвращаем пустой массив, логично же?
         */
        return $validationMessages;
    }

    /**
     * Создание нового экземпляра модели
     * @return Model
     */
    public function createModel()
    {
        $formClass = $this->getModelClass();

        return new $formClass ();
    }

    /**
     * Возвращает класс модели, которую можно привязать к данной форме
     * @return string
     */
    public function getModelClass()
    {
        return $this->modelClass;
    }

    /**
     * Привязывает модель к форме по ID модели
     *
     * @param integer $modelId ID Модели
     * @return self
     */
    public function setModelId(int $modelId)
    {
        return $this->setModel($this->getModelUsingId($modelId));
    }

    /**
     * Возвращает модель по ID
     * @param integer ID модели
     * @return Model
     */
    public function getModelUsingId(int $modelId)
    {
        /**
         * Создаем конструктор запросов
         */
        $query = $this->createModel()->where('id', $modelId);

        /**
         * Это делаем для того, чтобы запрос по поиску модели можно было унаследовать
         * т.е. добавить кастомные условия и т.д. к запросу.
         */
        $this->extendModel($query);

        /**
         * Возвращаем код ошибки 404 если вдруг модель не найдена
         */
        abort_if(!$formModel = $query->first(), 404);

        return $formModel;
    }

    /**
     * Задает модель формы, также заполняет поля
     * @param Model $model
     * @return self
     */
    public function setModel(Model $model)
    {
        $this->setData($this->model = $model);
        return $this;
    }

    /**
     * Возвращает экземпляр модели формы
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Применяет переданные значения полям
     *
     * @param array|Model
     * @return self
     */
    public function setData($data)
    {
        if (count($this->fields) > 0) {
            foreach ($this->fields as $field) {
                if ($field instanceof ValueInterface) {
                    $field->setDataValue($data);
                }
            }
        }

        return $this;
    }

    /**
     * Возвращает данные формы
     * @return array
     */
    public function getData()
    {
        $data = [];

        if (count($this->fields) > 0) {
            foreach ($this->fields as $field) {
                if ($field instanceof ValueInterface) {
                    $data = $field->getDataValue($data);
                }
            }
        }

        return $data;
    }

    /**
     * Вызывается перед проверкой полей
     *
     * @param Validator $validator
     * @return void
     */
    protected function beforeValidation(Validator $validator) {}

    /**
     * Вызывается после проверки полей
     *
     * @param Validator $validator
     * @return void
     */
    protected function afterValidation(Validator $validator) {}

    /**
     * Добавляет к форме новое поле, по его типу
     *
     * @param string $fieldName Название поля
     * @param string $fieldType Тип поля
     * @return FormField
     *
     * @throws FormFieldException
     */
    public function add(string $fieldName, string $fieldType)
    {
        /**
         * Получаем все типы полей
         */
        $availableFieldTypes = config('widgets.fieldTypes', []);

        /**
         * Делаем проверку, что данный тип поля существует в системе
         */
        if (!isset($availableFieldTypes[$fieldType])) {
            throw new FormFieldException(
                sprintf('Тип поля [%s] не существует', $fieldType)
            );
        }

        /**
         * Получаем класс поля по типу
         */
        $fieldClass = $availableFieldTypes[$fieldType];

        return $this->addField($fieldName, new $fieldClass ($this->view, $this, $fieldName, $fieldType));
    }

    /**
     * Добавляет новое поле к форме, передавая класс поля
     *
     * @param string $fieldName Название поля
     * @param FormField $formField Экземпляр формы
     * @return FormField
     */
    public function addField(string $fieldName, FormField $formField)
    {
        /**
         * Добавляем данное поле в массив полей данной формы
         */
        if ($this->fields == null)
            $this->fields = collect([]);

        $this->fields->put($fieldName, $formField);

        /**
         * Если у формы есть начальное название полей, то
         * добавляем его к каждому полю, т.е. name => User[name]
         */
        if ($this->arrayName)
            $formField->arrayName = $this->arrayName;

        /**
         * Если у виджета есть установленная тема, то данную тему распостраняем
         * на поля виджета
         */
        if ($this->getTheme())
            $formField->setTheme($this->getTheme());

        if ($this->getState())
            $formField->setState($this->getState());

        return $formField;
    }

    /**
     * Возвращает коллекцию полей, подключенных к данной форме
     * @return Collection
     */
    public function getFields()
    {
        return $this->fields;
    }


    /**
     * Возвращает объект поля по его названию
     *
     * @param string $fieldName
     * @return FormField
     *
     * @throws FormFieldException
     */
    public function getField(string $fieldName)
    {
        if (!$this->fields->has($fieldName)) {
            throw new FormFieldException(sprintf(
                'Поле с именем [%s] не найдено', $fieldName
            ));
        }

        return $this->fields->get($fieldName);
    }

    /**
     * Получить исходное значение поля
     *
     * @param string $fieldName Название поля
     * @return string
     *
     * @throws FormFieldException
     */
    public function getFieldValue(string $fieldName)
    {
        if (($field = $this->getField($fieldName)) && $field instanceof ValueInterface) {
            return $field->getValue(); 
        }

        return null;
    }

    /**
     * Проверка полей формы
     * @return void
     */
    public function validated(array $validationData = [])
    {
        if (!$validationData) 
            $validationData = $this->arrayName ? Arr::get(request()->all(), $this->arrayName, []) : request()->all();

        /**
         * Получаем данные для валидатора
         */
        $validationRules = $this->getValidationRules();
        $validationNames = $this->getValidationNames();
        $validationMessages = $this->getValidationMessages();
        
        /**
         * Создаём экземпляр валидатора для проведения валидации полей
         * В валидатор передём данные формы, правила валидации, спец сообщения и название полей
         */
        $validator = validator($validationData, $validationRules, $validationMessages, $validationNames);

        $this->beforeValidation($validator);

        /**
         * Производим валидацию полей
         */
        $validator->validate();

        $this->afterValidation($validator);
    }

    /**
     * Инициализация конфигурации формы. Данные метод предназначен для управления
     * конфигурации формы
     *
     * @return void
     */
    protected function initConfig()
    {
        $this->addConfigOptions([
            'method', 'url', 'wrapperClass', 'modelId', 'routeName'
        ]);
    }

    /**
     * Регистрация полей данной формы
     *
     * @return void
     */
    public function registerFields() {}

    /**
     * Возвращает данные формы из запроса
     *
     * @param mixed Исходные данные, если данные не переданы, то они будут взяты из запроса
     * @return array
     */
    public function getSaveData($data = null)
    {
        if (!$data)
            $data = $this->arrayName ? Arr::get(request()->all(), $this->arrayName, []) : request()->all();

        /**
         * Задаем значения всем полям
         */
        $this->setData($data);


        $result = [];

        if (count($this->fields) > 0) {
            foreach ($this->fields as $field) {
                if ($field instanceof ValueInterface) {
                    if ($field->getSaveValue() !== FormField::NOT_SAVE_DATA) {
                        $result = $field->getSaveData($result);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Наследование запроса
     *
     * @param $query
     * @return void
     */
    protected function extendModel($query) {}
}
