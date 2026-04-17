<?php

declare(strict_types=1);

function redirect_with_status(string $status): never
{
    header('Location: /kontakt/?status=' . rawurlencode($status));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('invalid');
}

$configPath = __DIR__ . '/mail-config.local.php';
if (!is_file($configPath)) {
    $configPath = __DIR__ . '/mail-config.example.php';
}

$config = require $configPath;

$honeypot = trim((string)($_POST['website'] ?? ''));
if ($honeypot !== '') {
    redirect_with_status('sent');
}

$firstName = trim((string)($_POST['firstName'] ?? ''));
$lastName = trim((string)($_POST['lastName'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$arrival = trim((string)($_POST['arrival'] ?? ''));
$departure = trim((string)($_POST['departure'] ?? ''));
$adults = trim((string)($_POST['adults'] ?? ''));
$children = trim((string)($_POST['children'] ?? '0'));
$dog = trim((string)($_POST['dog'] ?? 'Nein'));
$notes = trim((string)($_POST['notes'] ?? ''));

if (
    $firstName === '' ||
    $lastName === '' ||
    $email === '' ||
    $phone === '' ||
    $arrival === '' ||
    $departure === '' ||
    $adults === ''
) {
    redirect_with_status('missing');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_status('email');
}

function smtp_read($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_expect($socket, array $codes): void
{
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }
}

function smtp_command($socket, string $command, array $codes): void
{
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $codes);
}

function encode_header(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

$subject = 'Neue Buchungsanfrage von der Website';
$bodyLines = [
    'Neue Buchungsanfrage',
    '',
    'Vorname: ' . $firstName,
    'Nachname: ' . $lastName,
    'E-Mail: ' . $email,
    'Telefon: ' . $phone,
    'Anreise: ' . $arrival,
    'Abreise: ' . $departure,
    'Erwachsene: ' . $adults,
    'Kinder bis 17 Jahre: ' . $children,
    'Hund: ' . ($dog !== '' ? $dog : 'Nein'),
    '',
    'Wünsche und Anmerkungen:',
    $notes !== '' ? $notes : '-',
];

$body = implode("\r\n", $bodyLines);
$headers = [
    'Date: ' . date(DATE_RFC2822),
    'From: ' . encode_header($config['from_name']) . ' <' . $config['from_email'] . '>',
    'To: ' . encode_header($config['to_name']) . ' <' . $config['to_email'] . '>',
    'Reply-To: ' . $email,
    'Subject: ' . encode_header($subject),
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
];

$message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n";
$message = str_replace("\r\n.", "\r\n..", $message);

$transport = ($config['secure'] ?? '') === 'ssl' ? 'ssl://' : '';
$socket = @stream_socket_client(
    $transport . $config['host'] . ':' . $config['port'],
    $errno,
    $errstr,
    20,
    STREAM_CLIENT_CONNECT
);

if (!$socket) {
    redirect_with_status('server');
}

stream_set_timeout($socket, 20);

try {
    smtp_expect($socket, [220]);
    smtp_command($socket, 'EHLO ferienwohnung-hubmann.at', [250]);

    if (($config['secure'] ?? '') === 'tls') {
        smtp_command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('TLS handshake failed');
        }
        smtp_command($socket, 'EHLO ferienwohnung-hubmann.at', [250]);
    }

    smtp_command($socket, 'AUTH LOGIN', [334]);
    smtp_command($socket, base64_encode($config['username']), [334]);
    smtp_command($socket, base64_encode($config['password']), [235]);
    smtp_command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250]);
    smtp_command($socket, 'RCPT TO:<' . $config['to_email'] . '>', [250, 251]);
    smtp_command($socket, 'DATA', [354]);
    fwrite($socket, $message . ".\r\n");
    smtp_expect($socket, [250]);
    smtp_command($socket, 'QUIT', [221]);
} catch (Throwable $e) {
    fclose($socket);
    redirect_with_status('send');
}

fclose($socket);
redirect_with_status('sent');
