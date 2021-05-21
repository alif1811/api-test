<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class UUserDriverRoutingPickupLib {

    protected $params;
    protected $table;
    protected $CI;

    public function __construct($params)
    {
        // Do something with $params
        $this->params = $params;
        $this->table = "u_user_driver_routing_pickup";
        $this->CI =& get_instance();

        $this->CI->load->library("UUserTrashScheduleLib", $params);
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
            SELECT
                $this->table.order,
                $this->table.status,
                $this->table.day_pickup,
                u_user_trash_schedule.day_schedule,
                u_user_trash_schedule.is_request_scheduling,
                u_user_trash_schedule.schedule_to,
                u_user_trash.current_weight,
                u_user_trash.currennt_height,
                m_users.full_name,
                m_users.address,
                m_medias.uri
            FROM `$this->table`
            JOIN u_user_trash_schedule
            ON $this->table.id_u_user_trash_schedule = u_user_trash_schedule.id
            JOIN u_user_trash
            ON u_user_trash_schedule.id_u_user_trash = u_user_trash.id
            JOIN m_users
            ON u_user_trash.id_m_users = m_users.id
            JOIN m_medias
            ON m_users.id_m_medias = m_medias.id
            WHERE $where
            ORDER BY $this->table.order ASC
        ")->result_array();

        if (count($data) == 0)
            return null;

        return $data;
    }

    public function size($search, $orderDirection)
    {
        // Load Icon By Filter
        return $this->params["sql"]->query("
            SELECT count(`$this->table`.`id`) as `total` FROM `$this->table`
            WHERE `$this->table`.`email` LIKE '%".$search."%'
            ORDER BY `$this->table`.`created_at` $orderDirection
        ")->row()->total;
    }
}
