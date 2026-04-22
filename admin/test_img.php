<?php
define('IS_INCLUDED', true);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';
echo "uploadUrl(test): " . uploadUrl('umum/test.png') . "\n";
echo "assetUrl(test): " . assetUrl('uploads/umum/test.png') . "\n";
echo "imgTag(test): " . imgTag('umum/test.png') . "\n";
