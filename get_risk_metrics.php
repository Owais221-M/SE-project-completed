<?php
/**
 * API endpoint: Returns portfolio risk metrics as JSON
 * Called by dashboard.js to display the risk panel
 */
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

require_once 'config.php';
require_once 'RiskManager.php';

$risk = new RiskManager($conn, $_SESSION['id']);
echo json_encode($risk->getPortfolioMetrics());
