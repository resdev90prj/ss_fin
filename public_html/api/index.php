<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api/bootstrap.php';
require_once api_project_root() . '/controllers/api/ApiAuthController.php';
require_once api_project_root() . '/controllers/api/ApiDashboardController.php';
require_once api_project_root() . '/controllers/api/ApiAccountController.php';
require_once api_project_root() . '/controllers/api/ApiCategoryController.php';
require_once api_project_root() . '/controllers/api/ApiTransactionController.php';
require_once api_project_root() . '/controllers/api/ApiTargetController.php';

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = api_request_path();

switch ($method . ' ' . $path) {
    case 'GET /me':
        (new ApiAuthController())->me();
        break;

    case 'POST /login':
        (new ApiAuthController())->login();
        break;

    case 'POST /logout':
        (new ApiAuthController())->logout();
        break;

    case 'GET /dashboard/summary':
        (new ApiDashboardController())->summary();
        break;

    case 'GET /accounts':
        (new ApiAccountController())->index();
        break;

    case 'GET /categories':
        (new ApiCategoryController())->index();
        break;

    case 'GET /transactions':
        (new ApiTransactionController())->index();
        break;

    case 'GET /targets/summary':
        (new ApiTargetController())->summary();
        break;

    default:
        api_not_found();
}
