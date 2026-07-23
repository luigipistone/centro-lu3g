<?php

$path = $argv[1] ?? null;
if (! $path || ! is_file($path)) {
    fwrite(STDERR, "wp-config.php non trovato.\n");
    exit(1);
}

$contents = file_get_contents($path);
$line = 'set_time_limit(300);';
if (! str_contains($contents, $line)) {
    $marker = "/* That's all, stop editing!";
    $position = strpos($contents, $marker);
    if ($position === false) {
        $contents .= PHP_EOL.$line.PHP_EOL;
    } else {
        $contents = substr($contents, 0, $position).$line.PHP_EOL.PHP_EOL.substr($contents, $position);
    }
    file_put_contents($path, $contents);
}
