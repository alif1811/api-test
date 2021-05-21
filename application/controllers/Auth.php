<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Auth extends CI_Controller
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
        $this->load->library("MMediasLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("MUsersLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("MTrashLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("UUserLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("UUserAuthLib", array(
            "sql" => $this->customSQL
        ));
        $this->load->library("UUserTrashLib", array(
            "sql" => $this->customSQL
        ));
    }

    public function login()
    {
        $email = $this->input->post_get("email", TRUE) ?: "";
        $password = $this->input->post_get("password", TRUE) ?: "";

        if (empty($email) || empty($password))
            $this->request->res(400, null, "Parameter tidak benar", null);

        $res = $this->userslib->get("`email` = '" . $email . "'");

        if (empty($res))
            $this->request->res(403, null, "Akun tidak ditemukan", null);

        if (!password_verify($password, $res["password"]))
            $this->request->res(403, null, "Password anda salah", null);

        unset($res["password"]);

        $this->notificationslib->create(array(
            "type" => 0,
            "title" => "Berhasil melakukan otentikasi",
            "content" => "Berhasil melakukan otentikasi pada tanggal " . date("Y-m-d H:i:s"),
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
            "id_m_users" => $res["id"]
        ));

        // unset($res["id"]);

        $this->request->res(200, $res, "Berhasil melakukan otentikas", null);
    }

    public function checkUid()
    {
        $req = $this->request->raw();
        if (!isset($req["uid"]) || empty($req["uid"]))
            $this->request->res(400, null, "Wrong Parameter", null);

        // Check UID
        $UUserAuth = $this->uuserauthlib->get("`u_user_auth`.`uid` = '" . $req["uid"] . "'");
        if ($UUserAuth == null) $this->request->res(404, ["status" => 0], "UID Not Found", null);

        // Get U Users
        $UUser = $this->uuserlib->get("`u_user`.`id` = '" . $UUserAuth['id_u_user'] . "'");
        if ($UUser == null) $this->request->res(404, -1, "ID User Not Found", null);

        // Check Trash
        $UUserTrash = $this->uusertrashlib->get("`u_user_trash`.`id_m_users` = '" . $UUser['id_m_users'] . "'");

        // Get M Users
        $MUsers = $this->muserslib->get("`m_users`.`id` = '" . $UUser['id_m_users'] . "'");
        unset($UUser['id_m_users']);

        if ($UUserTrash == null) {
            $this->request->res(404, [
                "status" => 1,
                "m_users" => $MUsers,
                "u_user" => $UUser
            ], "Trash Not Found", null);
        }

        $MTrash = $this->mtrashlib->get("`m_trash`.`id` = '" . $UUserTrash['id_m_trash'] . "'");
        unset($UUser['id_m_users']);

        $this->request->res(200, [
            "status" => 2,
            "m_users" => $MUsers,
            "u_user" => $UUser,
            "u_user_trash" => $UUserTrash,
            "m_trash" => $MTrash
        ], "Registered", null);
    }

    public function registration()
    {
        $req = $this->request->raw();
        if (
            !isset($req["email"]) || empty($req["email"]) ||
            !isset($req["full_name"]) || empty($req["full_name"]) ||
            !isset($req["phone"]) || empty($req["phone"]) ||
            !isset($req["address"]) || empty($req["address"]) ||
            !isset($req["profile_image"]) || empty($req["profile_image"]) ||
            !isset($req["uid"]) || empty($req["uid"]) ||
            !isset($req["address"]) || empty($req["address"])
        )
            $this->request->res(400, null, "Parameter tidak benar", null);

        $existPhone = $this->muserslib->get("`m_users`.`phone` = '" . $req["phone"] . "'");
        $existEmail = $this->uuserlib->get("`u_user`.`email` = '" . $req["email"] . "'");

        if (!empty($existPhone) || !empty($existEmail))
            $this->request->res(500, null, "Akun sudah dibuat sebelumnya", null);

        $dataMMedias = array(
            "uri" => $req["profile_image"],
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $createMMedias = $this->mmediaslib->create($dataMMedias);
        $this->checkID($createMMedias);

        $dataMUsers = array(
            "full_name" => $req["full_name"],
            "phone" => $req['phone'],
            "address" => $req['address'],
            "total_points" => 0,
            "id_m_medias" => $createMMedias,
            "created_at" => date("Y-m-d H:i:s"),
            "updated_at" => date("Y-m-d H:i:s"),
        );

        $createMUsers = $this->muserslib->create($dataMUsers);
        if ($createMUsers == -1) $this->mmediaslib->delete("`m_medias`.`id` = " . $createMMedias);
        $this->checkID($createMUsers);

        $dataUUser = array(
            "id_m_users" => $createMUsers,
            "token" => md5(date_timestamp_get(date_create()) . $req["email"]),
            "email" => $req["email"],
            "type" => 'customer',
        );

        $createUUser = $this->uuserlib->create($dataUUser);
        if ($createUUser == -1) {
            $this->mmediaslib->delete("`m_medias`.`id` = " . $createMMedias);
            $this->muserslib->delete("`m_users`.`id` = " . $createMUsers);
        }
        $this->checkID($createUUser);

        $dataUUserAuth = array(
            "id_u_user" => $createUUser,
            "uid" => $req["uid"],
            "timestamp" => date("Y-m-d H:i:s"),
        );

        $createUUserAuth = $this->uuserauthlib->create($dataUUserAuth);
        if ($createUUserAuth == -1) {
            $this->mmediaslib->delete("`m_medias`.`id` = " . $createMMedias);
            $this->muserslib->delete("`m_users`.`id` = " . $createMUsers);
            $this->muserslib->delete("`u_user`.`id` = " . $createUUser);
        }
        $this->checkID($createUUserAuth);

        $this->request->res(200, $createMUsers, "Berhasil melakukan registrasi", null);
    }

    private function checkID($checkID)
    {
        if ($checkID == -1)
            $this->request->res(500, null, "Terjadi kesalahan, silahkan cek masukan anda", null);
    }
}
