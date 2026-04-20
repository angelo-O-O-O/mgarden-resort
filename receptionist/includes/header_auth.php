<?php
require_once __DIR__ . '/config.php';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login — <?= SITE_NAME ?> Receptionist</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"/>
  <link rel="stylesheet" href="<?= SITE_URL ?>/receptionist/css/style.css?v=6"/>
</head>
<body>