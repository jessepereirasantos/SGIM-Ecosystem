<?php
namespace App\Core;

class JwtHelper {
    private static $secret = 'SGIM-SECRET-KEY-REPLACE-IN-PRODUCTION';

    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secret, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function decode($jwt) {
        $tokenParts = explode('.', $jwt);
        if (count($tokenParts) !== 3) return false;

        $header = $tokenParts[0];
        $payload = $tokenParts[1];
        $signatureProvided = $tokenParts[2];

        $signatureCheck = hash_hmac('sha256', $header . "." . $payload, self::$secret, true);
        $base64UrlSignatureCheck = self::base64UrlEncode($signatureCheck);

        if ($base64UrlSignatureCheck !== $signatureProvided) return false;

        return json_decode(base64_decode($payload), true);
    }

    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
