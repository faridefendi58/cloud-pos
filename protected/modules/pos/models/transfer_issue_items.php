<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class TransferIssueItemsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_transfer_issue_item';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['ti_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($ti_id = 0)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, ti.ti_number, ti.base_price, p.title AS product_name  
            FROM {tablePrefix}ext_transfer_issue_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_transfer_issue ti ON ti.id = t.ti_id 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE 1';

        $params = [];
        if ($ti_id > 0) {
            $sql .= ' AND t.ti_id =:ti_id';
            $params['ti_id'] = $ti_id;
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
        $sql = 'SELECT t.*, a.name AS created_by_name, ti.ti_number, ti.base_price, p.title AS product_name 
            s.name AS supplier_name 
            FROM {tablePrefix}ext_transfer_issue_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_transfer_issue ti ON ti.id = t.ti_id  
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }
}
