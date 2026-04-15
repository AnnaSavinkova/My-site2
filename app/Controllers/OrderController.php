<?php
require_once __DIR__ . '/BaseController.php';
class OrderController extends BaseController {
    public function index(){
        ob_start(); include __DIR__ . '/../legacy/checkout.php'; echo ob_get_clean();
    }
}
