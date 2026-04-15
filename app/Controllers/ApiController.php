<?php
require_once __DIR__ . '/BaseController.php';
class ApiController extends BaseController {
    public function index(){
        ob_start(); include __DIR__ . '/../legacy/get_dishes.php'; echo ob_get_clean();
    }
}
