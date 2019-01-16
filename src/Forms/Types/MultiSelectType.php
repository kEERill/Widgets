<?php namespace Keerill\Widgets\Forms\Types;

class MultiSelectType extends SelectType
{
    /**
     * @var integer Максимальное число элементов, которые можно выбрать
     */
    public $maxSelect = 10;

    /**
     * @inheritdoc
     */
    protected $template = 'widgets::forms.fields.multiselect';

    /**
     * @inheritdoc
     */
    public function initConfig()
    {
        parent::initConfig();
        
        $this->fillConfig([
            'maxSelect'
        ]);
    }

    /**
     * Возвращает атрибуты для input
     * @return array
     */
    public function getInputAttributes()
    {
        $baseClasses = parent::getInputAttributes();
        $baseClasses[] = 'multiple';

        return $baseClasses;
    }

}