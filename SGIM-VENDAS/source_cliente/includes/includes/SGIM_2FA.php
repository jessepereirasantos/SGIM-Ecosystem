<?php
/**
 * Simple TOTP Implementation for SGIM
 * Based on RFC 6238
 */
class SGIM_2FA {
    public static function createSecret($length = 16) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[rand(0, 31)];
        }
        return $secret;
    }

    public static function getQRCodeUrl($name, $secret, $title = 'SGIM') {
        $name = urlencode($name);
        $title = urlencode($title);
        return "otpauth://totp/$title:$name?secret=$secret&issuer=$title";
    }

    public static function verifyCode($secret, $code, $discrepancy = 1) {
        $currentTimeSlice = floor(time() / 30);
        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::calculateCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode == $code) {
                return true;
            }
        }
        return false;
    }

    private static function calculateCode($secret, $timeSlice) {
        $secretUpper = strtoupper($secret);
        $secretKey = self::base32Decode($secretUpper);
        
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $hashPart = substr($hash, $offset, 4);
        
        $value = unpack('N', $hashPart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode($base32) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32 = str_replace('=', '', $base32);
        $chunks = str_split($base32, 8);
        $bin = '';
        foreach ($chunks as $chunk) {
            $chunkLen = strlen($chunk);
            $charCodes = [];
            for ($i = 0; $i < $chunkLen; $i++) {
                $charCodes[] = strpos($alphabet, $chunk[$i]);
            }
            
            if ($chunkLen >= 2) $bin .= chr(($charCodes[0] << 3) | ($charCodes[1] >> 2));
            if ($chunkLen >= 4) $bin .= chr(($charCodes[1] << 6) | ($charCodes[2] << 1) | ($charCodes[3] >> 4));
            if ($chunkLen >= 5) $bin .= chr(($charCodes[3] << 4) | ($charCodes[4] >> 1));
            if ($chunkLen >= 7) $bin .= chr(($charCodes[4] << 7) | ($charCodes[5] << 2) | ($charCodes[6] >> 3));
            if ($chunkLen == 8) $bin .= chr(($charCodes[6] << 5) | $charCodes[7]);
        }
        return $bin;
    }
}
