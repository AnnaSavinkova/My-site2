<?php
require_once __DIR__ . '/BaseController.php';
class AdminController extends BaseController {
    public function index(){
        ob_start(); include __DIR__ . '/../legacy/admin_dashboard.php'; echo ob_get_clean();
    }
}
