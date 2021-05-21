<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Transactions extends CI_Controller
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
        $this->load->library("transactionslib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("transactionitemlib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("feedbackslib", array(
            "sql" => $this->customSQL
        ));
    }

    public function index() 
    {
        $status = $this->input->get("status");
        $page = $this->input->get("page", TRUE) ?: 0;
        $search = $this->input->get("search", TRUE) ?: "";
        $orderDirection = $this->input->get("order-direction", TRUE) ?: "DESC";

        $tempUser = $this->checkIsValid();

        if (isset($status) && !empty($status))
        {
            $res = $this->transactionslib->filter("`t_transaction_product`.`id_m_users` = " . $tempUser["id"] . " AND `t_transaction_product`.`status` = $status", $search, $page, $orderDirection);
            $size = $this->transactionslib->size("`t_transaction_product`.`id_m_users` = " . $tempUser["id"] . " AND `t_transaction_product`.`status` = $status", $search, $orderDirection);
        }
        else {
            if ($status === 0 || $status === "0")
            {
                $res = $this->transactionslib->filter("`t_transaction_product`.`id_m_users` = " . $tempUser["id"] . " AND `t_transaction_product`.`status` = $status", $search, $page, $orderDirection);
                $size = $this->transactionslib->size("`t_transaction_product`.`id_m_users` = " . $tempUser["id"] . " AND `t_transaction_product`.`status` = $status", $search, $orderDirection);
            } else {
                $res = $this->transactionslib->filter("`t_transaction_product`.`id_m_users` = " . $tempUser["id"], $search, $page, $orderDirection);
                $size = $this->transactionslib->size("`t_transaction_product`.`id_m_users` = " . $tempUser["id"], $search, $orderDirection);
            }
        }
            
        $temp = array();
        foreach ($res as $item) {
            $item["item"] = $this->transactionitemlib->get_once("`t_transaction_product_item`.`id_t_transaction_product` = " . $item["id"], $search);
            if (isset($item["item"]) && !empty($item["item"]))
                $temp[] = $item;
        }
        $res = $temp;

        $this->request->res(200, $res, "Berhasil memuat data transaksi aktif", array(
            "page" => $page,
            "search" => $search,
            "order-direction" => $orderDirection,
            "size" => array(
                "fetch" => count($res),
                "total" => $size
            )
        ));
    }

    public function get($id)
    {
        $tempUser = $this->checkIsValid();

        $trx = $this->transactionslib->get("`t_transaction_product`.`id` = " . $id);
        $trx["items"] = $this->transactionitemlib->all("`t_transaction_product_item`.`id_t_transaction_product` = " . $id);
        
        $this->request->res(200, $trx, "Berhasil memuat detail transaksi", null);
    }

    public function update($id)
    {
        $status = $this->input->post_get("status", TRUE) ?: "1";

        $tempUser = $this->checkIsValid();

        $check = $this->transactionslib->get("`t_transaction_product`.`id` = " . $id);

        if (empty($check))
            $this->request->res(403, null, "Transaksi tidak ditemukan", null);

        $statusBefore = (int)$check["status"];

        if ((int) $status <= $statusBefore)
            $this->request->res(403, null, "Transaksi anda tidak dapat diubah statusnya", null);

        $data = array(
            "updated_at" => date("Y-m-d H:i:s"),            
            "status" => $status
        );

        $checkID = $this->transactionslib->update("`t_transaction_product`.`id` = " . $id, $data);

        $this->checkID($checkID);

        $res = $this->transactionslib->get("`t_transaction_product`.`id` = " . $id);

        $this->request->res(200, $res, "Berhasil mengubah status", null);
    }

    public function cancel($id)
    {
        $tempUser = $this->checkIsValid();

        $data = $this->transactionslib->get("`t_transaction_product`.`id` = " . $id);

        if ($data["status"] == "0" || $data["status"] == 0) {
            $data = array(
                "updated_at" => date("Y-m-d H:i:s"),            
                "status" => "5"
            );
            $checkID = $this->transactionslib->update("`t_transaction_product`.`id` = " . $id, $data);
            $this->checkID($checkID);

            $data = $this->transactionslib->get("`t_transaction_product`.`id` = " . $id);

            $this->request->res(200, $data, "Berhasil membatalkan transaksi", null);
        }

        $this->request->res(403, null, "Transaksi anda sudah tidak dapat dibatalkan", null);
    }

    public function addFeedback($id)
    {
        $tempUser = $this->checkIsValid();
        $req = $this->request->raw();

        if (!isset($req["review"]) || !isset($req["rating"])) 
        $this->request->res(400, null, "Harap masukkan data rating dan review", null);

        $trx = $this->transactionslib->get("`t_transaction_product`.`id` = " . $id . " AND `t_transaction_product`.`id_m_users` = " . $tempUser["id"]);
        if (empty($trx))
        $this->request->res(404, null, "Maaf transaksi tidak ditemukan", null);

        $trx["items"] = $this->transactionitemlib->all("`t_transaction_product_item`.`id_t_transaction_product` = " . $id);

        if ($trx["status"] != 4)
        $this->request->res(403, null, "Maaf transaksi belum dapat direview", null);

        foreach ($trx["items"] as $item) {
            
            $checkID = $this->feedbackslib->create(array(
                "rating" => $req["rating"],
                "comment" => $req["review"],
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"),
                "id_t_products" => $item["product"]["id"],
                "id_m_users" => $tempUser["id"]
            ));
            $this->checkID($checkID);

        }

        $trx["is_already_feedback"] = 1;
        $this->transactionslib->update(array(
            "id" => $trx["id"]
        ), array(
            "is_already_feedback" => 1
        ));

        $this->request->res(200, $trx, "Berhasil memberikan feedback", null);
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
