<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Route extends CI_Controller
{
    // Public Variable
    public $session, $custom_curl;
    public $csrf_token, $auth;

    public function __construct()
    {
        parent::__construct();

        // Load Model
        $this->load->model("tokenize");
        $this->load->model("customSQL");
        $this->load->model("request");

        // Load Helper
        $this->session = new Session_helper();
        $this->custom_curl = new Mycurl_helper("");

        // Init Request
        $this->request->init($this->custom_curl);

        // Load Library
        $this->load->library("MUsersLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("MMediasLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("UUserDriverRoutingPickupLib", array(
            "sql" => $this->customSQL
        ));
    }

    public function getRoute()
    {
        $UUser = $this->checkIsValid();

        // Get Info Route
        $UUserDriverRoutingPickupLib = $this->uuserdriverroutingpickuplib->get("`u_user_driver_routing_pickup`.`id_m_users` = '" . $UUser['id_m_users'] . "'");

        $this->request->res(200, $UUserDriverRoutingPickupLib, "Berhasil mengambil data", null);
    }

    private function checkID($checkID)
    {
        if ($checkID == -1)
            $this->request->res(500, null, "Terjadi kesalahan, silahkan cek masukan anda", null);
    }

    private function checkIsValid()
    {
        $tempUser = $this->customSQL->checkValid();
        if (count($tempUser) != 1)
            $this->request->res(403, null, "Tidak ter-otentikasi", null);
        $tempUser = $tempUser[0];
        return $tempUser;
    }
}
