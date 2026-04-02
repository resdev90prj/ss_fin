<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/api/bootstrap.php';
require_once api_project_root() . '/controllers/api/ApiAuthController.php';
require_once api_project_root() . '/controllers/api/ApiDashboardController.php';
require_once api_project_root() . '/controllers/api/ApiAccountController.php';
require_once api_project_root() . '/controllers/api/ApiBoxController.php';
require_once api_project_root() . '/controllers/api/ApiCategoryController.php';
require_once api_project_root() . '/controllers/api/ApiTransactionController.php';
require_once api_project_root() . '/controllers/api/ApiBudgetController.php';
require_once api_project_root() . '/controllers/api/ApiGoalController.php';
require_once api_project_root() . '/controllers/api/ApiReportController.php';
require_once api_project_root() . '/controllers/api/ApiUserController.php';
require_once api_project_root() . '/controllers/api/ApiImportController.php';
require_once api_project_root() . '/controllers/api/ApiDebtController.php';
require_once api_project_root() . '/controllers/api/ApiTargetController.php';
require_once api_project_root() . '/controllers/api/ApiOnboardingController.php';

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

    case 'POST /accounts':
        (new ApiAccountController())->store();
        break;

    case 'POST /accounts/update':
        (new ApiAccountController())->update();
        break;

    case 'POST /accounts/toggle':
        (new ApiAccountController())->toggle();
        break;

    case 'GET /boxes':
        (new ApiBoxController())->index();
        break;

    case 'POST /boxes':
        (new ApiBoxController())->store();
        break;

    case 'POST /boxes/update':
        (new ApiBoxController())->update();
        break;

    case 'GET /categories':
        (new ApiCategoryController())->index();
        break;

    case 'POST /categories':
        (new ApiCategoryController())->store();
        break;

    case 'POST /categories/update':
        (new ApiCategoryController())->update();
        break;

    case 'POST /categories/delete':
        (new ApiCategoryController())->delete();
        break;

    case 'GET /transactions':
        (new ApiTransactionController())->index();
        break;

    case 'GET /transactions/suggest-category':
        (new ApiTransactionController())->suggestCategory();
        break;

    case 'POST /transactions/auto-classify-others':
        (new ApiTransactionController())->autoClassifyOthers();
        break;

    case 'POST /transactions':
        (new ApiTransactionController())->store();
        break;

    case 'POST /transactions/update':
        (new ApiTransactionController())->update();
        break;

    case 'POST /transactions/delete':
        (new ApiTransactionController())->delete();
        break;

    case 'GET /budgets':
        (new ApiBudgetController())->index();
        break;

    case 'POST /budgets':
        (new ApiBudgetController())->store();
        break;

    case 'POST /budgets/delete':
        (new ApiBudgetController())->delete();
        break;

    case 'GET /goals':
        (new ApiGoalController())->index();
        break;

    case 'POST /goals':
        (new ApiGoalController())->store();
        break;

    case 'POST /goals/update':
        (new ApiGoalController())->update();
        break;

    case 'POST /goals/delete':
        (new ApiGoalController())->delete();
        break;

    case 'GET /reports':
        (new ApiReportController())->index();
        break;

    case 'GET /profile':
        (new ApiUserController())->profile();
        break;

    case 'POST /profile/update':
        (new ApiUserController())->profileUpdate();
        break;

    case 'POST /profile/password':
        (new ApiUserController())->profilePassword();
        break;

    case 'POST /profile/alerts':
        (new ApiUserController())->profileAlerts();
        break;

    case 'GET /users':
        (new ApiUserController())->index();
        break;

    case 'POST /users':
        (new ApiUserController())->store();
        break;

    case 'POST /users/update':
        (new ApiUserController())->update();
        break;

    case 'POST /users/toggle-status':
        (new ApiUserController())->toggleStatus();
        break;

    case 'POST /users/reset-password':
        (new ApiUserController())->resetPassword();
        break;

    case 'POST /users/scope':
        (new ApiUserController())->scope();
        break;

    case 'POST /users/clear-scope':
        (new ApiUserController())->clearScope();
        break;

    case 'GET /imports':
        (new ApiImportController())->index();
        break;

    case 'POST /imports/upload':
        (new ApiImportController())->upload();
        break;

    case 'POST /imports/process-ofx-queue':
        (new ApiImportController())->processOfxQueue();
        break;

    case 'GET /debts':
        (new ApiDebtController())->index();
        break;

    case 'GET /debts/details':
        (new ApiDebtController())->details();
        break;

    case 'POST /debts':
        (new ApiDebtController())->store();
        break;

    case 'POST /debts/pay-installment':
        (new ApiDebtController())->payInstallment();
        break;

    case 'POST /debts/refund-installment':
        (new ApiDebtController())->refundInstallment();
        break;

    case 'POST /debts/delete':
        (new ApiDebtController())->delete();
        break;

    case 'POST /debts/delete-installment':
        (new ApiDebtController())->deleteInstallment();
        break;

    case 'GET /targets/summary':
        (new ApiTargetController())->summary();
        break;

    case 'GET /onboarding/summary':
        (new ApiOnboardingController())->summary();
        break;

    default:
        api_not_found();
}
