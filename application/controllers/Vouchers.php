<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Vouchers extends CI_Controller
{
    // Public Variable
    public $session, $custom_curl;
    public $csrf_token, $auth;
    public $topBarContent, $navBarContent;

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
        $this->load->library("voucherslib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("voucherredeemlib", array(
            "sql" => $this->customSQL
        ));
    }

    public function index() 
    {        
        $page = $this->input->get("page", TRUE) ?: 0;
        $search = $this->input->get("search", TRUE) ?: "";
        $orderDirection = $this->input->get("order-direction", TRUE) ?: "DESC";

        $tempUser = $this->checkIsValid();

        $where = "`t_voucher`.`start_date` <= '". date("Y-m-d") ."' AND `t_voucher`.`end_date` >= '". date("Y-m-d") ."'";
        
        $res = $this->voucherslib->filter($where, $search, $page, $orderDirection);
        $size = $this->voucherslib->size($where, $search, $orderDirection);
        $this->request->res(200, $res, "Berhasil memuat data voucher", array(
            "page" => $page,
            "search" => $search,
            "order-direction" => $orderDirection,
            "size" => array(
                "fetch" => count($res),
                "total" => $size
            )
        ));
    }

    public function get($code)
    {
        $tempUser = $this->checkIsValid();        

        $where = "`t_voucher`.`start_date` <= '". date("Y-m-d") ."' AND `t_voucher`.`end_date` >= '". date("Y-m-d") ."' AND `t_voucher`.`voucher_code` = '$code'";
        $res = $this->voucherslib->get($where);

        if (empty($res))
            $this->request->res(404, null, "Kode voucher tidak ditemukan / expired", null);

        // Check is already redeem / not
        $check = $this->voucherredeemlib->get("`t_voucher_redeem_user`.`id_t_voucher` = " . $res["id"] . " AND `t_voucher_redeem_user`.`id_m_users` = " . $tempUser["id"]);

        if (!empty($check))
            $this->request->res(403, null, "Kode voucher sudah di redeem sebelumnya", null);
        
        $this->request->res(200, $res, "Berhasil memuat detail voucher", null);
    }

    private function checkIsValid()
    {
        $tempUser = $this->customSQL->checkValid();
        if (count($tempUser) != 1)
            $this->request->res(403, null, "Tidak ter-otentikasi", null);
        $tempUser = $tempUser[0];
        return $tempUser;
    }

    private function checkID($checkID)
    {
        if ($checkID == -1)
            $this->request->res(500, null, "Terjadi kesalahan, silahkan cek masukan anda", null);
    }

}
