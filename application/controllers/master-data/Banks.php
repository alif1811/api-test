<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Banks extends CI_Controller
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
        $this->load->library("master-data/categorieslib", array(
            "sql" => $this->customSQL
        ));
    }

    public function index() 
    {
        $page = $this->input->get("page", TRUE) ?: 0;
        $search = $this->input->get("search", TRUE) ?: "";
        $orderDirection = $this->input->get("order-direction", TRUE) ?: "DESC";

        $tempUser = $this->checkIsValid();

        $res = $this->categorieslib->filter($search, $page, $orderDirection);
        $size = $this->categorieslib->size($search, $orderDirection);
        $this->request->res(200, $res, "Berhasil memuat data category", array(
            "page" => $page,
            "search" => $search,
            "order-direction" => $orderDirection,
            "size" => array(
                "fetch" => count($res),
                "total" => $size
            )
        ));
    }

    public function create()
    {
        $req = $this->request->raw();
        if (!isset($req["category"]) || empty($req["category"]) ||
            !isset($req["slug"]) || empty($req["slug"]) ||
            !isset($req["id_m_icons"]) || empty($req["id_m_icons"])) 
            $this->request->res(400, null, "Parameter tidak benar", null);

        $tempUser = $this->checkIsValid();

        $data = array(
            "category" => $req["category"],
            "slug" => $req["slug"],
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
            "id_m_icons" => $req["id_m_icons"]
        );

        $checkID = $this->categorieslib->create($data);
        $this->checkID($checkID);
        
        $data = $this->categorieslib->get("`m_categories`.`id` = " . $checkID);

        $this->request->res(200, $data, "Berhasil membuat category", null);
    }

    public function update($id)
    {
        $req = $this->request->raw();

        $tempUser = $this->checkIsValid();

        $data = array();

        if (isset($req["category"])) $data["category"] = $req["category"];
        if (isset($req["slug"])) $data["slug"] = $req["slug"];
        if (isset($req["id_m_icons"])) $data["id_m_icons"] = $req["id_m_icons"];

        $checkID = $this->categorieslib->update(array(
            "id" => $id
        ), $data);
        $this->checkID($checkID);

        $data = $this->categorieslib->get("`m_categories`.`id` = " . $id);

        $this->request->res(200, $data, "Berhasil mengubah category", null);
    }

    public function delete($id)
    {
        $tempUser = $this->checkIsValid();

        $data = $this->categorieslib->get("`m_categories`.`id` = " . $id);

        $checkID = $this->categorieslib->delete(array("id" => $id));
        $this->checkID($checkID);

        $this->request->res(200, $data, "Berhasil menghapus category", null);
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
