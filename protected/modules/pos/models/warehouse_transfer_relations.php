<?php
namespace Model;

require_once __DIR__ . '/../../../models/base.php';

class WarehouseTransferRelationsModel extends \Model\BaseModel
{
    public static function model($className=__CLASS__)
    {
        return parent::model($className);
    }

    public function tableName()
    {
        return 'ext_warehouse_transfer_relation';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        // NOTE: you should only define rules for those attributes that
        // will receive user inputs.
        return [
            ['warehouse_id', 'required'],
        ];
    }

    /**
     * @return array
     */
    public function getData($data = array())
    {
        $add_join = ''; $add_select = '';
        if (isset($data['just_warehouse'])) {
            $add_select .= ', w.title AS warehouse_name';
            $add_join .= ' LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_rel_id ';
        }
        if (isset($data['just_supplier'])) {
            $add_select .= ', s.name AS supplier_name';
            $add_join .= ' LEFT JOIN {tablePrefix}ext_supplier s ON s.id = t.supplier_id ';
        }
        $sql = 'SELECT t.*, a.name AS admin_name '. $add_select .'     
            FROM {tablePrefix}ext_warehouse_transfer_relation t '. $add_join .'          
            LEFT JOIN {tablePrefix}admin a ON a.id = t.created_by   
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['rel_type'])) {
            $sql .= ' AND t.rel_type =:rel_type';
            $params['rel_type'] = $data['rel_type'];
        }

        if (isset($data['admin_id'])) {
            $sql .= ' AND t.admin_id =:admin_id';
            $params['admin_id'] = $data['admin_id'];
        }

        if (isset($data['just_warehouse'])) {
            $sql .= ' AND t.warehouse_rel_id > 0';
        }

        if (isset($data['just_supplier'])) {
            $sql .= ' AND t.supplier_id > 0';
        }

        if (isset($data['just_non_transaction'])) {
            $sql .= ' AND t.non_transaction_type IS NOT NULL';
        }

        $sql .= ' ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );

        return $rows;
    }

    /**
     * @param $id
     * @return array
     */
    public function getRelatedWarehouses($data = [])
    {
        $data['just_warehouse'] = true;
        $datas = self::getData($data);
        $items = [];
        if (is_array($datas)) {
            foreach ($datas as $data) {
                $items[$data['id']] = $data['warehouse_name'];
            }
        }

        return $items;
    }

    public function getAllRelatedWarehouses($data = [])
    {
        $sql = 'SELECT t.*, w.title, w.address, w.phone, w.active, w.configs     
            FROM {tablePrefix}ext_warehouse_transfer_relation t 
            LEFT JOIN {tablePrefix}ext_warehouse w ON w.id = t.warehouse_rel_id     
            WHERE 1';

        $params = [];
        if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['rel_type'])) {
            $sql .= ' AND t.rel_type =:rel_type';
            $params['rel_type'] = $data['rel_type'];
        }

        $sql .= ' AND t.warehouse_rel_id > 0 ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );
        $items = [];
        if (is_array($rows)) {
            foreach ($rows as $data) {
                $data['configs'] = json_decode($data['configs'], true);
                if ($data['rel_type'] == 'in') {
                    $items['in'][] = $data;
                } elseif ($data['rel_type'] == 'out') {
                    $items['out'][] = $data;
                }
            }
        }

        return $items;
    }

    public function getRelatedSuppliers($data = [])
    {
        $data['just_supplier'] = true;
        $datas = self::getData($data);
        $items = [];
        if (is_array($datas)) {
            foreach ($datas as $data) {
                $items[$data['id']] = $data['supplier_name'];
            }
        }

        return $items;
    }

    public function getAllRelatedSuppliers($data = [])
    {
        $sql = 'SELECT t.*, s.name AS supplier_name, s.configs AS supplier_configs     
            FROM {tablePrefix}ext_warehouse_transfer_relation t 
            LEFT JOIN {tablePrefix}ext_supplier s ON s.id = t.supplier_id     
            WHERE 1';

        $params = [];
        if (isset($data['supplier_id'])) {
            $sql .= ' AND t.supplier_id =:v';
            $params['supplier_id'] = $data['supplier_id'];
        }

		if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['rel_type'])) {
            $sql .= ' AND t.rel_type =:rel_type';
            $params['rel_type'] = $data['rel_type'];
        }

        $sql .= ' AND t.supplier_id > 0 ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );
        $items = [];
        if (is_array($rows)) {
            foreach ($rows as $data) {
                if (!empty($data['supplier_configs'])) {
                    $data['supplier_configs'] = json_decode($data['supplier_configs'], true);
                }
                if ($data['rel_type'] == 'in') {
                    $items['in'][] = $data;
                } elseif ($data['rel_type'] == 'out') {
                    $items['out'][] = $data;
                }
            }
        }

        return $items;
    }

    public function getRelatedNonTrans($data = [])
    {
        $data['just_non_transaction'] = true;
        $datas = self::getData($data);
        $items = [];
        if (is_array($datas)) {
            foreach ($datas as $data) {
                $items[$data['id']] = $data['non_transaction_type'];
            }
        }

        return $items;
    }

    public function getAllRelatedNonTrans($data = [])
    {
        $sql = 'SELECT t.*     
            FROM {tablePrefix}ext_warehouse_transfer_relation t      
            WHERE 1';

        $params = [];

		if (isset($data['warehouse_id'])) {
            $sql .= ' AND t.warehouse_id =:warehouse_id';
            $params['warehouse_id'] = $data['warehouse_id'];
        }

        if (isset($data['rel_type'])) {
            $sql .= ' AND t.rel_type =:rel_type';
            $params['rel_type'] = $data['rel_type'];
        }

        $sql .= ' AND t.non_transaction_type IS NOT NULL ORDER BY t.id DESC';

        $sql = str_replace(['{tablePrefix}'], [$this->_tbl_prefix], $sql);

        $rows = R::getAll( $sql, $params );
        $items = [];
        if (is_array($rows)) {
            foreach ($rows as $data) {
                if ($data['rel_type'] == 'in') {
                    $items['in'][] = $data;
                } elseif ($data['rel_type'] == 'out') {
                    $items['out'][] = $data;
                }
            }
        }

        return $items;
    }
}
