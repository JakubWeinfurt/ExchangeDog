<?php 
//get API from twitter
require('TwitterAPIExchange.php');


//simple money format
function formatMoney($number, $fractional=false) {
    if ($fractional) {
        $number = sprintf('%.2f', $number);
    }
    while (true) {
        $replaced = preg_replace('/(-?\d+)(\d\d\d)/', '$1,$2', $number);
        if ($replaced != $number) {
            $number = $replaced;
        } else {
            break;
        }
    }
    return $number;
}
// Tweet function
function Tweet($currency,$value,$exchange,$amount,$link)
{
    
    $text="Deposit ".$currency.PHP_EOL
    ."Exchange: ".$exchange.PHP_EOL
    ."Token amount: ".formatMoney($amount,2)." ".$currency.PHP_EOL
    ."Value: $".formatMoney($value)." ".PHP_EOL.
    "Link: ".$link." $".$currency." #".$exchange;
    
    
    
    
    
    $settings = array(
        'oauth_access_token' => "twitter_access_token",
        'oauth_access_token_secret' => "twitter_access_token_secret",
        'consumer_key' => "consumer_key",
        'consumer_secret' => "consumer_secret"
    );
    $url = 'https://api.twitter.com/1.1/statuses/update.json';
    $requestMethod = 'POST';
    $postfields = array(
        'status' => $text
    );
    $twitter = new TwitterAPIExchange($settings); //Using TwitterAPIExchange class for twitter connection
    $twitter->buildOauth($url, $requestMethod)
    ->setPostfields($postfields)
    ->performRequest();
    
    return;
    
}
// Get USD price for token this is example for USDC, coingecko
function GetUSDCPrice() {
    $agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
    $gecko_url = 'https://api.coingecko.com/api/v3/coins/usd-coin';//
    $g = curl_init();
    curl_setopt($g, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($g, CURLOPT_VERBOSE, true);
    curl_setopt($g, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($g, CURLOPT_USERAGENT, $agent);
    curl_setopt($g, CURLOPT_ENCODING,  '');
    
    curl_setopt($g, CURLOPT_URL,$gecko_url);
    $gOutput = curl_exec ($g);
    $gData = json_decode($gOutput, true);
    curl_close ($g);    
    return $gData['market_data']['current_price']['usd'];
    
}
//This sends notifications paramets to sendMSG, I have custom SSL so verification is set to false
function ValidateNotification($currency,$value,$exchange,$amount,$message)
{
    $arrContextOptions=array(
        "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),
    );
    // msg_server and PERSONAL_APIKEY
    file_get_contents('https://MSG_SERVER/end/sendMsg.php?apikey=PERSONAL_APIKEY&currency='.$currency.'&value='.$value.'&exchange='.$exchange.'&amount='.$amount.'&msg='.$message,false,stream_context_create($arrContextOptions));
    
}


// Data curl for explorer agent mozzila seems to be working for most 
function CURL_USDC_Address($ex_adress)
{
    $agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $agent);
    curl_setopt($ch, CURLOPT_ENCODING,  '');
    //blockscount doesn't require any API key
    curl_setopt($ch, CURLOPT_URL,'https://blockscout.com/eth/mainnet/api?module=account&action=tokentx&address='.strtolower($ex_adress).'&contractaddress=0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48&page=1&offset=10&sort=desc');
    $server_output = curl_exec ($ch);
    curl_close ($ch);
    
    return json_decode($server_output, true);
   
}

    
    $OSTPRICE = GetUSDCPrice();
    //array of exchange addresses 
    $ex_adresses=array(array("0x3f5ce5fbfe3e9af3971dd833d26ba9b5c936f0be","binance"),array("0x55fe002aeff02f77364de339a1292923a15844b8","Poloniex"),array("0x55fe002aeff02f77364de339a1292923a15844b8","USDC"));
    
    
    // foreach cycle for exchanges
    foreach ($ex_adresses as $ex_c_adress) {
        $array = CURL_USDC_Address($ex_c_adress[0]);
//time now
        $t=time();
        if($array['status']==1){
            
            foreach ($array['result'] as $item) {
 // simple validation for exchanges
                if(strtolower($item['to'])==strtolower($ex_c_adress[0]) && $item['timeStamp']+180 > $t)  {
                    $tran_amount = round($item['value']/pow(10,6),2);
                    $tran_value = round($OSTPRICE*$tran_amount,2);
 // twitter and telegram notifications  
                    if($tran_value >= 300000)
                    {
                        $msg='Incoming deposit to '.$ex_c_adress[1].'  '.round($item['value']/pow(10,6),2)." USDC  ".$tran_value.'$ link here: https://etherscan.io/tx/'.$item['hash'];
            
                        
                        Tweet("USDC",$tran_value,$ex_c_adress[1],$tran_amount,'https://etherscan.io/tx/'.$item['hash'].$addhype);
                        ValidateNotification("USDC",$tran_value,$ex_c_adress[1],$tran_amount,urlencode($msg));
                    }
 // Send only telegram notification
                    else if($tran_value >= 20000) 
                    {
                        $msg='Incoming deposit to '.$ex_c_adress[1].'  '.round($item['value']/pow(10,6),2)." USDC  ".$tran_value.'$ link here: https://etherscan.io/tx/'.$item['hash'];
                        
                        ValidateNotification("USDC",$tran_value,$ex_c_adress[1],$tran_amount,urlencode($msg));
                        
                        
                    }
                   }
                   
                   
           // custom validation for stable coins for monitoring printed token's        
                    else if($item['from']=="0x0000000000000000000000000000000000000000" && $item['timestamp']+180 > $t)
                    {
                        $tran_amount = round($item['value']/pow(10,$item['tokenInfo']['decimals']),2);
                        $tran_value = round($OSTPRICE*$tran_amount,2);
                        $addhype="";
                        $addhype=" #bullish #cryptobullish #printed";
                        if($tran_value >= 500000)
                        {
                            
                            Tweet($item['tokenInfo']['symbol'],$tran_value,"Tokens minted",$tran_amount,'https://etherscan.io/tx/'.$item['transactionHash'].$addhype);
                        }
                    }
            
            }
           
            
            }
        }
        
        
        ?>
 
