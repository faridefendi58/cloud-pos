<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class OrdersModel extends \Model\BaseModel
{
    const PAYMENT_TYPE_CASH = 1;
    const PAYMENT_TYPE_CREDIT = 2;

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_order';
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
    public function getData($data = array())
    {
        $sql = 'SELECT t.*, p.title AS product_name, i.cash, i.serie, i.nr, i.status, i.paid_at, i.config  
            FROM {tablePrefix}ext_order t 
            LEFT JOIN {tablePrefix}ext_product p ON p.id = t.product_id 
            LEFT JOIN {tablePrefix}ext_invoice i ON i.id = t.invoice_id
            WHERE 1';

        $params = [];
        if (isset($data['type'])) {
            $sql .= ' AND t.type =:order_type';
            $params['order_type'] = $data['type'];
        }

        if (isset($data['status'])) {
            $sql .= ' AND t.status =:order_status';
            $params['order_status'] = $data['status'];
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    public function getNextGroupId()
    {
        $sql = 'SELECT MAX(t.group_id) AS max_group_id 
            FROM {tablePrefix}ext_order t 
            WHERE 1';

        $params = [];

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row['max_group_id'] + 1;
    }
}
