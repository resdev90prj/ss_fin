<?php
$rootIndex = realpath(__DIR__ . '/../index.php');
if ($rootIndex !== false && is_file($rootIndex)) {
    require $rootIndex;
    exit;
}

http_response_code(500);
echo 'Bootstrap principal nao encontrado.';
