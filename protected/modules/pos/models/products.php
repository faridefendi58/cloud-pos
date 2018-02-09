<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class ProductsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_product';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['title', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData()
    {
        $sql = 'SELECT t.*, c.title AS category_name  
            FROM {tablePrefix}ext_product t 
            LEFT JOIN {tablePrefix}ext_product_category c ON c.id = t.product_category_id 
            WHERE 1';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }
}
