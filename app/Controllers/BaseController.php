<?php
// app/Controllers/BaseController.php
class BaseController {
    protected function render($view, $data = []){
        extract($data);
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        if (file_exists($viewFile)){
            include $viewFile;
        } else {
            echo "View not found: $viewFile";
        }
    }
    protected function redirect($url){ header('Location: ' . $url); exit; }
    protected function json($data, $status=200){ header('Content-Type: application/json', true, $status); echo json_encode($data); exit; }
}
