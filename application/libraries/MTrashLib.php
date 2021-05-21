<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class MTrashLib {

    protected $params;
    protected $table;
    protected $CI;

    public function __construct($params)
    {
        // Do something with $params
        $this->params = $params;
        $this->table = "m_trash";
        $this->CI =& get_instance();
    }

    public function create($data)
    {
        return $this->params["sql"]->create(
            $data, $this->table
        );
    }

    public function update($where, $data)
    {
        return $this->params["sql"]->update(
            $where, $data, $this->table
        );
    }

    public function delete($where)
    {
        return $this->params["sql"]->delete($where, $this->table);
    }

    public function get($where)
    {
        // Load Icon By Filter
        $data = $this->params["sql"]->query("
            SELECT `$this->table`.* FROM `$this->table`
            WHERE $where
        ")->result_array();

        if (count($data) != 1)
            return null;

        $item = $data[0];
        return $item;
    }

    public function size($search, $orderDirection)
    {
        // Load Icon By Filter
        return $this->params["sql"]->query("
            SELECT count(`$this->table`.`id`) as `total` FROM `$this->table`
            WHERE `$this->table`.`email` LIKE '%".$search."%' OR `$this->table`.`full_name` LIKE '%".$search."%'
            OR `$this->table`.`phone_number` LIKE '%".$search."%'
            OR `$this->table`.`address` LIKE '%".$search."%'
            ORDER BY `$this->table`.`created_at` $orderDirection
        ")->row()->total;
    }

}
