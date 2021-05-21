<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Wishlists extends CI_Controller
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
        $this->load->library("services/wishlistslib", array(
            "sql" => $this->customSQL
        ));
    }

    public function index() 
    {
        $page = $this->input->get("page", TRUE) ?: 0;
        $search = $this->input->get("search", TRUE) ?: "";
        $orderDirection = $this->input->get("order-direction", TRUE) ?: "DESC";

        $tempUser = $this->checkIsValid();

        $res = $this->wishlistslib->filter("`t_wishlist_product`.`id_m_users` = " . $tempUser["id"], $search, $page, $orderDirection);
        $size = $this->wishlistslib->size("`t_wishlist_product`.`id_m_users` = " . $tempUser["id"], $search, $orderDirection);
        $this->request->res(200, $res, "Berhasil memuat data wishlist", array(
            "page" => $page,
            "search" => $search,
            "order-direction" => $orderDirection,
            "size" => array(
                "fetch" => count($res),
                "total" => $size
            )
        ));
    }

    public function delete($id_t_products)
    {
        $tempUser = $this->checkIsValid();

        $data = $this->wishlistslib->get("`t_wishlist_product`.`id_t_products` = " . $id_t_products. " AND `t_wishlist_product`.`id_m_users` = " . $tempUser["id"]);

        $checkID = $this->wishlistslib->delete(array("id_t_products" => $id_t_products, "id_m_users" => $tempUser["id"]));
        $this->checkID($checkID);

        $this->request->res(200, $data, "Berhasil menghapus wish list", null);
    }

    public function get($id_t_products)
    {
        $tempUser = $this->checkIsValid();

        $data = $this->wishlistslib->get("`t_wishlist_product`.`id_t_products` = " . $id_t_products . " AND `t_wishlist_product`.`id_m_users` = " . $tempUser["id"]);

        $this->request->res(200, $data, "Berhasil menghapus wish list", null);
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
