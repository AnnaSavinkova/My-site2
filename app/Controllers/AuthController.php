<?php
require_once __DIR__ . '/BaseController.php';
class AuthController extends BaseController {
    public function index(){
        ob_start(); include __DIR__ . '/../legacy/login.php'; echo ob_get_clean();
    }
}
