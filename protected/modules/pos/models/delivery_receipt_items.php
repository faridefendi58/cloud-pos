<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class DeliveryReceiptItemsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_delivery_receipt_item';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['dr_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($dr_id = 0)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, dr.dr_number, p.title AS product_name  
            FROM {tablePrefix}ext_delivery_receipt_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_delivery_receipt dr ON dr.id = t.dr_id 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            WHERE 1';

        $params = [];
        if ($dr_id > 0) {
            $sql .= ' AND t.dr_id =:dr_id';
            $params['dr_id'] = $dr_id;
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
        $sql = 'SELECT t.*, a.name AS created_by_name, dr.dr_number, p.title AS product_name 
            FROM {tablePrefix}ext_delivery_receipt_item t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_delivery_receipt dr ON dr.id = t.dr_id  
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
            FROM {tablePrefix}ext_delivery_receipt_item t 
            WHERE t.dr_id =:dr_id AND t.product_id =:product_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['dr_id'=>$data['dr_id'], 'product_id'=>$data['product_id']] );

        return $row;
    }

    public function getProductStock()
    {
        $sql = 'SELECT t.product_id, t.title, t.unit, SUM(t.available_qty) AS qty 
            FROM {tablePrefix}ext_delivery_receipt_item t 
            WHERE 1';

        $sql .= ' GROUP BY t.product_id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql );

        return $rows;
    }

    public function getAvailableProduct($product_id)
    {
        $sql = 'SELECT t.* 
            FROM {tablePrefix}ext_delivery_receipt_item t 
            WHERE t.product_id =:product_id AND t.available_qty > 0';

        $sql .= ' ORDER BY t.id ASC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['product_id' => $product_id] );

        return $row;
    }

    /**
     * Method to substract stok on transfer execution
     * @param $product_id
     * @param $quantity
     * @return bool
     */
    public function subtracting_stok($product_id, $quantity)
    {
        $sql = 'product_id =:product_id AND available_qty > 0';

        $rows = R::find($this->tableName, $sql, ['product_id' => $product_id]);
        if (is_array($rows)) {
            foreach ($rows as $i => $bean) {
                if ($bean instanceof \RedBeanPHP\OODBBean && ($quantity > 0)) {
                    $available_qty = $bean->available_qty;
                    if ($available_qty < $quantity) {
                        $bean->available_qty = 0;
                    } else {
                        $bean->available_qty = $available_qty - $quantity;
                    }
                    $bean->updated_at = date("Y-m-d H:i:s");
                    $update = $this->update($bean);
                    if ($update) {
                        $quantity = $quantity - $available_qty;
                    }
                }
            }
        }

        return ($quantity <= 0)? true : false;
    }
}
