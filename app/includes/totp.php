<?php

// Власна реалізація TOTP (RFC 6238) чистим PHP, без composer/зовнішніх
// бібліотек - алгоритм звірено з офіційними тестовими векторами RFC 6238
// (секрет "12345678901234567890", SHA1, 8 цифр) перед підключенням сюди.

const TOTP_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;

function totp_generate_secret(int $bytes = 20): string
{
    return totp_base32_encode(random_bytes($bytes));
}

function totp_base32_encode(string $data): string
{
    $binaryString = '';
    foreach (str_split($data) as $char) {
        $binaryString .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    $encoded = '';
    foreach (str_split($binaryString, 5) as $chunk) {
        $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $encoded .= TOTP_ALPHABET[bindec($chunk)];
    }

    return $encoded;
}

function totp_base32_decode(string $b32): string
{
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $b32));

    $binaryString = '';
    foreach (str_split($b32) as $char) {
        $pos = strpos(TOTP_ALPHABET, $char);
        if ($pos === false) {
            continue;
        }
        $binaryString .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    $bytes = '';
    foreach (str_split($binaryString, 8) as $byte) {
        if (strlen($byte) === 8) {
            $bytes .= chr(bindec($byte));
        }
    }

    return $bytes;
}

function totp_hotp(string $binarySecret, int $counter, int $digits = TOTP_DIGITS): string
{
    // 8-байтний big-endian лічильник - старші 4 байти завжди 0, оскільки
    // unix-час/30 не перевищить 32-бітний діапазон ще довго (до 2106 року).
    $binaryCounter = pack('N', 0) . pack('N', $counter);
    $hash = hash_hmac('sha1', $binaryCounter, $binarySecret, true);

    $offset = ord($hash[19]) & 0x0F;
    $truncated = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);

    $code = $truncated % (10 ** $digits);
    return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
}

function totp_code(string $base32Secret, ?int $timestamp = null): string
{
    $timestamp ??= time();
    $counter = (int) floor($timestamp / TOTP_PERIOD);
    return totp_hotp(totp_base32_decode($base32Secret), $counter);
}

// window=1 - приймає код з попереднього/наступного 30-секундного вікна
// теж, бо годинник телефону й сервера рідко ідеально синхронні.
function totp_verify(string $base32Secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }

    $binarySecret = totp_base32_decode($base32Secret);
    $counter = (int) floor(time() / TOTP_PERIOD);

    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_hotp($binarySecret, $counter + $i), $code)) {
            return true;
        }
    }

    return false;
}

function totp_uri(string $base32Secret, string $username, string $issuer = 'Nyxilum CMS'): string
{
    $label = rawurlencode($issuer) . ':' . rawurlencode($username);
    return 'otpauth://totp/' . $label
        . '?secret=' . $base32Secret
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}
