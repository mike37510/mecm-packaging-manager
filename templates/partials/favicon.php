<?php

use App\Support\View;

$brandV = (string) @filemtime(dirname(__DIR__, 2) . '/public/assets/brand/icon.png');
?>
<link rel="icon" href="/favicon.ico?v=<?= View::e($brandV) ?>" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/brand/favicon-32.png?v=<?= View::e($brandV) ?>">
<link rel="apple-touch-icon" href="/assets/brand/apple-touch-icon.png?v=<?= View::e($brandV) ?>">
