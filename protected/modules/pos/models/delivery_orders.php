<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class DeliveryOrdersModel extends \Model\BaseModel
{
    const STATUS_ONPROCESS = 'onprocess';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELED = 'canceled';

    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_delivery_order';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['do_number', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = null)
    {
        $sql = 'SELECT t.*, a.name AS admin_name, 
            po.po_number AS po_number, sp.name AS supplier_name, 
            wg.title AS wh_group_name, ab.name AS completed_by_name   
            FROM {tablePrefix}ext_delivery_order t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id 
            LEFT JOIN {tablePrefix}ext_supplier sp ON sp.id = po.supplier_id 
            LEFT JOIN {tablePrefix}ext_warehouse_group wg ON wg.id = po.wh_group_id 
            LEFT JOIN {tablePrefix}admin ab ON ab.id = t.completed_by 
            WHERE 1';

        $params = [];
        if (is_array($data)) {
            if (isset($data['status'])) {
                $sql .= ' AND t.status =:status';
                $params['status'] = $data['status'];
            }
            if (isset($data['po_id'])) {
                $sql .= ' AND t.po_id =:po_id';
                $params['po_id'] = $data['po_id'];
            }
            if (isset($data['supplier_id'])) {
                if (is_array($data['supplier_id'])) {
                    $supplier_id = implode(", ", $data['supplier_id']);
                    $sql .= ' AND po.supplier_id IN ('.$supplier_id.')';
                } else {
                    $sql .= ' AND po.supplier_id =:supplier_id';
                    $params['supplier_id'] = $data['supplier_id'];
                }
            }

            if (isset($data['wh_group_id'])) {
                if (is_array($data['wh_group_id'])) {
                    $group_id = implode(", ", $data['wh_group_id']);
                    if (!isset($data['supplier_id']))
                        $sql .= ' AND po.wh_group_id IN ('.$group_id.')';
                    else {
                        if (strpos($sql, 'AND po.supplier_id') !== false) {
                            $sql = str_replace(
                                ['AND po.supplier_id'],
                                ['AND (po.supplier_id'], $sql);
                            $sql .= ' OR po.wh_group_id IN ('.$group_id.'))';
                        }
                    }
                } else {
                    if (!isset($data['supplier_id']))
                        $sql .= ' AND po.wh_group_id =:wh_group_id';
                    else {
                        if (strpos($sql, 'AND po.supplier_id') !== false) {
                            $sql = str_replace(
                                ['AND po.supplier_id'],
                                ['AND (po.supplier_id'], $sql);
                            $sql .= ' OR po.wh_group_id =:wh_group_id)';
                        }
                    }
                    $params['wh_group_id'] = $data['wh_group_id'];
                }
            }
        }

        $sql .= ' ORDER BY t.created_at DESC';

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
        $sql = 'SELECT t.*,  a.name AS created_by_name, 
            po.po_number AS po_number    
            FROM {tablePrefix}ext_delivery_order t 
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by 
            LEFT JOIN {tablePrefix}ext_purchase_order po ON po.id = t.po_id 
            WHERE t.id =:id';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, ['id'=>$id] );

        return $row;
    }

    public function getLastDoNumber($do_serie = null)
    {
        $sql = 'SELECT MAX(t.do_nr) AS max_nr 
            FROM {tablePrefix}ext_delivery_order t 
            WHERE 1';

        $params = [];
        if (!empty($do_serie)) {
            $sql .= ' AND t.do_serie =:do_serie';
            $params['do_serie'] = $do_serie;
        }

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $row = R::getRow( $sql, $params );

        return $row;
    }
}
