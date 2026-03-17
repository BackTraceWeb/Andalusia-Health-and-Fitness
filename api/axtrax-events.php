<?php
/**
 * AxTrax Events/Access Logs API
 * Get real-time access events and door entry logs
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
    // Get query parameters (default to last 7 days)
    $from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d\TH:i:s', strtotime('-7 days'));
    $to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d\TH:i:s');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;

    // Build endpoint with required query parameters
    $endpoint = '/api/EventPanelInfo/GetAll?from=' . urlencode($from) . '&to=' . urlencode($to) . '&limit=' . $limit;

    // Use QuickPay's helper function for API request
    $eventsData = axtraxApiRequest('GET', $endpoint, $config);

    if ($eventsData === null) {
        throw new Exception('Failed to fetch events data - API request returned null');
    }

    if (!isset($eventsData['Data'])) {
        throw new Exception('Failed to fetch events data - missing Data key in response');
    }

    $events = [];
    foreach ($eventsData['Data'] as $event) {
        $events[] = [
            'id' => $event['ID'] ?? 0,
            'timestamp' => $event['dtEventReal'] ?? '',
            'member_name' => $event['tFullName'] ?? 'Unknown',
            'event_type' => $event['iEventType'] ?? 0,
            'event_subtype' => $event['iEventSubType'] ?? 0,
            'event_source' => $event['iEventSource'] ?? 0,
            'panel_id' => $event['IdPanel'] ?? 0,
            'reader_id' => $event['IdReader'] ?? 0,
            'door_id' => $event['IdDoor'] ?? 0,
            'card_code' => $event['iCardCode'] ?? 0,
            'site_code' => $event['iSiteCode'] ?? 0,
            'description' => $event['tDesc'] ?? '',
            'status' => $event['wStatus'] ?? 0,
            'enabled' => $event['bEnabled'] ?? false,
        ];
    }

    // Sort by timestamp descending (most recent first)
    usort($events, function($a, $b) {
        return strcmp($b['timestamp'], $a['timestamp']);
    });

    echo json_encode([
        'ok' => true,
        'events' => $events,
        'count' => count($events),
        'timestamp' => date('c')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
