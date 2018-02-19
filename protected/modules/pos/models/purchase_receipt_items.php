<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class PurchaseReceiptItemsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_purchase_receipt_item';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['pr_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($pr_id = 0)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, pr.pr_number, p.title AS product_name  
            FROM {tablePrefix}ext_purchase_receipt_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_purchase_receipt pr ON pr.id = t.pr_id 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE 1';

        $params = [];
        if ($pr_id > 0) {
            $sql .= ' AND t.pr_id =:pr_id';
            $params['pr_id'] = $pr_id;
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
        $sql = 'SELECT t.*, a.name AS created_by_name, pr.pr_number, pr.price_netto, p.title AS product_name 
            s.name AS supplier_name 
            FROM {tablePrefix}ext_purchase_receipt_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_purchase_receipt pr ON pr.id = t.pr_id  
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
            FROM {tablePrefix}ext_purchase_receipt_item t 
            WHERE t.pr_id =:pr_id AND t.product_id =:product_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['pr_id'=>$data['pr_id'], 'product_id'=>$data['product_id']] );

        return $row;
    }

    public function getCountStockByPOItem($po_item_id)
    {
        $sql = 'SELECT SUM(t.quantity) AS total 
            FROM {tablePrefix}ext_purchase_receipt_item t 
            WHERE t.po_item_id =:po_item_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['po_item_id'=>$po_item_id] );

        return $row['total'];
    }
}
