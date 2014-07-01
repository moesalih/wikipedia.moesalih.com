<?php
 
$post_data = file_get_contents("php://input");

$header[] = "Content-type: application/x-www-form-urlencoded";
$header[] = "Content-length: " . strlen($post_data);

$ch = curl_init( $_GET['url'] ); 
//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_VERBOSE, 1);
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

if ( strlen($post_data)>0 ){
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
}


function curl_exec_follow(/*resource*/ $ch, /*int*/ &$maxredirect = null) {
    $mr = $maxredirect === null ? 5 : intval($maxredirect);
    if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $mr > 0);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $mr);
    } else {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        if ($mr > 0) {
            $newurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

            $rch = curl_copy_handle($ch);
            curl_setopt($rch, CURLOPT_HEADER, true);
            curl_setopt($rch, CURLOPT_NOBODY, true);
            curl_setopt($rch, CURLOPT_FORBID_REUSE, false);
            curl_setopt($rch, CURLOPT_RETURNTRANSFER, true);
            do {
                curl_setopt($rch, CURLOPT_URL, $newurl);
                $header = curl_exec($rch);
                if (curl_errno($rch)) {
                    $code = 0;
                } else {
                    $code = curl_getinfo($rch, CURLINFO_HTTP_CODE);
                    if ($code == 301 || $code == 302) {
                        preg_match('/Location:(.*?)\n/', $header, $matches);
                        $newurl = trim(array_pop($matches));
                    } else {
                        $code = 0;
                    }
                }
            } while ($code && --$mr);
            curl_close($rch);
            if (!$mr) {
                if ($maxredirect === null) {
                    trigger_error('Too many redirects. When following redirects, libcurl hit the maximum amount.', E_USER_WARNING);
                } else {
                    $maxredirect = 0;
                }
                return false;
            }
            curl_setopt($ch, CURLOPT_URL, $newurl);
        }
    }
    return curl_exec($ch);
} 



$response = curl_exec_follow($ch);     
$info = curl_getinfo($ch);     
$newurl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

if (curl_errno($ch)) {
    print curl_error($ch);
} else {
    curl_close($ch);

    $body = $info["size_download"] ? substr($response, $info["header_size"], $info["size_download"]) : "";
    $headers = explode("\n", substr($response, 0, $info["header_size"]));



    // headers to strip
    $strip = array("Transfer-Encoding");
        
     // process response headers
     foreach ($headers as &$header)
     {
         // skip empty headers
         if (!$header) continue;
         
         // get header key
         $pos = strpos($header, ":");
         $key = substr($header, 0, $pos);
         
         
         // set headers
         if (!in_array($key, $strip))
         {
             header($header);
         }
     }

    
    header('Access-Control-Allow-Origin: *');
    header('Content-type: ' . $info['content_type']);
    header('X-Location: ' . $newurl);
    
    print $body;
}

?>