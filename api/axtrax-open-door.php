<?php
/**
 * AxTrax Open Door API
 * Open/unlock a specific door by ID
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
    // Get door ID from query parameter
    $doorId = isset($_GET['door_id']) ? (int)$_GET['door_id'] : 0;

    if ($doorId <= 0) {
        throw new Exception('Invalid door ID');
    }

    // Get all doors to verify this door exists
    $doorsData = axtraxApiRequest('GET', '/api/Door/GetAll', $config);

    if ($doorsData === null || !isset($doorsData['Data'])) {
        throw new Exception('Failed to fetch door list');
    }

    // Find the door in the list
    $door = null;
    foreach ($doorsData['Data'] as $d) {
        if ($d['ID'] == $doorId) {
            $door = $d;
            break;
        }
    }

    if ($door === null) {
        throw new Exception('Door not found');
    }

    // Trigger manual operation to unlock the door
    // Requires IdPanel and IdOutput from the door data
    $panelId = $door['IdPanel'] ?? null;
    $outputId = $door['IdOutput'] ?? null;

    if ($panelId === null || $outputId === null) {
        throw new Exception('Door is missing panel or output ID');
    }

    // Use REST API PanelManualOperation to unlock door
    // Single panel endpoint - panelID as query parameter, arrId contains output IDs for one panel
    // dtTime format: time portion (mm:ss) controls unlock duration - 00:00:04 = 4 seconds
    $operationData = [
        'IdPanel' => $panelId,
        'ManualType' => 2,  // Open by timer
        'arrId' => [$outputId],
        'dtTime' => '0001-01-01T00:00:04Z',  // 4 second unlock duration
        'iType' => 2,
        'iMode' => 0,
        'bEnabled' => true
    ];

    $endpoint = '/api/ManualOperation/PanelManualOperation?panelID=' . $panelId;
    $result = axtraxApiRequest('PUT', $endpoint, $config, $operationData);

    if ($result === null) {
        throw new Exception('Failed to trigger door unlock');
    }

    if (isset($result['Errors']) && !empty($result['Errors'])) {
        throw new Exception('AxTrax API errors: ' . json_encode($result['Errors']));
    }

    echo json_encode([
        'ok' => true,
        'message' => 'Door unlocked for 4 seconds',
        'door_id' => $doorId,
        'door_name' => $door['tDesc'] ?? 'Unknown',
        'panel_id' => $panelId,
        'output_id' => $outputId
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
