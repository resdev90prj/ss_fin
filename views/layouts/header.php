<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <?php
  $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
  $assetBasePath = ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/');
  $faviconUrl = $assetBasePath . '/public_html/assets/branding/finance_logo_v3.ico?v=20260308_3';
  ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? 'SaaS IA Finan') ?></title>
  <link rel="icon" type="image/x-icon" href="<?= e($faviconUrl) ?>" sizes="any">
  <link rel="shortcut icon" type="image/x-icon" href="<?= e($faviconUrl) ?>">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
