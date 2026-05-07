<?
// ?utm_source=yandex&utm_medium=2&utm_campaign=3&utm_content=4
if (isset($_GET['utm_source'])) {
	$cookieTime = time() + 60*60*24*7;

	setcookie("utm_source", $_GET['utm_source'], $cookieTime, "/");
	setcookie("utm_medium", $_GET['utm_medium'], $cookieTime, "/");
	setcookie("utm_campaign", $_GET['utm_campaign'], $cookieTime, "/");
	setcookie("utm_content", $_GET['utm_content'], $cookieTime, "/");
}

echo "<pre>";
print_r($_COOKIE['utm_source']);
echo "</pre>";
echo "<pre>";
print_r($_COOKIE['utm_medium']);
echo "</pre>";
echo "<pre>";
print_r($_COOKIE['utm_campaign']);
echo "</pre>";
echo "<pre>";
print_r($_COOKIE['utm_content']);
echo "</pre>";