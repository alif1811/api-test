<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Proof extends CI_Controller
{
    // Public Variable
    public $session, $custom_curl;
    public $csrf_token, $auth;

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
                "max_size"  => 20000000
            )
        );

        // Init Request
        $this->request->init($this->custom_curl);

        // Load Library
        $this->load->library("MUsersLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("MMediasLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("UUserTrashLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("UUserBillLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("UUserBillProofLib", array(
            "sql" => $this->customSQL
        ));
    }

    public function send()
    {
        $UUser = $this->checkIsValid();

        if (!isset($_FILES["photo"]["name"]))
            return $this->request
                ->res(400, null, "Parameter tidak benar", null);

        $fromBank = $this->input->post("from_bank", TRUE) ?: "";
        $accoundNumber = $this->input->post("account_number", TRUE) ?: "";
        $transferAmount = $this->input->post("transfer_amount", TRUE) ?: "";
        $idMBanks = $this->input->post("id_banks", TRUE) ?: "";
        $idUUserBill = $this->input->post("id_user_bill", TRUE) ?: "";

        if ($fromBank == "" || $accoundNumber == "" || $transferAmount == "" || $idUUserBill == "") {
            return $this->request->res(400, null, "Parameter tidak benar", null);
        }

        // Upload Photo
        $photo = $this->fileUpload->do_upload("photo");

        if (!$photo["status"])
            return $this->request
                ->res(500, null, "Gagal mengunggah gambar", null);

        $dataMedias = array(
            "uri" => base_url("assets/dist/img/") . $photo["file_name"],
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s")
        );

        $checkIDMedias = $this->mmediaslib->create($dataMedias);
        $this->checkID($checkIDMedias);

        $data = [
            "from_bank" => $fromBank,
            "account_number" => $accoundNumber,
            "transfer_amount" => $transferAmount,
            "id_u_user_bill" => $idUUserBill,
            "id_m_medias" => $checkIDMedias,
            "id_m_banks" => $idMBanks
        ];

        $createProof = $this->uuserbillprooflib->create($data);
        $this->checkID($createProof);

        $this->uuserbilllib->update("`u_user_bill`.`id` = " . $idUUserBill, ["status" => "process"]);

        $this->request->res(201, null, "Berhasil mengunggah bukti pembayaran", null);
    }

    private function checkID($checkID)
    {
        if ($checkID == -1)
            $this->request->res(500, null, "Terjadi kesalahan, silahkan cek masukan anda", null);
    }

    private function checkIsValid()
    {
        $tempUser = $this->customSQL->checkValid();
        if (count($tempUser) != 1)
            $this->request->res(403, null, "Tidak ter-otentikasi", null);
        $tempUser = $tempUser[0];
        return $tempUser;
    }
}
