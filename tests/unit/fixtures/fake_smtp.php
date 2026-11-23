<?php
// Fake SMTP server for tests/unit: one connection, plain text, records the session.
// php fake_smtp.php PORT TRANSCRIPT_FILE
[$_, $port, $file] = $argv;
$srv = stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
$c = stream_socket_accept($srv, 20);
$log = '';
$say = function (string $l) use ($c, &$log) { fwrite($c, "$l\r\n"); $log .= "S: $l\n"; };
$say('220 fake.smtp ready');
$inData = false; $data = ''; $auth = 0;   // AUTH LOGIN: 1 = waiting for user, 2 = waiting for password
while (($line = fgets($c)) !== false) {
    $log .= 'C: ' . rtrim($line, "\r\n") . "\n";
    if ($inData) {
        if (rtrim($line, "\r\n") === '.') { $inData = false; $say('250 queued'); file_put_contents("$file.data", $data); continue; }
        $data .= $line; continue;
    }
    if ($auth === 1) { $auth = 2; $say('334 UGFzc3dvcmQ6'); continue; }
    if ($auth === 2) { $auth = 0; $say('235 ok'); continue; }
    $cmd = strtoupper(substr(trim($line), 0, 4));
    if ($cmd === 'EHLO') { fwrite($c, "250-fake.smtp\r\n250 AUTH LOGIN\r\n"); }
    elseif ($cmd === 'AUTH') { $auth = 1; $say('334 VXNlcm5hbWU6'); }
    elseif ($cmd === 'MAIL' || $cmd === 'RCPT') { $say('250 ok'); }
    elseif ($cmd === 'DATA') { $inData = true; $say('354 go'); }
    elseif ($cmd === 'QUIT') { $say('221 bye'); break; }
    else { $say('500 what'); }
}
file_put_contents($file, $log);
