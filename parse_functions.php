<?php
function getFunctionsFromFile($filepath) {
    if (!file_exists($filepath)) {
        die(json_encode(['error' => 'File not found: ' . $filepath]));
    }

    $source = file_get_contents($filepath);
    if ($source === false) {
        die(json_encode(['error' => 'Could not read file: ' . $filepath]));
    }
    
    $tokens = token_get_all($source);
    $functions = [];
    $count = count($tokens);
    
    for ($i = 0; $i < $count; $i++) {
        if ($tokens[$i][0] === T_FUNCTION) {
            
            // Пропускаем пробелы после T_FUNCTION
            $j = $i + 1;
            while (isset($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            
            // Проверяем, что за функцией идет имя (а не "(" для анонимных функций)
            if (isset($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $functionName = $tokens[$j][1];
                $nameTokenIndex = $j; // Индекс токена с именем функции
                
                // Ищем открывающую скобку параметров
                $k = $j + 1;
                while (isset($tokens[$k]) && $tokens[$k] !== '(') {
                    $k++;
                }
                
                if (isset($tokens[$k]) && $tokens[$k] === '(') {
                    // Ищем соответствующую закрывающую скобку параметров
                    $parenCount = 1;
                    $l = $k + 1;
                    
                    while (isset($tokens[$l]) && $parenCount > 0) {
                        if ($tokens[$l] === '(') {
                            $parenCount++;
                        } elseif ($tokens[$l] === ')') {
                            $parenCount--;
                        }
                        $l++;
                    }
                    
                    // Теперь ищем открывающую фигурную скобку тела функции
                    while (isset($tokens[$l]) && $tokens[$l] !== '{') {
                        // Пропускаем пробелы и другие токены
                        $l++;
                    }
                    
                    if (isset($tokens[$l]) && $tokens[$l] === '{') {
                        // Ищем соответствующую закрывающую фигурную скобку
                        $braceCount = 1;
                        $m = $l + 1;
                        $startPos = $l; // Индекс открывающей скобки
                        
                        while (isset($tokens[$m]) && $braceCount > 0) {
                            if ($tokens[$m] === '{') {
                                $braceCount++;
                            } elseif ($tokens[$m] === '}') {
                                $braceCount--;
                            }
                            $m++;
                        }
                        
                        // Сохраняем индексы начала и конца тела функции
                        // Тело функции находится между открывающей { и закрывающей } скобками
                        $functions[] = [
                            'name' => $functionName,
                            'name_token_index' => $nameTokenIndex,
                            'body_start_index' => $startPos + 1, // Индекс первого токена после {
                            'body_end_index' => $m - 2, // Индекс последнего токена перед }
                            'body_start_brace_index' => $startPos, // Индекс токена {
                            'body_end_brace_index' => $m - 1 // Индекс токена }
                        ];
                    }
                }
            }
        }
    }
    
    return $functions;
}

if ($argc < 2) {
    die("Usage: php " . $argv[0] . " <file.php>\n");
}

$file = $argv[1];
$list = getFunctionsFromFile($file);
/*
foreach ($list as $function) {
    echo "Function: " . $function['name'] . "\n";
    echo "Body:\n" . $function['body'] . "\n";
    echo str_repeat("-", 50) . "\n";
}
*/
$jsonResult = json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
print_r($jsonResult);
?>
