<?php
defined('BASEPATH') or exit('No direct script access allowed');

class RequestMenus extends CI_Controller
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
        $this->load->library("requestmenuslib", array(
            "sql" => $this->customSQL
        ));

        $this->load->library("services/cartsrequestmenulib", array(
            "sql" => $this->customSQL
        ));

        $this->load->library("voucherslib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("voucherredeemlib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("transactionsrequestmenulib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("transactionsrequestmenuitemlib", array(
            "sql" => $this->customSQL
        ));
    }

    public function index() 
    {
        $page = $this->input->get("page", TRUE) ?: 0;
        $search = $this->input->get("search", TRUE) ?: "";
        $orderDirection = $this->input->get("order-direction", TRUE) ?: "DESC";

        $tempUser = $this->checkIsValid();

        $res = $this->cartsrequestmenulib->filter("`t_cart_request_menu_user`.`is_visible` = 0 AND `t_cart_request_menu_user`.`id_m_users` = " . $tempUser["id"], $search, $page, $orderDirection);
        $size = $this->cartsrequestmenulib->size("`t_cart_request_menu_user`.`is_visible` = 0 AND `t_cart_request_menu_user`.`id_m_users` = " . $tempUser["id"], $search, $orderDirection);
        $this->request->res(200, $res, "Berhasil memuat cart request menu", array(
            "page" => $page,
            "search" => $search,
            "order-direction" => $orderDirection,
            "size" => array(
                "fetch" => count($res),
                "total" => $size
            )
        ));
    }

    public function addToCart()
    {
        $req = $this->request->raw();
        if (!isset($req["product_name"]) || empty($req["product_name"]) ||
            !isset($req["product_description"]) || empty($req["product_description"]) ||
            !isset($req["product_price"]) || empty($req["product_price"]) ||
            !isset($req["product_qty"]) || empty($req["product_qty"])) 
            $this->request->res(400, null, "Parameter tidak benar", null);

        $tempUser = $this->checkIsValid();

        $data = array(
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
            "product_name" => $req["product_name"],
            "product_description" => $req["product_description"],
            "product_price" => $req["product_price"],
            "product_qty" => $req["product_qty"],
            "is_visible" => 1,
            "id_m_users" => $tempUser["id"]
        );

        $dataCart = array(
            "qty" => $req["product_qty"],
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
            "is_visible" => 0,
            "id_m_users" => $tempUser["id"]
        );

        $checkID = $this->requestmenuslib->create($data);

        $this->checkID($checkID);

        $dataCart["id_t_request_menu"] = $checkID;

        $checkID = $this->cartsrequestmenulib->create($dataCart);

        $this->checkID($checkID);

        $res = $this->cartsrequestmenulib->get("`t_cart_request_menu_user`.`id` = " . $checkID);
        unset($res["id_t_request_menu"]);

        $this->request->res(200, $res, "Berhasil menambahkan ke cart", null);
    }

    public function checkout()
    {
        $tempUser = $this->checkIsValid();
        $carts = $this->cartsrequestmenulib->all("`t_cart_request_menu_user`.`id_m_users` = " . $tempUser["id"] . " AND `t_cart_request_menu_user`.`is_visible` = 0");

        if (count($carts) == 0)
            $this->request->res(400, null, "Anda belum memiliki barang di keranjang", null);

        $total = 0;
        $trxTemp = array();
        foreach($carts as $item) {
            // Calculate Subtotal
            $qty = (int)$item["qty"];
            $product_price = (int)$item["product"]["product_price"];
            $subtotal = ($qty * $product_price);

            // Insert To Total
            $total += $subtotal;

            // Create Trx Temp
            $trxTemp[] = array(
                "qty" => $qty,
                "sub_total" => $subtotal,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s"), 
                "id_m_users" => $tempUser["id"],
                "id_t_request_menu" => $item["product"]["id"]
            );
        }

        // Create Trx
        $checkID = $this->transactionsrequestmenulib->create(array(
            "transaction_code" => md5(date_timestamp_get(date_create()) . "-" . $tempUser["id"]), 
            "total" => $total,
            "status" => 0,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"), 
            "id_m_users" => $tempUser["id"]
        ));
        $this->checkID($checkID);
        $trx = $this->transactionsrequestmenulib->get("`t_transaction_request_menu`.`id` = " . $checkID);

        // Create Trx Item
        $trxItems = array();
        foreach ($trxTemp as $item) {
            $item["id_t_transaction_request_menu"] = $checkID;
            $checkIDItem = $this->transactionsrequestmenuitemlib->create($item);
            $this->checkID($checkIDItem);
            $temp = $this->transactionsrequestmenuitemlib->get("`t_transaction_request_menu_item`.`id` = " . $checkIDItem);
            $trxItems[] = $temp;
        }
        $trx["items"] = $trxItems;

        // Update Cart Status
        foreach($carts as $item) {
            $data = array(
                "updated_at" => date("Y-m-d H:i:s"),
                "is_visible" => 1,
            );
            $checkID = $this->cartsrequestmenulib->update("`t_cart_request_menu_user`.`id` = " . $item["id"], $data);
            $this->checkID($checkID);
        }

        $this->request->res(200, $trx, "Berhasil melakukan transaksi", null);
    }

    public function update($id)
    {
        $req = $this->request->raw();

        $tempUser = $this->checkIsValid();

        $res = $this->cartsrequestmenulib->get("`t_cart_request_menu_user`.`id` = " . $id);

        if (empty($res))
        $this->request->res(404, null, "Data tidak ditemukan", null);

        $data = array(
            "updated_at" => date("Y-m-d H:i:s")
        );

        $dataCart = array(
            "updated_at" => date("Y-m-d H:i:s")
        );

        if (isset($req["product_name"])) $data["product_name"] = $req["product_name"];
        if (isset($req["product_description"])) $data["product_description"] = $req["product_description"];
        if (isset($req["product_price"])) $data["product_price"] = $req["product_price"];
        if (isset($req["product_qty"])) {
            $data["product_qty"] = $req["product_qty"];
            $dataCart["qty"] = $req["product_qty"];
        }

        $checkID = $this->requestmenuslib->update("`t_request_menu`.`id` = " . $res["product"]["id"], $data);

        $this->checkID($checkID);

        $checkID = $this->cartsrequestmenulib->update("`t_cart_request_menu_user`.`id` = " . $res["id"], $dataCart);

        $this->checkID($checkID);

        $res = $this->cartsrequestmenulib->get("`t_cart_request_menu_user`.`id` = " . $checkID);

        $this->request->res(200, $res, "Berhasil mengubah cart", null);
    }

    public function delete($id)
    {
        $tempUser = $this->checkIsValid();

        $data = $this->cartsrequestmenulib->get("`t_cart_request_menu_user`.`id` = " . $id);

        $checkID = $this->cartsrequestmenulib->delete(array("id" => $id));
        $this->checkID($checkID);

        $checkID = $this->requestmenuslib->delete(array("id" => $data["product"]["id"]));
        $this->checkID($checkID);

        $this->request->res(200, $data, "Berhasil menghapus produk dari cart", null);
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
