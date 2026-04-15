<?php
require_once __DIR__ . '/BaseController.php';
class ProfileController extends BaseController {
    public function index(){
        ob_start(); include __DIR__ . '/../legacy/profile.php'; echo ob_get_clean();
    }
}
