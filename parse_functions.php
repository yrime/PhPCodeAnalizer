<?php
function getFunctionsFromFile($filepath) {
    if (!file_exists($filepath)) {
        die("Файл не найден.");
    }

    // Получаем содержимое файла
    $source = file_get_contents($filepath);
    
    // Разбиваем код на токены
    $tokens = token_get_all($source);
    $functions = [];
    $count = count($tokens);
    
    for ($i = 0; $i < $count; $i++) {
        // Ищем токен "function"
        if ($tokens[$i][0] === T_FUNCTION) {
            // Пропускаем пробелы (следующий токен может быть именем функции)
            $j = $i + 1;
            while (isset($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            // Проверяем, что за функцией идет имя (а не "(" для анонимных функций)
            if (isset($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $functions[] = $tokens[$j][1];
            }
        }
    }
    
    return $functions;
}

// Использование
$file = $argv[1];
$list = getFunctionsFromFile($file);

print_r($list);
?>
