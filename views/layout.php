<?php
function startDB($k){ ob_start(); $GLOBALS['_v'][$k]=''; }
function endDB(){ $buf=ob_get_clean(); $GLOBALS['_v']['content']=$buf; template(); }
function template(){ ?><!doctype html><html><head><meta charset="utf-8"><title>Memora</title></head>
<body><?= $GLOBALS['_v']['content'] ?></body></html><?php }
