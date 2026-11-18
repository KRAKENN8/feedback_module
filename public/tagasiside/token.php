<?php

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

echo json_encode(['token' => make_token()]);
