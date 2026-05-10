<?php

$apiKey = "6M527A4NS4Z8Z4KPXZFON5D8I1K9X8WOQL9CFRO2JRKKSZDLFSJAU7YR1VJWC9GRRY32O8SUGPDEXZTO";
$url = "https://www.naukri.com/software-developer-jobs-in-india";

$scrapingbeeUrl = "https://app.scrapingbee.com/api/v1/?" . http_build_query([
    "api_key" => $apiKey,
    "url" => $url,
    "render_js" => "true",
    "premium_proxy" => "true"
]);

echo file_get_contents($scrapingbeeUrl);

?>