<?php
/**
 * Rate Limiting Helper
 * Protects API endpoints from abuse
 */

class RateLimiter {
    private $pdo;
    private $enabled;
    private $maxRequests;
    private $windowSeconds;

    public function __construct() {
        $this->enabled = config('RATE_LIMIT_ENABLED', true);
        $this->maxRequests = config('RATE_LIMIT_REQUESTS', 60);
        $this->windowSeconds = config('RATE_LIMIT_WINDOW', 60);

        if ($this->enabled) {
            $this->pdo = pdo();
            $this->ensureTable();
        }
    }

    private function ensureTable() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    identifier VARCHAR(255) NOT NULL,
                    endpoint VARCHAR(255) NOT NULL,
                    request_count INT DEFAULT 1,
                    window_start INT NOT NULL,
                    INDEX idx_identifier_endpoint (identifier, endpoint),
                    INDEX idx_window (window_start)
                )
            ");
        } catch (PDOException $e) {
            error_log("Rate limit table creation failed: " . $e->getMessage());
        }
    }

    /**
     * Check if request should be rate limited
     * @param string $endpoint The endpoint being accessed
     * @return bool True if allowed, false if rate limited
     */
    public function check($endpoint = null) {
        if (!$this->enabled) {
            return true;
        }

        if ($endpoint === null) {
            $endpoint = $_SERVER['REQUEST_URI'] ?? 'unknown';
        }

        $identifier = $this->getIdentifier();
        $now = time();
        $windowStart = $now - $this->windowSeconds;

        try {
            // Clean up old entries
            $this->pdo->prepare("DELETE FROM rate_limits WHERE window_start < ?")
                ->execute([$windowStart]);

            // Check current rate
            $stmt = $this->pdo->prepare("
                SELECT SUM(request_count) as total
                FROM rate_limits
                WHERE identifier = ?
                  AND endpoint = ?
                  AND window_start >= ?
            ");
            $stmt->execute([$identifier, $endpoint, $windowStart]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentCount = (int)($result['total'] ?? 0);

            if ($currentCount >= $this->maxRequests) {
                $this->sendRateLimitResponse();
                return false;
            }

            // Increment counter
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (identifier, endpoint, request_count, window_start)
                VALUES (?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE request_count = request_count + 1
            ");
            $stmt->execute([$identifier, $endpoint, $now]);

            return true;
        } catch (PDOException $e) {
            error_log("Rate limit check failed: " . $e->getMessage());
            // Fail open - allow request if rate limiting fails
            return true;
        }
    }

    private function getIdentifier() {
        // Use IP address as primary identifier
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // For authenticated users, also include session ID
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['logged_in'])) {
            $ip .= '_' . session_id();
        }

        return $ip;
    }

    private function sendRateLimitResponse() {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $this->windowSeconds);
        echo json_encode([
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $this->windowSeconds
        ]);
        exit;
    }
}

/**
 * Convenience function to check rate limit
 */
function rate_limit($endpoint = null) {
    static $limiter;
    if ($limiter === null) {
        $limiter = new RateLimiter();
    }
    return $limiter->check($endpoint);
}
