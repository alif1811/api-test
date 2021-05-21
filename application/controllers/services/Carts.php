<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Carts extends CI_Controller
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
        $this->load->library("services/cartslib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("transactionslib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("transactionitemlib", array(
            "sql" => $this->customSQL
        ));
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

        $res = $this->cartslib->filter("`t_cart_product_user`.`id_m_users` = " . $tempUser["id"] . " AND `t_cart_product_user`.`is_visible` = 0", $search, $page, $orderDirection);
        $size = $this->cartslib->size("`t_cart_product_user`.`id_m_users` = " . $tempUser["id"] . " AND `t_cart_product_user`.`is_visible` = 0", $search, $orderDirection);
        $this->request->res(200, $res, "Berhasil memuat data cart", array(
            "page" => $page,
            "search" => $search,
            "order-direction" => $orderDirection,
            "size" => array(
                "fetch" => count($res),
                "total" => $size
            )
        ));
    }

    public function update($id)
    {
        $qty = $this->input->post_get("qty", TRUE) ?: 1;

        $tempUser = $this->checkIsValid();

        $data = array(
            "updated_at" => date("Y-m-d H:i:s"),
            "qty" => $qty
        );

        $checkID = $this->cartslib->update("`t_cart_product_user`.`id` = " . $id, $data);

        $this->checkID($checkID);

        $res = $this->cartslib->get("`t_cart_product_user`.`id` = " . $id);

        $this->request->res(200, $res, "Berhasil mengubah cart", null);
    }

    public function delete($id)
    {
        $tempUser = $this->checkIsValid();

        $data = $this->cartslib->get("`t_cart_product_user`.`id` = " . $id);

        $checkID = $this->cartslib->delete(array("id" => $id));
        $this->checkID($checkID);

        $this->request->res(200, $data, "Berhasil menghapus produk dari cart", null);
    }

    public function checkout()
    {
        $tempUser = $this->checkIsValid();
        $carts = $this->cartslib->all("`t_cart_product_user`.`id_m_users` = " . $tempUser["id"] . " AND `t_cart_product_user`.`is_visible` = 0");

        if (count($carts) == 0)
            $this->request->res(400, null, "Anda belum memiliki barang di keranjang", null);

        $total = 0;
        $trxTemp = array();
        foreach($carts as $item) {
            // Calculate Subtotal
            $qty = (int)$item["qty"];
            $discount = (int)$item["product"]["discount"];
            $product_price = (int)$item["product"]["product_price"];
            $product_price_after_discount = $product_price + ($discount * $product_price);
            $subtotal = ($qty * $product_price_after_discount);

            // Insert To Total
            $total += $subtotal;

            // Create Trx Temp
            $trxTemp[] = array(
                "qty" => $qty,
                "sub_total" => $subtotal,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"), 
                "id_m_users" => $tempUser["id"],
                "id_t_products" => $item["product"]["id"]
            );
        }

        // Check If Have Voucher
        $voucher = $this->input->post_get("voucher", TRUE) ?: "";
        $discountPrice = 0;
        if (!empty($voucher)) {
            $where = "`t_voucher`.`start_date` <= '". date("Y-m-d") ."' AND `t_voucher`.`end_date` >= '". date("Y-m-d") ."' AND `t_voucher`.`voucher_code` = '$voucher'";
            $res = $this->voucherslib->get($where);
            
            if (!empty($res)) {
                $total -= (int) $res["discount_price"];
                $discountPrice = (int) $res["discount_price"];

                // Check is already redeem / not
                $check = $this->voucherredeemlib->get("`t_voucher_redeem_user`.`id_t_voucher` = " . $res["id"] . " AND `t_voucher_redeem_user`.`id_m_users` = " . $tempUser["id"]);

                if (!empty($check))
                    $this->request->res(403, null, "Kode voucher sudah di redeem sebelumnya", null);

                // Do Add To Redeem
                $checkID = $this->voucherredeemlib->create(array(
                    "id_t_voucher" => $res["id"],
                    "id_m_users" => $tempUser["id"]
                ));
                $this->checkID($checkID);
            } else $this->request->res(404, null, "Kode voucher tidak ditemukan / expired", null);
        }

        // Create Trx
        $checkID = $this->transactionslib->create(array(
            "transaction_code" => md5(date_timestamp_get(date_create()) . "-" . $tempUser["id"]), 
            "total" => $total,
            "status" => 0,
            "discount_price" => $discountPrice,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"), 
            "id_m_users" => $tempUser["id"]
        ));
        $this->checkID($checkID);
        $trx = $this->transactionslib->get("`t_transaction_product`.`id` = " . $checkID);

        // Create Trx Item
        $trxItems = array();
        foreach ($trxTemp as $item) {
            $item["id_t_transaction_product"] = $checkID;
            $checkIDItem = $this->transactionitemlib->create($item);
            $this->checkID($checkIDItem);
            $temp = $this->transactionitemlib->get("`t_transaction_product_item`.`id` = " . $checkIDItem);
            $trxItems[] = $temp;
        }
        $trx["items"] = $trxItems;

        // Update Cart Status
        foreach($carts as $item) {
            $data = array(
                "updated_at" => date("Y-m-d H:i:s"),
                "is_visible" => 1,
            );
            $checkID = $this->cartslib->update("`t_cart_product_user`.`id` = " . $item["id"], $data);
            $this->checkID($checkID);
        }

        $this->request->res(200, $trx, "Berhasil melakukan transaksi", null);
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
