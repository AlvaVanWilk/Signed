<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Signed\Api;
use Signed\ApiError;
use Signed\Config;
use Signed\Db;
use Signed\HttpSession;
use Signed\Schema;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$respond = static function (int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
};

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = (string) ($_GET['a'] ?? '');
    $input = [];
    if ($method === 'POST') {
        // Eigener Header: Fremde Seiten können ihn nicht ohne CORS-Freigabe setzen (CSRF-Schutz).
        if (($_SERVER['HTTP_X_SIGNED'] ?? '') !== '1') {
            throw new ApiError('Ungültige Anfrage.', 400);
        }
        $raw = (string) file_get_contents('php://input');
        try {
            $input = $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiError('Ungültige Daten.', 400);
        }
        if (!is_array($input)) {
            throw new ApiError('Ungültige Daten.', 400);
        }
    }

    $db = Db::fromConfig();
    Schema::migrate($db);
    // Neuer Kern zum Ausprobieren auf dem eigenen Rechner (config.php: 'core' => true); auf dem Webspace steht das nicht drin.
    Signed\Game\BandCore::$live = Signed\Game\BandCore::$live || (bool) Config::get('core', false);
    $result = (new Api($db, new HttpSession()))->handle($method, $action, $input);
    $respond(200, ['ok' => true] + $result);
} catch (ApiError $e) {
    $respond($e->status, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('[signed] ' . $e);
    $respond(500, ['ok' => false, 'error' => Config::get('debug') ? $e->getMessage() : 'Serverfehler. Bitte versuch es gleich noch einmal.']);
}
