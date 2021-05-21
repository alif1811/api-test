<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Profile extends CI_Controller
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
        $this->load->library("master-data/userslib", array(
            "sql" => $this->customSQL
        ));
    }

    public function index() 
    {
        $tempUser = $this->checkIsValid();
        $data = $this->userslib->get("`m_users`.`token` = '" . $tempUser["token"] . "'");
        // unset($data["id"]);
        $this->request->res(200, $data, "Berhasil memuat profile", null);
    }

    public function update()
    {
        $req = $this->request->raw();

        $tempUser = $this->checkIsValid();

        $data = array(
            "updated_at" => date("Y-m-d H:i:s")
        );

        if (isset($req["email"])) $data["email"] = $req["email"];
        if (isset($req["full_name"])) $data["full_name"] = $req["full_name"];
        if (isset($req["phone_number"])) $data["phone_number"] = $req["phone_number"];
        if (isset($req["address"])) $data["address"] = $req["address"];
        if (isset($req["password"])) $data["password"] = password_hash($req["password"], PASSWORD_DEFAULT);
        if (isset($req["id_m_medias"])) $data["id_m_medias"] = $req["id_m_medias"];

        $checkID = $this->userslib->update(array(
            "token" => $tempUser["token"]
        ), $data);
        $this->checkID($checkID);

        $data = $this->userslib->get("`m_users`.`token` = '" . $tempUser["token"] . "'");
        unset($data["id"]);

        $this->request->res(200, $data, "Berhasil mengubah user", null);
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
