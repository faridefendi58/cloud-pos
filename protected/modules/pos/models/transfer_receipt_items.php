<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class TransferReceiptItemsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_transfer_receipt_item';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['tr_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($tr_id = 0)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, tr.tr_number, p.title AS product_name  
            FROM {tablePrefix}ext_transfer_receipt_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_transfer_receipt tr ON tr.id = t.tr_id 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE 1';

        $params = [];
        if ($tr_id > 0) {
            $sql .= ' AND t.tr_id =:tr_id';
            $params['tr_id'] = $tr_id;
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
        $sql = 'SELECT t.*, a.name AS created_by_name, tr.tr_number, tr.base_price, p.title AS product_name 
            FROM {tablePrefix}ext_transfer_receipt_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_transfer_receipt tr ON tr.id = t.tr_id  
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
            FROM {tablePrefix}ext_transfer_receipt_item t 
            WHERE t.tr_id =:tr_id AND t.product_id =:product_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['tr_id'=>$data['tr_id'], 'product_id'=>$data['product_id']] );

        return $row;
    }

    public function getCountStockByTIItem($ti_item_id)
    {
        $sql = 'SELECT SUM(t.quantity) AS total 
            FROM {tablePrefix}ext_transfer_receipt_item t 
            WHERE t.ti_item_id =:ti_item_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['ti_item_id'=>$ti_item_id] );

        return $row['total'];
    }
}
