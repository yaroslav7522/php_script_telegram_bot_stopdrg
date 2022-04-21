<?php
require_once(__DIR__.'/req.php');

const TOKEN = 'TE:TOKEN';
const BASE_URL = 'https://api.telegram.org/bot' . TOKEN . '/';


//https://api.telegram.org/bot5024837649:AAExXMSiVeRWLUJqT81ioKbC2CYMjqvaBCM/setWebhook?url=https://globalsoft.in.ua/projects/boots/StopDRGBot/

$update = json_decode(file_get_contents('php://input'));

//file_put_contents(__DIR__ . '/logs.txt', print_r($update,1));

$chat_id = $update->message->chat->id ?? '';
$text = $update->message->text ?? '';
$username = $update->message->chat->username ?? '';
$name = $update->message->chat->first_name .' '. $update->message->chat->last_name ;

//Start bot
if($text == '/start'){
	$result = send_request('sendMessage', [
						'chat_id' => $chat_id,
						'text'	  => 'В даному чаті можна перевірити підозрілу особу, автомобіль, паспорт. По можливості шукати лише по цифровому значенні, або серію вводити кирилицею без пробілів. 
Наприклад:
 - номер авто. АА1234БВ - шукати як "1234" або "аа1234бв"
 - номер паспорта СН292608 - шукати "292608" або "сн292608"
 - Іванов Іван Іванович - шукати по прізвищу "Іванов"',
					]);
//file_put_contents(__DIR__ . '/logs.txt', 'res:'.print_r($result,1));
}elseif(!empty($text)){
	$res = findResult($username, $text, $chat_id, $name);
}


function send_request($method, $params = []){
	if(!empty($params)){
		$url = BASE_URL . $method . '?' . http_build_query($params);
	}else{
		$url = BASE_URL . $method;
	}

	return getSslPage($url);
}

function getSslPage($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

function findResult($username, $text, $chat_id, $name = ''){
	$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	if (mysqli_connect_errno()) {
		echo "Failed to connect to MySQL: " . mysqli_connect_error();
		$result = send_request('sendMessage', ['chat_id' => $chat_id,'text'	  => 'Failed to connect to MySQL']);	  
		return false;
	}	
	mysqli_set_charset( $conn, 'utf8');
	
	$sql = "SELECT text,detail,date FROM information WHERE text LIKE '%".$text."%'";
	if ($result = mysqli_query($conn, $sql)) {
		
		$result_message = "УВАГА!!! Знайдено результати: \n";
		$res_count = 0;
		while ($row = mysqli_fetch_assoc($result)) {
			$res_count++;
			$result_message .= $res_count . ". " . $row["text"] . ' - ' . $row["detail"] . '. Інформація на дату: ' . $row["date"] . "\n";
		}
			
		if($res_count == 0) {
			$result_message = "Збігів не знайдено!";
		}
	}else{
		$result_message = 'Error: ' . mysqli_error($conn);
	}
	
	addRequest($username, $text, $res_count, $chat_id, $name);
	$result = send_request('sendMessage', ['chat_id' => $chat_id,'text'	  => $result_message]);	
}

function addRequest($username, $text, $res_count, $chat_id, $name = '') {
	$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
	mysqli_set_charset( $conn, 'utf8');
	if (mysqli_connect_errno()) {
		echo "Failed to connect to MySQL: " . mysqli_connect_error();
		$result = send_request('sendMessage', ['chat_id' => $chat_id,'text'	  => 'Failed to connect to MySQL']);	  
		return false;
	}
	
	if(!empty($username) && !empty($text)){
		$sql = "INSERT INTO requets (username, request, result, date, name) VALUES ('".$username."', '".$text."', ".$res_count.", '".date('Y-m-d H:i:s')."', '".$name."')";
		if (mysqli_query($conn, $sql)) {
			return true;
		}else{
			return false;
		}
	}
}


