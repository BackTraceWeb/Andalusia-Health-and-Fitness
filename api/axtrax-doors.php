<?php
/**
 * AxTrax Door Status API
 * Get real-time status of all doors
 * Uses same pattern as QuickPay (axtrax-helpers.php)
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/axtrax-config.php';
require_once __DIR__ . '/axtrax-helpers.php';

session_start();
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$config = require __DIR__ . '/axtrax-config.php';

try {
    // Use QuickPay's helper function for API request
    $doorsData = axtraxApiRequest('GET', '/api/Door/GetAll', $config);

    if ($doorsData === null || !isset($doorsData['Data'])) {
        throw new Exception('Failed to fetch door data');
    }

    $doors = [];
    foreach ($doorsData['Data'] as $door) {
        $doors[] = [
            'id' => $door['ID'] ?? 0,
            'name' => $door['tDesc'] ?? 'Unknown Door',
            'panel_id' => $door['IdPanel'] ?? 0,
            'door_number' => $door['wDoorNumber'] ?? 0,
            'status' => $door['DoorStatus'] ?? 0,
            'online' => $door['bOnline'] ?? false,
            'forced' => $door['bForced'] ?? false,
            'held_alert' => $door['bHeldAlert'] ?? false,
            'rex' => $door['bRex'] ?? false,
            'output_id' => $door['IdOutput'] ?? 0,
        ];
    }

    echo json_encode([
        'ok' => true,
        'doors' => $doors,
        'count' => count($doors),
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
