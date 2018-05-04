<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class PurchaseOrdersModel extends \Model\BaseModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_ON_PROCESS = 'onprocess';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELED = 'canceled';

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_purchase_order';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['po_number', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = null)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, s.name AS supplier_name, sh.title AS shipment_name, 
            wh.title AS wh_group_name   
            FROM {tablePrefix}ext_purchase_order t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_supplier s ON s.id = t.supplier_id 
            LEFT JOIN {tablePrefix}ext_shipment sh ON sh.id = t.shipment_id  
            LEFT JOIN {tablePrefix}ext_warehouse_group wh ON wh.id = t.wh_group_id  
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }
            $has_combine_open = false;
            if (isset($data['supplier_id'])) {
                if (is_array($data['supplier_id'])) {
                    $supplier_id = implode(", ", $data['supplier_id']);
                    $sql .= ' AND {combine_open}t.supplier_id IN ('.$supplier_id.')';
                    $has_combine_open = true;
                } else {
                    $sql .= ' AND {combine_open}t.supplier_id =:supplier_id';
                    $params['supplier_id'] = $data['supplier_id'];
                    $has_combine_open = true;
                }
            }

            $use_or = false;
            if (isset($data['wh_group_id'])) {
                if (is_array($data['wh_group_id'])) {
                    $group_id = implode(", ", $data['wh_group_id']);
                    if (!isset($data['supplier_id'])) {
                        $sql .= ' AND t.wh_group_id IN (' . $group_id . ')';
                    } else {
                        $sql .= ' OR t.wh_group_id IN (' . $group_id . '){combine_close}';
                        $use_or = true;
                    }
                } else {
                    if (!isset($data['supplier_id'])) {
                        $sql .= ' AND t.wh_group_id =:wh_group_id';
                    } else {
                        $sql .= ' OR t.wh_group_id =:wh_group_id{combine_close}';
                        $use_or = true;
                    }
                    $params['wh_group_id'] = $data['wh_group_id'];
                }
            }

            if (isset($data['is_pre_order'])) {
                $sql .= ' AND t.is_pre_order =:is_pre_order';
                $params['is_pre_order'] = $data['is_pre_order'];
            }
        }

        $sql .= ' ORDER BY t.date_order DESC';

        if (!$use_or) {
            if ($has_combine_open)
                $sql = str_replace(['{tablePrefix}', '{combine_open}'], [$this->_tbl_prefix, ''], $sql);
            else
                $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);
        } else {
            $sql = str_replace(['{tablePrefix}', '{combine_open}', '{combine_close}'], [$this->_tbl_prefix, '(', ')'], $sql);
        }

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getDetail($id)
    {
        $sql = 'SELECT t.*, a.name AS created_by_name, ab.name AS updated_by_name, 
            s.name AS supplier_name, wh.title AS wh_group_name, wh.pic AS wh_group_pic, sh.title AS shipment_name  
            FROM {tablePrefix}ext_purchase_order t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.updated_by 
            LEFT JOIN {tablePrefix}ext_supplier s ON s.id = t.supplier_id 
            LEFT JOIN {tablePrefix}ext_warehouse_group wh ON wh.id = t.wh_group_id 
            LEFT JOIN {tablePrefix}ext_shipment sh ON sh.id = t.shipment_id  
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastPoNumber($po_serie = null)
    {
        $sql = 'SELECT MAX(t.po_nr) AS max_nr 
            FROM {tablePrefix}ext_purchase_order t 
            WHERE 1';

        $params = [];
        if (!empty($po_serie)) {
            $sql .= ' AND t.po_serie =:po_serie';
            $params['po_serie'] = $po_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }

    public function available_items($data)
    {
        $params = [];
        if (isset($data['id'])) {
            $params['po_id'] = $data['id'];
        }

        if (isset($data['po_id'])) {
            $params['po_id'] = $data['po_id'];
        }

        $sql = 'SELECT t.*, po.po_number   
            FROM {tablePrefix}ext_purchase_order_item t 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id 
            WHERE 1';

        if (!empty($params['po_id'])) {
            $sql .= ' AND t.po_id =:po_id';
        }

        $sql .= ' AND t.available_qty > 0';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }
}
