<?php 

class EbClientAmocrm{
  
    function __construct($secret_key, $intagration_id, $client_domen, $redirect_uri, $auth_token) {

        $this->config = array(
            'secret_key' => $secret_key, //секретный ключ 
            'intagration_id' => $intagration_id, // id интеграции
            'client_domen' => $client_domen, //домент данного аккаунта в AmoCRM
            'redirect_uri' => $redirect_uri,  //адресс редиректа
            'auth_token' => $auth_token //auth токен    
        );

        if(empty(file_get_contents("refresh.txt")) || empty(file_get_contents("access.txt"))) //если token не указан, то создаем его
            $this::get_refresh();
        
        $this->refresh = file_get_contents("refresh.txt");
        $this->access = file_get_contents("access.txt");
    }

    private function get_refresh(){ //создать первую пару токенов
        /** Соберем данные для запроса */
        $data = [
            'client_id' => $this->config['intagration_id'],
            'client_secret' => $this->config['secret_key'],
            'grant_type' => 'authorization_code',
            'code' => $this->config['auth_token'],
            'redirect_uri' => $this->config['redirect_uri'],
        ];
    
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, "https://".$this->config['client_domen'].".amocrm.ru/oauth2/access_token");
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json']);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $code = (int)$code;
        $response = json_decode($out, true);
        $r = array(
            "access_token" => $response['access_token'], //Access токен
            "refresh_token" => $response['refresh_token'], //Refresh токен
            "token_type" => $response['token_type'], //Тип токена
            "expires_in" => $response['expires_in'] //Через сколько действие токена истекает
        );
        if(empty($r['access_token'])) //если результата нет - выводим ошибку
            die("Обновите auth токен!");
        else{
            $accesstxt = fopen("access.txt", 'w') or die("не удалось создать файл");
            $refreshtxt = fopen("refresh.txt", 'w') or die("не удалось создать файл");
    
            fwrite($accesstxt, $r['access_token']);
            fwrite($refreshtxt, $r['refresh_token']);
    
            fclose($accesstxt);
            fclose($refreshtxt);
        }
        return $r;
    }

    private function get_acess(){ //обновить access токен
        $link = 'https://' . $this->config['client_domen'] . '.amocrm.ru/oauth2/access_token'; //Формируем URL для запроса

        $data = [
            'client_id' => $this->config['intagration_id'],
            'client_secret' => $this->config['secret_key'],
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refresh,
            'redirect_uri' => $this->config['redirect_uri'],
        ];

        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json']);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl); //Инициируем запрос к API и сохраняем ответ в переменную
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $response = json_decode($out, true);
        $access_token = $response['access_token']; //Access токен
        $refresh_token = $response['refresh_token']; //Refresh токен
        if(empty($access_token)){ //если результата нет - выводим ошибку
            print_r($response);
            die("Произошла неизвестная ошибка!");
        }
        else{
            $accesstxt = fopen("access.txt", 'w') or die("не удалось создать файл");
            $refreshtxt = fopen("refresh.txt", 'w') or die("не удалось создать файл");
    
            fwrite($accesstxt, $access_token);
            fwrite($refreshtxt, $refresh_token);
    
            fclose($accesstxt);
            fclose($refreshtxt);
        }
    }

    private function request($link, $p = false,  $body=false){
        $headers = [
            'Authorization: Bearer ' . $this->access
        ];
        $curl = curl_init(); #Сохраняем дескриптор сеанса cURL
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'amoCRM-API-client/1.0');
        curl_setopt($curl, CURLOPT_URL, $link);
        if($p){
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt($curl,CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        $out = curl_exec($curl); 
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $code = (int) $code;

        if(json_decode($out, true)['response']['error']=="Неверный логин или пароль"){
            $this::get_acess();
            //$this::request($link, $p, $body);
        }

        return [$code, json_decode($out, true)];
    }

    public function create_note($id, $text){ //создает текстовое примечание к сделке

        $link = 'https://' . $this->config['client_domen'] . '.amocrm.ru/api/v2/notes';
    
        $data = array (
            'add' =>
            array (0 =>
              array (
                'element_id' => $id,
                'element_type' => '2',
                'text' => $text,
                'note_type' => '4',
              ),
            ),
          );
        return $this::request($link, true, $data);
    }

    public function create_contact($config){

        $contacts['add'] = array(
            array(
                'name' => $config['name'],
                'custom_fields' => array(
                )
            )
        );

        foreach ($config['custom_fields'] as $key => $value){
            $arr = array(
                'id' => $value['id'],
                'values'=> array(
                    array(
                    'value' => $value['value'],
                    'enum' => $value['enum']
                    )
                )
            );
            array_push($contacts['add'][0]['custom_fields'], $arr);
        }   

        //print_r($contacts);
    
        $Response = $this::request('https://' . $this->config['client_domen'] . '.amocrm.ru/api/v2/contacts', true, $contacts);
        return $Response[0]['id'];
    }

    public function create_lead($config){

        $leads['add'] = array(
            array(
                'name'=>$config['name'],
                'sale'=>$config['sale'],
                "contacts_id"=>$config['contacts_id'],
                'custom_fields' => array(
                ),
            ),
        );

        foreach ($config['custom_fields'] as $key => $value){
            $arr = array(
                'id' => $value['id'],
                'values'=> array(
                    array(
                    'value' => $value['value'],
                    )
                )
            );
            array_push($leads['add'][0]['custom_fields'], $arr);
        }
        
        $request = $this::request('https://' . $this->config['client_domen'] . '.amocrm.ru/api/v2/leads', true, $leads);
        return $request['_embedded']['items'][0]['id'];
    }

    public function get_contacts(){ //выводит список всех контактов
        return $this::request('https://' . $this->config['client_domen'] . '.amocrm.ru/api/v2/contacts/');
    }

    public function get_leads(){
        return $this::request('https://' . $this->config['client_domen'] . '.amocrm.ru/api/v2/leads/');
    }
}



