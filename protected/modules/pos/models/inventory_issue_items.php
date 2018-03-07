<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class InventoryIssueItemsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_inventory_issue_item';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['ii_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($ii_id = 0)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, ti.ii_number, p.title AS product_name  
            FROM {tablePrefix}ext_inventory_issue_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_inventory_issue ti ON ti.id = t.ii_id 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE 1';

        $params = [];
        if ($ii_id > 0) {
            $sql .= ' AND t.ii_id =:ii_id';
            $params['ii_id'] = $ii_id;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*, a.name AS created_by_name, ti.ii_number, p.title AS product_name 
            s.name AS supplier_name 
            FROM {tablePrefix}ext_inventory_issue_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_inventory_issue ti ON ti.id = t.ii_id  
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }
}
