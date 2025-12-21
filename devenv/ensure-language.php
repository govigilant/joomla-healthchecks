<?php
$config = $argv[1] ?? '';
$language = $argv[2] ?? 'en-GB';
$metalang = $argv[3] ?? $language;

if ($config === '' || !is_file($config)) {
    exit(0);
}

$contents = file_get_contents($config);

if ($contents === false) {
    exit(0);
}

if (strpos($contents, "public \$language") !== false) {
    exit(0);
}

$replacement = "\tpublic \$language = '" . $language . "';\n\tpublic \$metalang = '" . $metalang . "';\n}\n";

if (preg_match("/\n}\s*$/", $contents)) {
    $contents = preg_replace("/\n}\s*$/", "\n" . $replacement, $contents, 1);
} else {
    $contents .= "\n" . $replacement;
}

file_put_contents($config, $contents);
