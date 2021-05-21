<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Trash extends CI_Controller
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
        $this->load->library("MUsersLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("UUserTrashLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("UUserBillLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("MTrashLib", array(
            "sql" => $this->customSQL
        ));
    }

    public function getDetail()
    {
        $UUser = $this->checkIsValid();

        // Get Info Trash
        $UUserTrash = $this->uusertrashlib->get("`u_user_trash`.`id_m_users` = '" . $UUser['id_m_users'] . "'");
        $MTrash = $this->mtrashlib->get("`m_trash`.`id` = '" . $UUserTrash['id_m_trash'] . "'");


        date_default_timezone_set('Asia/Jakarta');
        $date = getdate()['mday'];
        $month = getdate()['mon'];
        $year = getdate()['year'];

        $UUserBill = $this->uuserbilllib->get(
            "`u_user_bill`.`id_m_users` = '" . $UUser['id_m_users'] . "'" .
                " && " .
                "`u_user_bill`.`month` = '" . $month . "'" .
                " && " .
                "`u_user_bill`.`year` = '" . $year . "'"
        );

        if ($UUserBill == null) {
            $monthBefore = $month - 1 != 0 ? $month - 1 : 12;
            $yearBefore = $monthBefore == 12 ? $year - 1 : $year;

            $prevMonth = $this->uuserbilllib->get(
                "`u_user_bill`.`id_m_users` = '" . $UUser['id_m_users'] . "'" .
                    " && " .
                    "`u_user_bill`.`month` = '" . $monthBefore . "'" .
                    " && " .
                    "`u_user_bill`.`year` = '" . $yearBefore . "'"
            );
            if ($prevMonth == null || $prevMonth['status'] == 'paid off') {
                $newBill = array(
                    "id_m_users" => $UUser['id_m_users'],
                    "bill" => $MTrash['price'],
                    "status" => $date > 20 ? 'currently' : 'not currently',
                    "month" => "$month",
                    "year" => "$year",
                    "pay_at" => date("Y-m-d H:i:s")
                );

                $createNewBill = $this->uuserbilllib->create($newBill);
                $this->checkID($createNewBill);
                $newBill['id'] = "$createNewBill";
                $UUserBill = $newBill;
            } else {
                $UUserBill = $prevMonth;
            }
        }

        $this->request->res(200, ["u_user_bill" => $UUserBill, "u_user_trash" => $UUserTrash], "Berhasil mengambil data", null);
    }

    public function createTrash()
    {
        $req = $this->request->raw();
        if (
            !isset($req["idUser"]) || empty($req["idUser"]) ||
            !isset($req["weight"]) || empty($req["weight"]) ||
            !isset($req["height"]) || empty($req["height"]) ||
            !isset($req["price"]) || empty($req["price"]) ||
            !isset($req["lat"]) || empty($req["lat"]) ||
            !isset($req["lon"]) || empty($req["lon"])
        )
            $this->request->res(400, null, "Parameter tidak benar", null);

        $dataMTrash = array(
            "weight" => $req["weight"],
            "height" => $req['height'],
            "token_activation" => md5(date("Y-m-d H:i:s")),
            "is_activated" => 'Y',
            "price" => $req['price'],
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $createMTrash = $this->mtrashlib->create($dataMTrash);
        $this->checkID($createMTrash);

        $dataUUserTrash = array(
            "id_m_users" => $req['idUser'],
            "id_m_trash" => md5(date_timestamp_get(date_create()) . $req["email"]),
            "current_weight" => 0,
            "current_height" => 0,
            "status" => "active",
            "type" => "subscribe",
            "lat" => $req['lat'],
            "lon" => $req['lon'],
            "active_at" => date("Y-m-d H:i:s")
        );

        $createUUserTrash = $this->uuserlib->create($dataUUserTrash);
        $this->checkID($createUUserTrash);

        $this->request->res(200, $createUUserTrash, "Berhasil registrasi tempat sampah", null);
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
