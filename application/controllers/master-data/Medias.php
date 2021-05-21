<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Medias extends CI_Controller
{
    // Public Variable
    public $session, $custom_curl;
    public $csrf_token, $auth;
    public $topBarContent, $navBarContent;
    public $fileUpload;

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
        $this->fileUpload = new Upload_file_helper(
            array(
                "file_type" => array(
                    "png",
                    "jpg",
                    "jpeg",
                    "webp"
                ),
                "max_size"  => 200000000
            )
        );

        // Init Request
        $this->request->init($this->custom_curl);

        // Load Library
        $this->load->library("master-data/mediaslib", array(
            "sql" => $this->customSQL
        ));
    }

    public function index() 
    {
        $page = $this->input->get("page", TRUE) ?: 0;
        $search = $this->input->get("search", TRUE) ?: "";
        $orderDirection = $this->input->get("order-direction", TRUE) ?: "DESC";

        $tempUser = $this->checkIsValid();

        $res = $this->mediaslib->filter($search, $page, $orderDirection);
        $size = $this->mediaslib->size($search, $orderDirection);
        $this->request->res(200, $res, "Berhasil memuat data media", array(
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
        if (!isset($_FILES["photo"]["name"])) 
            return $this->request
            ->res(400, null, "Parameter tidak benar", null);
        
        $tempUser = $this->checkIsValid();

        $photo = $this->fileUpload->do_upload("photo");

        if (!$photo["status"])
            return $this->request
            ->res(500, null, "Gagal mengunggah gambar", null);

        // Upload File
        $data = array(
            "url" => base_url("assets/dist/img/") . $photo["file_name"],
            "file_name" => $photo["file_name"],
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $checkID = $this->mediaslib->create($data);
        $this->checkID($checkID);
        
        $data = $this->mediaslib->get("`m_medias`.`id` = " . $checkID);

        $this->request->res(200, $data, "Berhasil membuat media", null);
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
