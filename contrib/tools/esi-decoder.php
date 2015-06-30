<?php
/**
 * ESI DECODER
 *
 * This tool can be used to decode an ESI request.
 * You can just paste the whole ESI URL and push the button to decode it.
 *
 * Make sure you place this utility on a protected spot on your web server where only authorized users can use it.
 *
 * If the URLs you see in "varnishlog" or "varnishncsa", read this FAQ item:
 * https://github.com/nexcess/magento-turpentine/wiki/FAQ#im-using-varnishncsa-to-generate-logs-and-the-esi-urls-are-cut-off-how-do-i-get-the-full-url-in-the-logs
 *
 */

// You might need to fix the path to your app/Mage.php on the line below.
require_once dirname(__FILE__).'/../../app/Mage.php';

Mage::app();
$data = ( empty($_REQUEST['data']) ) ? '' : $_REQUEST['data'];
header( 'Content-Type:text/html; charset=UTF-8' );
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>ESI Request Decoder</title>
    <style type="text/css">
        body, form, textarea, pre {
            margin: 0;
        }
        body {
            padding: 4px 12px 12px 12px;
            background-color: #EEEEFF;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
        }
        textarea, div.result {
            width: 100%
        }
        textarea, pre {
            background-color: #FFFFFF;
            font-size: 11px;
            font-family: "Lucida Console", Monaco, monospace;
            border: solid 1px #999999;
            padding: 2px;
        }
        textarea {
            height: 100px;
        }
        label.center, div.center {
            display: block;
            text-align: center;
            margin: 4px;
        }
    </style>
</head>
<body>
<form method="post">
    <label for="data" class="center">&darr; &darr;&nbsp; Paste ESI Data or URL &nbsp;&darr; &darr;</label>
    <textarea id="data" name="data"><?php echo $data; ?></textarea><br />
    <div class="center"><input type="submit" value=" &darr; &darr; &darr; &darr;&nbsp; DECODE &nbsp;&darr; &darr; &darr; &darr; " /></div>
</form>
<?php
if ( $data ):
    $processData = $data;
    $esiHelper = Mage::helper( 'turpentine/esi' );
    $dataPreg = preg_quote( $esiHelper->getEsiDataParam(), '|' );
    if ( preg_match('|'.$dataPreg.'/([\w\.\-]+=*)|', $data, $matches) ) {
        $processData = $matches[1];
    }
    $dataHelper = Mage::helper( 'turpentine/data' );
    $esiDataArray = $dataHelper->thaw( $processData );
?>
    <div class="center">=&nbsp; DATA &nbsp;=</div>
    <div class="result">
        <pre><?php echo htmlentities( var_export( $esiDataArray, 1 ) ); ?></pre>
    </div>
<?php
    $refPreg = preg_quote( $esiHelper->getEsiReferrerParam(), '|' );
    if ( preg_match('|'.$refPreg.'/([\w\.\-]+),*|', $data, $matches) ):
        $processData = $matches[1];
?>
    <div class="center">=&nbsp; REFERRER &nbsp;=</div>
    <div class="result">
        <pre><?php echo htmlentities( $dataHelper->urlBase64Decode( $processData ) ); ?></pre>
    </div>
<?php
    endif;
endif;
?>
</body>
</html>
