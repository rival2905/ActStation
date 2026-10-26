<?php

declare(strict_types=1);

use App\Services\AgentOfficePeer;
use Illuminate\Http\Request;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$request = Request::capture();
try {
    $trust = $request->getPathInfo() === '/trusted' ? ['127.0.0.1/32'] : [];
    $peer = AgentOfficePeer::resolve($request, $trust);
    header('Content-Type: application/json');
    echo json_encode($peer + ['raw_target' => $request->server('REQUEST_URI')], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(403);
    echo '{}';
}
