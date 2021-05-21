<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Settings extends CI_Controller
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
        $this->load->library("utilities/settingslib", array(
            "sql" => $this->customSQL
        ));
    }

    public function index() 
    {
        $tempUser = $this->checkIsValid();

        $res = $this->settingslib->get();
        $this->request->res(200, $res, "Berhasil memuat data setting", null);
    }

    public function update()
    {
        $req = $this->request->raw();

        $tempUser = $this->checkIsValid();

        $data = array(
            "updated_at" => date("Y-m-d H:i:s")
        );

        if (isset($req["application_name"])) $data["application_name"] = $req["application_name"];
        if (isset($req["version_code"])) $data["version_code"] = $req["version_code"];
        if (isset($req["id_m_medias"])) $data["id_m_medias"] = $req["id_m_medias"];

        $checkID = $this->settingslib->update($data);
        $this->checkID($checkID);

        $data = $this->settingslib->get();

        $this->request->res(200, $data, "Berhasil mengubah setting", null);
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
