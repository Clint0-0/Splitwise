<?php
// Fake Anthropic Messages API for tests/unit (run with php -S). Checks the request the
// way the real API would and answers like it. Never contacts the network.
$h = array_change_key_case(getallheaders());
$body = json_decode((string) file_get_contents('php://input'), true);
$ok = ($_SERVER['REQUEST_METHOD'] === 'POST')
    && ($h['x-api-key'] ?? '') === 'test-key-123'
    && ($h['anthropic-version'] ?? '') === '2023-06-01'
    && str_starts_with($h['content-type'] ?? '', 'application/json')
    && is_string($body['model'] ?? null) && ($body['max_tokens'] ?? 0) > 0
    && ($body['messages'][0]['content'][0]['type'] ?? '') === 'image'
    && ($body['messages'][0]['content'][0]['source']['media_type'] ?? '') === 'image/png'
    && base64_decode($body['messages'][0]['content'][0]['source']['data'] ?? '', true) !== false
    && ($body['messages'][0]['content'][1]['type'] ?? '') === 'text';
header('Content-Type: application/json');
if (!$ok) {
    http_response_code(400);
    echo '{"type":"error","error":{"type":"invalid_request_error","message":"bad request"}}';
    return;
}
echo json_encode(['id' => 'msg_test', 'type' => 'message', 'role' => 'assistant', 'model' => $body['model'],
    'content' => [['type' => 'text', 'text' => "Here is the bill:\n```json\n"
        . '{"merchant":"Cafe Test","items":[{"name":"Masala dosa","qty":2,"amount":180},{"name":"Filter coffee","qty":2,"amount":"90.50"},'
        . '{"name":"","amount":10},{"name":"Bad amount","amount":"abc"}],"total":284.03}' . "\n```"]],
    'stop_reason' => 'end_turn']);
