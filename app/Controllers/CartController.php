<?php
require_once __DIR__ . '/BaseController.php';
class CartController extends BaseController {
    public function index(){
        ob_start(); include __DIR__ . '/../legacy/cart.php'; echo ob_get_clean();
    }
}
