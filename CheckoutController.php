<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

class CheckoutController extends Controller
{
   
    public function payment($price){
        // Константные переменные
// Certificate Authority Банка
        $ca = "/PSroot.pem";

// Приватный ключ Торговца
        $key = "/private.0019463.pem";

// PKCS#12 кейстор с подписанным сертификатом Торговца
        $cert = "/certificate.0019463.pem";
// Пароль от PKCS#12 кейстора
        $password = "";

// URL MerchantHandler Банка. По этому адресу Торговец делает запрос в
// банк с деталями платежа а так же деталями карты
        $merchant_handler = "https://ecomm.pashabank.az:18443/ecomm2/MerchantHandler";

// URL ClientHandler Банка. По этому адресу Торговец редиректит Клиента
// на CardSuite ECOMM модуль
        $client_handler = "https://ecomm.pashabank.az:8463/ecomm2/ClientHandler";

// Название страницы приведено в качестве примера
        $system_malfunction_page = "system_malfunction.html";

// Параметры платежа полученные
        $amount = 100;

// Поле amount должно иметь длинну 1 – 12 числовых символов
        if (!is_numeric($amount) || strlen($amount) < 1 || strlen($amount) > 12) {
            // error
        }

        $arr = array('AZN'=>944, 'USD'=>840, 'EUR'=>978 );
        $currency =944;

// Поле currency должно иметь длину 3 числовых символа
        if (!is_numeric($currency) || strlen($currency) != 3) {
            // error
        }

        $description = "salam";

// Поле description может содержать любые символы, максимальная длина // 125 символов
        if (strlen($description) > 125) {
            // error
        }

        $language = "AZ";

// Поле language должно содержать 2 символа
        if (!ctype_alpha($language)) {
            // error
        }

        $params['command'] = "V";
        $params['amount'] = $amount;
        $params['currency'] = $currency;
        $params['description'] = $description;
        $params['language'] = $language;
        $params['msg_type'] = "SMS";

// IP адрес Клиента
        if (filter_input(INPUT_SERVER, 'REMOTE_ADDR') != null) {
            $params['client_ip_addr'] = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        }   elseif (filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR') != null) {
            $params['client_ip_addr'] =
                filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR');
        }   elseif (filter_input(INPUT_SERVER, 'HTTP_CLIENT_IP') != null) {
            $params['client_ip_addr'] = filter_input(INPUT_SERVER, 'HTTP_CLIENT_IP');
        } else {
            // should never happen
            $params['client_ip_addr'] = "127.0.0.1";
        }

        $qstring = http_build_query($params);


        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $merchant_handler);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $qstring);
        curl_setopt($ch, CURLOPT_SSLCERT, $cert);
        curl_setopt($ch, CURLOPT_SSLKEY, $key);
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, "PEM");
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $password);
        curl_setopt($ch, CURLOPT_CAPATH, $ca);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, "PEM");
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

        $result = curl_exec($ch);

// пример вернувшегося результата
// TRANSACTION_ID: TwXcbhBgrIsMY0A7s982nx/pSzE=

        if (curl_error($ch)) {
            echo curl_error($ch) . "<br>";
            echo "Error code: " . curl_errno($ch);
            curl_close($ch);
            exit;
        }

//   if (curl_error($ch)) {
// 	  header("Location: " . $system_malfunction_page);
//   }

        curl_close($ch);

// Получение TRANS_REF-а
        $trans_ref = explode(' ', $result)[1];


// TRANS_REF может содержать специальные символы, которые необходимо
// перекодировать в HTTP кодировку
        $trans_ref = urlencode($trans_ref);


// Добавление TRANS_REF-а к клиентскому URL модуля CardSuite ECOMM
        $client_url = $client_handler . "?trans_id=" . $trans_ref;

