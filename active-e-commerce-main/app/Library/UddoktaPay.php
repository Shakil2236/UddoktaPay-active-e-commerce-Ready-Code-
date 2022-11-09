<?php

namespace App\Library;

use Exception;
use Illuminate\Support\Facades\Http;

class UddoktaPay {
    
    /**
     * Send payment request
     *
     * @param array $requestData
     * @return void
     */
    public static function init_payment($requestData) {
        $response = Http::withHeaders( [
            'Content-Type'          => 'application/json',
            'RT-UDDOKTAPAY-API-KEY' => env( "UDDOKTAPAY_API_KEY" ),
        ] )
            ->asJson()
            ->withBody( json_encode( $requestData ), "JSON" )
            ->post( env( "UDDOKTAPAY_API_URL" ) );
            
        if ( $response->successful() ) {

            $result = $response->json();

            if($result['status']){
                return $response->collect()['payment_url'];
            }else{
                throw new Exception($result['message']);
            }
        } 
        
        throw new Exception("Please recheck env configurations");
    }
    
    public static function ipn($invoice_id) {
        $apiUrl = str_replace('api/checkout-v2', 'api/verify-payment/', env( "UDDOKTAPAY_API_URL" ));
        $verifyUrl = trim($apiUrl . $invoice_id);
        
        $requestData = [
            'invoice_id'    => $invoice_id
        ];
        
        $response = Http::withHeaders( [
            'Content-Type'          => 'application/json',
            'RT-UDDOKTAPAY-API-KEY' => env( "UDDOKTAPAY_API_KEY" ),
        ] )
            ->asJson()
            ->withBody( json_encode( $requestData ), "JSON" )
            ->post( $verifyUrl );
            
        if ( $response->successful() ) {

            $result = $response->json();

            if($result['status']){
                return $result;
            }else{
                throw new Exception($result['message']);
            }
        } 
        
        throw new Exception("Please recheck env configurations");
    }
}