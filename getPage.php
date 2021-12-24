<?php

class getPage
{
    private $ch ;

    public function __construct (){
        $this->ch = curl_init();
        $headers = array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',

            'Accept-Language: ru,en-US;q=0.9,en;q=0.8',
        );
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_VERBOSE, true);
        $streamVerboseHandle = fopen('php://temp', 'w+');
        curl_setopt($this->ch, CURLOPT_STDERR, $streamVerboseHandle);
        curl_setopt($this->ch, CURLOPT_HEADER, false);

        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
    }
    public function exec($url){
        curl_setopt($this->ch, CURLOPT_URL, $url);

        $result = curl_exec($this->ch);
        return $result;
    }
    public function __destruct(){
        curl_close($this->ch);
    }

}