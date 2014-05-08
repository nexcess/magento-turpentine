<?php
// You might need to fix the path to your app/Mage.php on the line below.
require_once dirname(__FILE__).'/../app/Mage.php';

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
            font-family: arial, helvetica, sans-serif;
            font-size: 12px;
        }
        textarea, div.result {
            width: 100%
        }
        textarea, pre {
            background-color: #FFFFFF;
            font-size: 11px; font-family: monospace, "courier new", courier;
            border: solid 1px #999999;
            padding: 2px;
        }
        textarea {
            height: 100px;
        }
        div.center {
            text-align: center;
            margin: 4px;
        }
    </style>
</head>
<body>
<form method="post">
    <div class="center">&darr; &darr;&nbsp; Paste ESI Data or URL &nbsp;&darr; &darr;</div>
    <textarea name="data"><?php echo $data; ?></textarea><br />
    <div class="center"><input type="submit" value=" &darr; &darr; &darr; &darr;&nbsp; DECODE &nbsp;&darr; &darr; &darr; &darr; " /></div>
</form>
<?php
if ( $data ):
    $processData = $data;
    if ( preg_match('|data/([\w\.\-]+=*)|', $data, $matches) ) {
        $processData = $matches[1];
    }
    $dataHelper = Mage::helper( 'turpentine/data' );
    $esiDataArray = $dataHelper->thaw( $processData );
?>
    <div class="result">
        <pre><?php echo var_export( $esiDataArray ); ?></pre>
    </div>
<?php
endif;
?>
</body>
</html>