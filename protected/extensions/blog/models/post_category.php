<?php
namespace ExtensionsModel;

require_once __DIR__ . '/../../../models/base.php';

class PostCategoryModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_post_category';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['category_name, slug', 'required'],
            ['parent_id', 'numerical', 'integerOnly' => true],
            ['created_at', 'required', 'on'=>'create'],
        ];
    }
}
