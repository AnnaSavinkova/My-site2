<?php
require_once __DIR__ . '/BaseController.php';
class HomeController extends BaseController {
    public function index(){
        ob_start(); include __DIR__ . '/../legacy/index.php'; echo ob_get_clean();
    }
}
