<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class MUsersLib {

    protected $params;
    protected $table;
    protected $CI;

    public function __construct($params)
    {
        // Do something with $params
        $this->params = $params;
        $this->table = "m_users";
        $this->CI =& get_instance();

        $this->CI->load->library("MMediasLib", $params);
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
            SELECT `m_users`.* FROM `m_users`
            WHERE $where
        ")->result_array();

        if (count($data) != 1)
            return null;

        $item = $data[0];
        
        // Load Media
        $temp = $this->CI->mmediaslib->get("`m_medias`.`id` = " . $item['id_m_medias']);
        if (!empty($temp)) {
            unset($temp["id"]);
            unset($temp["created_at"]);
            unset($temp["updated_at"]);
        }

        $item["media"] = $temp;
        unset($item["id_m_medias"]);

        return $item;
    }

    public function size($search, $orderDirection)
    {
        // Load Icon By Filter
        return $this->params["sql"]->query("
            SELECT count(`m_users`.`id`) as `total` FROM `m_users`
            WHERE `m_users`.`email` LIKE '%".$search."%' OR `m_users`.`full_name` LIKE '%".$search."%'
            OR `m_users`.`phone_number` LIKE '%".$search."%'
            OR `m_users`.`address` LIKE '%".$search."%'
            ORDER BY `m_users`.`created_at` $orderDirection
        ")->row()->total;
    }

}
