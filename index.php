<?php

ini_set('display_errors', 0);

include("px.017.php");



function curl($url) {
	$ch = curl_init($url); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	curl_setopt($ch, CURLOPT_HEADER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 10);

	$response = curl_exec($ch);     
	$info = curl_getinfo($ch);     

	$headers = substr($response, 0, $info["header_size"]);

	if (preg_match('/Location: http:\/\/en\.wikipedia\.org\/wiki\/(.*)/', $headers, $matches)) {
		header("Location: http://" . $_SERVER["HTTP_HOST"] . "/" . $matches[1]);
		exit;
	}

	$body = $info["size_download"] ? substr($response, $info["header_size"], $info["size_download"]) : "";
	
	return $body;
}



$page = substr($_SERVER["SCRIPT_URL"], 1);
$search = $_GET["search"];
$about = $_GET["about"];


if ($page) {
	$class = "page";
	
	$response = curl("http://en.wikipedia.org/wiki/" . urlencode($page));
	$px = new px($response);

	$title = $px->xpath("//h1[@id='firstHeading']")->html();
	$titleText = $px->xpath("//h1[@id='firstHeading']")->text();
	$content = $px->xpath("//div[@id='mw-content-text']")->html();
}
else if (isset($about)) {
	$class = "about";
	$content = "
	<h1>What is this?</h1>
	<p>I love Wikipedia. It's awesome. But it deserves a better and more delightful design. This is my vision of how the reading experience should be like. Better typography, removed side bar, reduced clutter, improved contrast and clarity, and more open space.
	<p>This is just a concept. Some features are not supported, and custom formatting on some wikipedia pages might not look right. If you see anything like that, <a href='mailto:moe.salih@gmail.com'>let me know</a>. Or you can contribute to the project on <a href='https://github.com/moesalih/wikipedia.moesalih.com' target='_blank'>GitHub</a>.
	<p>If you like this redesign, please share it with your friends and spread the word. And if you have any feedback or if you just want to say hi, feel free to <a href='mailto:moe.salih@gmail.com'>email me</a>.<br /><br />
	<p>Moe Salih<br />
	<small><a href='http://moesalih.com'>moesalih.com</a></small>
	";

}
else if ($search) {
	$class = "search";

	$response = curl("http://en.wikipedia.org/w/index.php?search=" . urlencode($search));
	$px = new px($response);

	$title = "<h1 id='firstHeading'>Search results</h1>";
	$titleText = $search;
	$content = $px->xpath("//ul[@class='mw-search-results']")->html();
}
else {
	$class = "home";
}

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, minimal-ui">

		<meta name="apple-mobile-web-app-capable" content="yes">
		<meta name="apple-mobile-web-app-status-bar-style" content="black">

		<link rel="shortcut icon" type="image/png" href="images/icon.png" />
		<link rel="apple-touch-icon" href="images/icon.png"/>
		
		<title><?php echo $titleText ? $titleText . " • " : ""; ?>Wikipedia</title>
		
		<link href="css/bootstrap.min.css" rel="stylesheet">
		<link href="css/social-likes_flat.css" rel="stylesheet">
		<link href="css/app.css" rel="stylesheet">
		
<!--
		<link href='http://fonts.googleapis.com/css?family=PT+Serif:400,700,400italic,700italic' rel='stylesheet' type='text/css'>
		<link href='http://fonts.googleapis.com/css?family=Droid+Serif:400,700,400italic,700italic' rel='stylesheet' type='text/css'>
		<link href='http://fonts.googleapis.com/css?family=Lora:400,700,400italic,700italic' rel='stylesheet' type='text/css'>
-->
		<link href='http://fonts.googleapis.com/css?family=Merriweather:400,300,700,300italic,700italic,400italic' rel='stylesheet' type='text/css'>
		
		<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
		<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
		<!--[if lt IE 9]>
		<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
		<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
		<![endif]-->
	</head>
	<body class="<?php echo $class; ?>">
	
	
		<div class="container">
		
			<header>Wikipedia</header>
		
			<div id="search">
				<form action=".">
					<input name="search" type="text" placeholder="Search" value="<?php echo $search; ?>" autocomplete="off" autofocus="autofocus" />
				</form>
			</div>
		
			<main>
				<?php echo $title; ?>
				<?php echo $content; ?>
			</main>
		
			<div id="social">			
				<div class="social-likes" data-url="http://wikipedia.moesalih.com">
					<div class="facebook" title="Share link on Facebook">Facebook</div>
					<div class="twitter" title="Share link on Twitter">Twitter</div>
					<div class="plusone" title="Share link on Google+">Google+</div>
				</div>
			</div>
			
			<div id="whatisthis">
				<a href="?about">What is this?</a>
			</div>


		</div>
		
		
		<script src="js/jquery-1.11.0.min.js"></script>
		<script src="js/bootstrap.min.js"></script>
		<script src="js/social-likes.min.js"></script>
		<script src="js/app.js"></script>
		<script>
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
			
			ga('create', 'UA-33281840-3', 'moesalih.com');
			ga('send', 'pageview');
		</script>
	</body>
</html>