// Переадресация Клиента на клиентский URL модуля CardSuite ECOMM
//            $transaction_id =  substr($result, 15);
            return  response([
                "link"=> $client_url,
                "trans_id"=>   explode(' ', $result)[1],
                "price" => $price,
            ]);
    }
    public function vacancy_payment(Request $request){
       return $this->payment($request->price);
    }


    public function  create_invoice(Request $request){
        $order = Order::create([
            "user_id" => $request->user_id,
            "qebz_no" => $request->qebz_no,
            "type" =>"vacancy",
            "title" => $request->title,
            "description" => $request->reason,
            "amount" => $request->amount
        ]);
        return response([
            'message' => 'Order Created Successfully',
            'data'    => $order
        ], 200);
    }
    public function getDetails(Request $request){
        $trans_id = $request->trans_id;
        $ca = "/PSroot.pem";

// Приватный ключ Торговца
        $key = "/private.0019463.pem";

// PKCS#12 кейстор с подписанным сертификатом Торговца
        $cert = "/certificate.0019463.pem";

        $password = "";

        $merchant_handler = "https://ecomm.pashabank.az:18443/ecomm2/MerchantHandler";
        $client_handler = "https://ecomm.pashabank.az:8463/ecomm2/ClientHandler";

        $errors = [];
        $paymentDetails = [];

        $success_page = "success.html";
        $card_expired_page = "card_expired.html";
        $insufficient_funds_page = "insufficient_funds.html";
        $system_malfunction_page = "system_malfunction.html";
        // Example for Query String response to RETURN_OK_URL:
        // ?trans_id=5h78PCxRzsRSzLxuDEWDyhSeC44=&amp;Ucaf_Cardholder_Confirm=0

        if (
            // strlen($trans_id) != 20 ||
            base64_encode(base64_decode($trans_id)) != $trans_id
        ) {
            abort(403, 'Incorrect transaction id');
        }

        $params['command'] = "C";
        $params['trans_id'] = $trans_id;

        if (filter_input(INPUT_SERVER, 'REMOTE_ADDR') != null) {
            $params['client_ip_addr'] = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        } elseif (filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR') != null) {
            $params['client_ip_addr'] =
                filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR');
        } elseif (filter_input(INPUT_SERVER, 'HTTP_CLIENT_IP') != null) {
            $params['client_ip_addr'] = filter_input(INPUT_SERVER, 'HTTP_CLIENT_IP');
        } else {
            $params['client_ip_addr'] = "10.10.10.10";
        }

        $qstring = http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $merchant_handler);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $qstring);
        curl_setopt($ch, CURLOPT_SSLCERT, $cert);
        curl_setopt($ch, CURLOPT_SSLKEY, $key);
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, "PEM");
        curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $password);
        curl_setopt($ch, CURLOPT_CAPATH, $ca);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, "PEM");
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        $result = curl_exec($ch);

        // Example returning result:
        // RESULT: OK
        // RESULT_PS: FINISHED
        // RESULT_CODE: 000
        // 3DSECURE: ATTEMPTED
        // RRN: 123456789012
        // APPROVAL_CODE: 123456
        // CARD_NUMBER: 4***********9999
        // RECC_PMNT_ID: 1258
        // RECC_PMNT_EXPIRY: 1108
        // for debug reasons only!

        if (curl_error($ch)) array_push($errors, 'Payment error!');

        curl_close($ch);

        $r_hm = array();
        $r_arr = array();

        $r_arr = explode("\n", $result);

        for ($i = 0; $i < count($r_arr); $i++) {
            $param = explode(":", $r_arr[$i])[0];
            $value = substr(explode(":", $r_arr[$i])[1], 1);
            $r_hm[$param] = $value;
        }

        if ($r_hm["RESULT"] == "OK") {
            if ($r_hm["RESULT_CODE"] == "000") $paymentDetails['status'] = 'completed';
            else $paymentDetails['status'] = 'not_completed';
        } elseif ($r_hm["RESULT"] == "FAILED") {
            if ($r_hm["RESULT_CODE"] == "116") $paymentDetails['status'] = 'insufficent_funds';
            elseif ($r_hm["RESULT_CODE"] == "129") $paymentDetails['status'] = 'card_expired';
            elseif ($r_hm["RESULT_CODE"] == "909") $paymentDetails['status'] = 'system_malfunction';
            else $paymentDetails['status'] = 'system_malfunction';
        } elseif ($r_hm["RESULT"] == "TIMEOUT") $paymentDetails['status'] = 'timeout';
        else $paymentDetails['status'] = 'system_malfunction';

        if ($r_hm["RESULT"] == "FAILED") {
            Log::info([
                'transaction_id' => $trans_id,
                'error_code' => $r_hm["RESULT_CODE"],
                'rrn' => $r_hm["RRN"]
            ]);
        }


        return ['trans_id' => $trans_id, 'errors' => $errors, 'paymentDetails' => $paymentDetails];
    }


    public  function payment_success(Request $request){
        $order = Order::where("qebz_no", $request->qebz_no)->first();
        $order->status = 2;
        $order->save();
        return response([
            'message' => 'Ödəniş uğurla keçdi',
            'data'    => $order
        ], 200);
    }

}


