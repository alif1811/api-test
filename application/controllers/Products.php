<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Products extends CI_Controller
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
        $this->load->library("productslib", array(
            "sql" => $this->customSQL
        ));

        $this->load->library("feedbackslib", array(
            "sql" => $this->customSQL
        ));

        $this->load->library("services/wishlistslib", array(
            "sql" => $this->customSQL
        ));

        $this->load->library("services/cartslib", array(
            "sql" => $this->customSQL
        ));
    }

    public function index() 
    {
        $categoryID = $this->input->get("category_id", TRUE) ?: "";
        $page = $this->input->get("page", TRUE) ?: 0;
        $search = $this->input->get("search", TRUE) ?: "";
        $orderDirection = $this->input->get("order-direction", TRUE) ?: "DESC";
        $is_price_order = $this->input->get("is_price_order", TRUE) ?: FALSE;
        $is_discount_order = $this->input->get("is_discount_order", TRUE) ?: FALSE;

        $tempUser = $this->checkIsValid();

        if (!empty($categoryID)) {
            $res = $this->productslib->filter_by_category($is_price_order, $is_discount_order, $categoryID, $search, $page, $orderDirection);
            $size = $this->productslib->size($search, $orderDirection);
        } else {
            $res = $this->productslib->filter($is_price_order, $is_discount_order, $search, $page, $orderDirection);
            $size = $this->productslib->size($search, $orderDirection);
        }

        $this->request->res(200, $res, "Berhasil memuat data produk", array(
            "page" => $page,
            "search" => $search,
            "order-direction" => $orderDirection,
            "size" => array(
                "fetch" => count($res),
                "total" => $size
            )
        ));
    }

    public function recomended() 
    {
        $categoryID = $this->input->get("category_id", TRUE) ?: "";
        $page = $this->input->get("page", TRUE) ?: 0;
        $search = $this->input->get("search", TRUE) ?: "";
        $orderDirection = $this->input->get("order-direction", TRUE) ?: "DESC";
        $is_price_order = $this->input->get("is_price_order", TRUE) ?: "";
        $is_discount_order = $this->input->get("is_discount_order", TRUE) ?: FALSE;

        $tempUser = $this->checkIsValid();

        if (!empty($categoryID)) {
            $res = $this->productslib->recomended_by_category($is_price_order, $is_discount_order, $categoryID, $search, $page, $orderDirection);
            $size = $this->productslib->size($search, $orderDirection);
        } else {
            $res = $this->productslib->recomended($is_price_order, $is_discount_order, $search, $page, $orderDirection);
            $size = $this->productslib->size($search, $orderDirection);
        }
        $this->request->res(200, $res, "Berhasil memuat data produk", array(
            "page" => $page,
            "search" => $search,
            "order-direction" => $orderDirection,
            "size" => array(
                "fetch" => count($res),
                "total" => $size
            )
        ));
    }

    public function detail($id) 
    {
        $tempUser = $this->checkIsValid();

        $res = $this->productslib->get("`t_products`.`id` = " . $id);
        $this->request->res(200, $res, "Berhasil memuat detail produk", null);
    }

    public function feedbacks($id)
    {
        $page = $this->input->get("page", TRUE) ?: 0;
        $search = $this->input->get("search", TRUE) ?: "";
        $orderDirection = $this->input->get("order-direction", TRUE) ?: "DESC";

        $tempUser = $this->checkIsValid();

        $res = $this->feedbackslib->filter($id, $search, $page, $orderDirection);
        $size = $this->feedbackslib->size($id, $search, $orderDirection);
        $this->request->res(200, $res, "Berhasil memuat data feedback product", array(
            "page" => $page,
            "search" => $search,
            "order-direction" => $orderDirection,
            "size" => array(
                "fetch" => count($res),
                "total" => $size
            )
        ));
    }

    public function addToWishlist($id)
    {
        $tempUser = $this->checkIsValid();

        $res = $this->wishlistslib->get("`t_wishlist_product`.`id_t_products` = " . $id . " AND 
        `t_wishlist_product`.`id_m_users` = " . $tempUser["id"]);

        if (!empty($res))
            $this->request->res(200, $res, "Sudah ada sebelumnya", null);

        $data = array(
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
            "id_t_products" => $id,
            "id_m_users" => $tempUser["id"]
        );

        $checkID = $this->wishlistslib->create($data);
        $this->checkID($checkID);

        $res = $this->wishlistslib->get("`t_wishlist_product`.`id_t_products` = " . $id . " AND 
        `t_wishlist_product`.`id_m_users` = " . $tempUser["id"]);

        $this->request->res(200, $res, "Berhasil menambahkan ke wishlist", null);
    }

    public function addToCart($id)
    {
        $qty = $this->input->post_get("qty", TRUE) ?: 1;

        $tempUser = $this->checkIsValid();

        $res = $this->cartslib->get("`t_cart_product_user`.`id_t_products` = " . $id . " AND 
        `t_cart_product_user`.`id_m_users` = " . $tempUser["id"] . " AND `t_cart_product_user`.`is_visible` = 0");

        $data = array(
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
            "id_t_products" => $id,
            "id_m_users" => $tempUser["id"]
        );

        if (!empty($res)) {
            $data["qty"] = ((int) $res["qty"] + $qty);            
            $checkID = $this->cartslib->update("`t_cart_product_user`.`id` = " . $res["id"], $data);
        } else {
            $data["qty"] = $qty;
            $checkID = $this->cartslib->create($data);
        }

        $this->checkID($checkID);

        $res = $this->cartslib->get("`t_cart_product_user`.`id_t_products` = " . $id . " AND 
        `t_cart_product_user`.`id_m_users` = " . $tempUser["id"] . " AND is_visible = 0");

        $this->request->res(200, $res, "Berhasil menambahkan ke cart", null);
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
