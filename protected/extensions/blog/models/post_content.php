<?php
namespace ExtensionsModel;

require_once __DIR__ . '/../../../models/base.php';

class PostContentModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_post_content';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['post_id, title, content', 'required'],
            ['title', 'length', 'min'=>3, 'max'=>128],
            ['slug', 'length', 'min'=>3, 'max'=>256],
            ['slug, created_at', 'required', 'on'=>'create'],
            //['post_id, language', 'numerical', 'integerOnly' => true],
        ];
    }
}
