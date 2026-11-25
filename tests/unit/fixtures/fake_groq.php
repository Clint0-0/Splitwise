<?php
// Fake Groq chat-completions API (OpenAI format) for tests/unit (run with php -S).
// Checks the request the way the real API would and answers like it. No network.
$h = array_change_key_case(getallheaders());
$body = json_decode((string) file_get_contents('php://input'), true);
$parts = $body['messages'][0]['content'] ?? [];
$img = '';
foreach ((array) $parts as $p) {
    if (($p['type'] ?? '') === 'image_url') { $img = (string) ($p['image_url']['url'] ?? ''); }
}
$b64 = preg_match('~^data:image/(png|jpeg|webp);base64,(.+)$~s', $img, $m) ? $m[2] : '';
$ok = ($_SERVER['REQUEST_METHOD'] === 'POST')
    && ($h['authorization'] ?? '') === 'Bearer gsk_test_key'
    && str_starts_with($h['content-type'] ?? '', 'application/json')
    && is_string($body['model'] ?? null)
    && ($body['response_format']['type'] ?? '') === 'json_object'
    && ($parts[0]['type'] ?? '') === 'text'
    && $b64 !== '' && base64_decode($b64, true) !== false
    && strlen($b64) <= 4 * 1024 * 1024;   // Groq's base64 image limit
header('Content-Type: application/json');
if (($h['authorization'] ?? '') === 'Bearer gsk_busy') {
    http_response_code(429);
    echo '{"error":{"message":"Rate limit reached","type":"tokens"}}';
    return;
}
if (!$ok) {
    http_response_code(400);
    echo '{"error":{"message":"bad request","type":"invalid_request_error"}}';
    return;
}
echo json_encode(['id' => 'chatcmpl-test', 'object' => 'chat.completion', 'model' => $body['model'],
    'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant',
        'content' => "<think>The bill shows two items {not json}</think>\n"
            . '{"merchant":"Groq Cafe","date":"2026-11-02","items":[{"name":"Idli","qty":2,"amount":60},{"name":"Vada","qty":1,"amount":"45.50"}],'
            . '"charges":[{"kind":"tax","label":"GST 5%","amount":5.28}],"total":110.78}']]]]);
