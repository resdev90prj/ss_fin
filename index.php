<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

function bootstrap_runtime_debug(): void
{
    $config = require __DIR__ . '/includes/config.php';
    $debug = is_array($config['debug'] ?? null) ? $config['debug'] : [];
    $enabled = !empty($debug['enabled']);
    if (!$enabled) {
        return;
    }

    error_reporting(E_ALL);
    ini_set('log_errors', '1');

    $logFile = trim((string)($debug['log_file'] ?? ''));
    if ($logFile !== '') {
        ini_set('error_log', $logFile);
    }

    $displayErrors = !empty($debug['display_errors']);
    ini_set('display_errors', $displayErrors ? '1' : '0');

    register_shutdown_function(static function (): void {
        $lastError = error_get_last();
        if (!$lastError) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($lastError['type'], $fatalTypes, true)) {
            return;
        }

        $route = (string)($_GET['route'] ?? 'dashboard');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $message = sprintf(
            '[runtime-fatal] route=%s uri=%s file=%s line=%d message=%s',
            $route,
            $uri,
            (string)($lastError['file'] ?? ''),
            (int)($lastError['line'] ?? 0),
            (string)($lastError['message'] ?? '')
        );
        error_log($message);
    });
}

bootstrap_runtime_debug();

function view(string $path, array $data = []): void
{
    extract($data);
    $viewPath = __DIR__ . '/views/' . $path . '.php';
    if (!file_exists($viewPath)) {
        http_response_code(404);
        echo 'View não encontrada: ' . e($path);
        exit;
    }

    require __DIR__ . '/views/layouts/header.php';
    require __DIR__ . '/views/layouts/sidebar.php';
    require $viewPath;
    require __DIR__ . '/views/layouts/footer.php';
}

$route = $_GET['route'] ?? 'dashboard';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$publicRoutes = ['login', 'login_submit'];
if (!in_array($route, $publicRoutes, true)) {
    require_login();
}

require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/DashboardController.php';
require_once __DIR__ . '/controllers/AccountController.php';
require_once __DIR__ . '/controllers/BoxController.php';
require_once __DIR__ . '/controllers/CategoryController.php';
require_once __DIR__ . '/controllers/TransactionController.php';
require_once __DIR__ . '/controllers/PartnerWithdrawalController.php';
require_once __DIR__ . '/controllers/DebtController.php';
require_once __DIR__ . '/controllers/BudgetController.php';
require_once __DIR__ . '/controllers/GoalController.php';
require_once __DIR__ . '/controllers/ReportController.php';
require_once __DIR__ . '/controllers/ImportController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/TargetController.php';

switch ($route) {
    case 'login':
        (new AuthController())->loginForm();
        break;
    case 'login_submit':
        if ($method === 'POST') {
            (new AuthController())->login();
            break;
        }
        redirect('index.php?route=login');
        break;
    case 'logout':
        (new AuthController())->logout();
        break;

    case 'dashboard':
        (new DashboardController())->index();
        break;

    case 'accounts':
        (new AccountController())->index();
        break;
    case 'accounts_store':
        (new AccountController())->store();
        break;
    case 'accounts_update':
        (new AccountController())->update();
        break;
    case 'accounts_toggle':
        (new AccountController())->toggle();
        break;

    case 'boxes':
        (new BoxController())->index();
        break;
    case 'boxes_store':
        (new BoxController())->store();
        break;
    case 'boxes_update':
        (new BoxController())->update();
        break;

    case 'categories':
        (new CategoryController())->index();
        break;
    case 'categories_store':
        (new CategoryController())->store();
        break;
    case 'categories_update':
        (new CategoryController())->update();
        break;
    case 'categories_delete':
        (new CategoryController())->delete();
        break;

    case 'transactions':
        (new TransactionController())->index();
        break;
    case 'transactions_suggest_category':
        (new TransactionController())->suggestCategory();
        break;
    case 'transactions_auto_classify_others':
        (new TransactionController())->autoClassifyOthers();
        break;
    case 'transactions_store':
        (new TransactionController())->store();
        break;
    case 'transactions_update':
        (new TransactionController())->update();
        break;
    case 'transactions_delete':
        (new TransactionController())->delete();
        break;

    case 'withdrawals':
        (new PartnerWithdrawalController())->index();
        break;
    case 'withdrawals_store':
        (new PartnerWithdrawalController())->store();
        break;

    case 'debts':
        (new DebtController())->index();
        break;
    case 'debts_store':
        (new DebtController())->store();
        break;
    case 'debts_show':
        (new DebtController())->show();
        break;
    case 'debts_pay_installment':
        (new DebtController())->payInstallment();
        break;
    case 'debts_refund_installment':
        (new DebtController())->refundInstallment();
        break;
    case 'debts_delete':
        (new DebtController())->delete();
        break;
    case 'debts_delete_installment':
        (new DebtController())->deleteInstallment();
        break;

    case 'budgets':
        (new BudgetController())->index();
        break;
    case 'budgets_store':
        (new BudgetController())->store();
        break;
    case 'budgets_delete':
        (new BudgetController())->delete();
        break;

    case 'goals':
        (new GoalController())->index();
        break;
    case 'goals_store':
        (new GoalController())->store();
        break;
    case 'goals_update':
        (new GoalController())->update();
        break;
    case 'goals_delete':
        (new GoalController())->delete();
        break;

    case 'reports':
        (new ReportController())->index();
        break;

    case 'imports':
        (new ImportController())->index();
        break;
    case 'imports_upload':
        (new ImportController())->upload();
        break;
    case 'imports_process_ofx_queue':
    case 'imports/process_ofx_queue':
        (new ImportController())->processOfxQueue();
        break;

    case 'targets':
        (new TargetController())->index();
        break;
    case 'targets_show':
        (new TargetController())->show();
        break;
    case 'targets_store':
        (new TargetController())->store();
        break;
    case 'targets_update':
        (new TargetController())->update();
        break;
    case 'targets_delete':
        (new TargetController())->delete();
        break;
    case 'targets_set_status':
        (new TargetController())->setStatus();
        break;

    case 'objectives_store':
        (new TargetController())->objectiveStore();
        break;
    case 'objectives_update':
        (new TargetController())->objectiveUpdate();
        break;
    case 'objectives_delete':
        (new TargetController())->objectiveDelete();
        break;
    case 'objectives_set_status':
        (new TargetController())->objectiveSetStatus();
        break;

    case 'decisions_store':
        (new TargetController())->decisionStore();
        break;
    case 'decisions_update':
        (new TargetController())->decisionUpdate();
        break;
    case 'decisions_delete':
        (new TargetController())->decisionDelete();
        break;

    case 'actions_store':
        (new TargetController())->actionStore();
        break;
    case 'actions_update':
        (new TargetController())->actionUpdate();
        break;
    case 'actions_delete':
        (new TargetController())->actionDelete();
        break;
    case 'actions_toggle_done':
        (new TargetController())->actionToggleDone();
        break;

    case 'users':
        (new UserController())->index();
        break;
    case 'users_store':
        (new UserController())->store();
        break;
    case 'users_update':
        (new UserController())->update();
        break;
    case 'users_toggle_status':
        (new UserController())->toggleStatus();
        break;
    case 'users_reset_password':
        (new UserController())->resetPassword();
        break;
    case 'users_scope':
        (new UserController())->scope();
        break;
    case 'users_clear_scope':
        (new UserController())->clearScope();
        break;

    default:
        http_response_code(404);
        echo 'Rota não encontrada.';
}
