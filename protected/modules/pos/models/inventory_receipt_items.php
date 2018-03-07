<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class InventoryReceiptItemsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_inventory_receipt_item';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['ir_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($ir_id = 0)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, ir.ir_number, p.title AS product_name  
            FROM {tablePrefix}ext_inventory_receipt_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_inventory_receipt ir ON ir.id = t.ir_id 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE 1';

        $params = [];
        if ($ir_id > 0) {
            $sql .= ' AND t.ir_id =:ir_id';
            $params['ir_id'] = $ir_id;
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
        $sql = 'SELECT t.*, a.name AS created_by_name, ir.ir_number, p.title AS product_name 
            FROM {tablePrefix}ext_inventory_receipt_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_inventory_receipt ir ON ir.id = t.ir_id  
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    /**
     * @param $data
     * @return array
     */
    public function getDataByProduct($data)
    {
        $sql = 'SELECT t.* 
            FROM {tablePrefix}ext_inventory_receipt_item t 
            WHERE t.ir_id =:ir_id AND t.product_id =:product_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['ir_id'=>$data['ir_id'], 'product_id'=>$data['product_id']] );

        return $row;
    }

    public function getCountStockByTIItem($ti_item_id)
    {
        $sql = 'SELECT SUM(t.quantity) AS total 
            FROM {tablePrefix}ext_inventory_receipt_item t 
            WHERE t.ti_item_id =:ti_item_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['ti_item_id'=>$ti_item_id] );

        return $row['total'];
    }
}