/*
$test = new EbClientAmocrm("9ZljQa0jR3fcfVuUv8v3LDpqr3jcV9nErZ6bmw50ssvoiShbiB808iJpSgcd1hIT", 'ea0c7acb-2153-4a5c-94e9-381e1c76b85d', 'kaindowl231', 'https://www.weblancer.net/jobs/', 'def50200c08b1590a1111fc8e61f31990b38558b49f7c309d2e25b2e29204f32cdc364a22cca690cd35f41cef03cadc367729204f6a621d76931b1d01021fa90cdcd6dbad239e646785a774504bb93f28b34fc629e90c7a993a82b5e635e77e012763caf210f5ed6f1c9dcb78a9e8566251a9c6be4131d07f58d9711e92922676e2effb98006a88f65cf6a0fba0f99097a4643bbfcc30b7580501f37c9290b525c8c79de731385b063d37bac37bb8209ced031bbbac6fd5820417c947fb91d1e3fcc54cf80864ac5cd1beeaa36904530e3df74307523973feda542edf8b8194838c7b96055f2bcb1e6347ed30e9afdb817b611e5e2ec6ef96dd9ba3b3fcc6e8f9ddaa713c14cb1fe43649acd2d47460c61c43aac6f5e31626ceed4fccb477d7220cefc4f864d19f2dffad8a1bfb65003de0153a03fef09b2b7194ba5e012f80ac2d96d0f3a7497fab605ce96c4ddbe66fad87523d59d38553fc77a2d414a3052da2478fac4f18fdbd82ee85fdb849dafc4a4e543131497cf14b26a555feaaed10ab166e7fd39c39a8b0c02bc49d1d753dee1c42f54dc2165a76b389a9412b110252cf071ac7c1d67c1bbfc61ece1965140843ebc5959d551f4dbb66761f69cb0d26404038395ac48ea27b4');

$lead_config = array(
    'name' => 'тестовая сделка1!',
    'sale' => '1000',
    'contacts_id' => array(2593951, 2594033),
    'custom_fields' => [
        [
            'id' => 190321,
            'value' => 'значение хаха'
        ]
    ]
);

$test->create_lead($lead_config);
print_r($test->get_leads());


Создание контакта!
$contact_config = array(
    'name' => " РОТ",
    'custom_fields' => [
        [
            'id' => 190199,
            'value' => '+7666666666',
            'enum' => '273619'
        ],
        [
            'id' => 190201,
            'value' => 'fsduisf@gmail.com',
            'enum' => '273631'
        ],
    ]
);
print_r($test->get_contacts());
print_r($test -> create_contact($contact_config));

*/

