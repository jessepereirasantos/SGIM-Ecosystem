<?php
/**
 * MercadoPagoService - SGIM-VENDAS
 * Replicado do backup para garantir a mesma lógica de criação de pagamentos.
 */
class MercadoPagoService {
    private $accessToken;

    public function __construct($accessToken) {
        $this->accessToken = $accessToken;
    }

    public function createPayment($data) {
        $ch = curl_init("https://api.mercadopago.com/v1/payments");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->accessToken,
            "X-Idempotency-Key: " . uniqid()
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result = json_decode($response, true);
        curl_close($ch);

        return [
            'status_code' => $httpCode,
            'response' => $result
        ];
    }

    public function getPayment($paymentId) {
        $ch = curl_init("https://api.mercadopago.com/v1/payments/" . $paymentId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->accessToken
        ]);

        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);

        return $result;
    }
}
