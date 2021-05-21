<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class MMediasLib {

    protected $params;
    protected $table;
    protected $CI;

    public function __construct($params)
    {
        // Do something with $params
        $this->params = $params;
        $this->table = "m_medias";
        $this->CI =& get_instance();
    }

    public function create($data)
    {
        return $this->params["sql"]->create(
            $data, $this->table
        );
    }

    public function filter($search, $page, $orderDirection)
    {
        // Preparing Filter
        $limit = 12;
        $offset = ($page * $limit);

        // Load Icon By Filter
        $data = $this->params["sql"]->query("
            SELECT `m_medias`.* FROM `m_medias`
            WHERE `m_medias`.`url` LIKE '%".$search."%' OR `m_medias`.`file_name` LIKE '%".$search."%'
            ORDER BY `m_medias`.`created_at` $orderDirection
            LIMIT $limit OFFSET $offset
        ")->result_array();

        // Return Response
        return $data;
    }

    public function get($where)
    {
        // Load Icon By Filter
        $data = $this->params["sql"]->query("
            SELECT `m_medias`.* FROM `m_medias`
            WHERE $where
        ")->result_array();

        if (count($data) != 1)
            return null;

        return $data[0];
    }

    public function delete($where)
    {
        return $this->params["sql"]->delete($where, $this->table);
    }

    public function size($search, $orderDirection)
    {
        // Load Icon By Filter
        return $this->params["sql"]->query("
            SELECT count(`m_medias`.`id`) as `total` FROM `m_medias`
            WHERE `m_medias`.`url` LIKE '%".$search."%' OR `m_medias`.`file_name` LIKE '%".$search."%'
            ORDER BY `m_medias`.`created_at` $orderDirection
        ")->row()->total;
    }

}
