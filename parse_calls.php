<?php
/**
 * Находит все вызовы функций в указанном диапазоне токенов
 * 
 * @param string $filepath Путь к файлу
 * @param int $startIndex Начальный индекс токена (включительно)
 * @param int $endIndex Конечный индекс токена (включительно)
 * @return array Массив с информацией о вызовах функций
 */
function findFunctionCallsInRange($filepath, $startIndex, $endIndex) {
    if (!file_exists($filepath)) {
        return ['error' => 'File not found: ' . $filepath];
    }

    $source = file_get_contents($filepath);
    if ($source === false) {
        return ['error' => 'Could not read file: ' . $filepath];
    }
    
    $tokens = token_get_all($source);
    $calls = [];
    
    // Ограничиваем диапазон проверки
    $start = max(0, min($startIndex, $endIndex));
    $end = min(count($tokens) - 1, max($startIndex, $endIndex));
    
    for ($i = $start; $i <= $end; $i++) {
        // Ищем токены, которые могут быть вызовами функций
        if (isset($tokens[$i]) && is_array($tokens[$i])) {
            $token = $tokens[$i];
            
            // Проверяем, является ли токен именем функции (T_STRING)
            // и следующий токен - открывающая скобка
            if ($token[0] === T_STRING) {
                // Проверяем, что это не часть объявления функции (уже отфильтровано диапазоном)
                // и не ключевое слово
                $skipTokens = [
                    T_ECHO, T_PRINT, T_IF, T_ELSE, T_ELSEIF, T_WHILE, 
                    T_FOR, T_FOREACH, T_SWITCH, T_CASE, T_DEFAULT, 
                    T_RETURN, T_BREAK, T_CONTINUE, T_CLASS, T_FUNCTION,
                    T_EXTENDS, T_IMPLEMENTS, T_INTERFACE, T_TRAIT,
                    T_NAMESPACE, T_USE, T_AS, T_CONST, T_VAR,
                    T_PUBLIC, T_PRIVATE, T_PROTECTED, T_STATIC,
                    T_ABSTRACT, T_FINAL, T_NEW, T_CLONE, T_INSTANCEOF
                ];
                
                if (in_array($token[0], $skipTokens)) {
                    continue;
                }
                
                // Проверяем следующий непробельный токен
                $j = $i + 1;
                while (isset($tokens[$j]) && 
                       ((is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) || 
                        (is_array($tokens[$j]) && $tokens[$j][0] === T_COMMENT) ||
                        (is_array($tokens[$j]) && $tokens[$j][0] === T_DOC_COMMENT))) {
                    $j++;
                }
                
                // Если следующий токен - открывающая скобка, это вызов функции
                if (isset($tokens[$j]) && $tokens[$j] === '(') {
                    
                    // Пропускаем имя функции и открывающую скобку
                    $k = $j + 1;
                    $parenCount = 1;
                    
                    // Находим соответствующую закрывающую скобку
                    while (isset($tokens[$k]) && $parenCount > 0) {
                        if ($tokens[$k] === '(') {
                            $parenCount++;
                        } elseif ($tokens[$k] === ')') {
                            $parenCount--;
                        }
                        $k++;
                    }
                    
                    // Извлекаем аргументы функции
                    $argsTokens = array_slice($tokens, $j + 1, $k - $j - 2);
                    $args = '';
                    foreach ($argsTokens as $argToken) {
                        if (is_array($argToken)) {
                            $args .= $argToken[1];
                        } else {
                            $args .= $argToken;
                        }
                    }
                    
                    $calls[] = [
                        'function_name' => $token[1],
                        'name_token_index' => $i,
                        'start_parenthesis_index' => $j,
                        'end_parenthesis_index' => $k - 1,
                        'arguments' => trim($args),
                        'line' => $token[2] // Номер строки
                    ];
                    
                    // Пропускаем обработанные токены
                    $i = $k;
                }
            }
            
            // Проверяем вызовы методов объекта (->)
            if ($token[0] === T_OBJECT_OPERATOR) {
                // Ищем имя метода после ->
                $j = $i + 1;
                while (isset($tokens[$j]) && 
                       ((is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) || 
                        (is_array($tokens[$j]) && $tokens[$j][0] === T_COMMENT) ||
                        (is_array($tokens[$j]) && $tokens[$j][0] === T_DOC_COMMENT))) {
                    $j++;
                }
                
                if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $methodName = $tokens[$j][1];
                    
                    // Проверяем следующий токен после имени метода
                    $k = $j + 1;
                    while (isset($tokens[$k]) && 
                           ((is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) || 
                            (is_array($tokens[$k]) && $tokens[$k][0] === T_COMMENT) ||
                            (is_array($tokens[$k]) && $tokens[$k][0] === T_DOC_COMMENT))) {
                        $k++;
                    }
                    
                    // Если следующий токен - открывающая скобка, это вызов метода
                    if (isset($tokens[$k]) && $tokens[$k] === '(') {
                        
                        // Находим соответствующую закрывающую скобку
                        $l = $k + 1;
                        $parenCount = 1;
                        
                        while (isset($tokens[$l]) && $parenCount > 0) {
                            if ($tokens[$l] === '(') {
                                $parenCount++;
                            } elseif ($tokens[$l] === ')') {
                                $parenCount--;
                            }
                            $l++;
                        }
                        
                        // Извлекаем аргументы метода
                        $argsTokens = array_slice($tokens, $k + 1, $l - $k - 2);
                        $args = '';
                        foreach ($argsTokens as $argToken) {
                            if (is_array($argToken)) {
                                $args .= $argToken[1];
                            } else {
                                $args .= $argToken;
                            }
                        }
                        
                        $calls[] = [
                            'type' => 'method_call',
                            'object_operator_index' => $i,
                            'method_name' => $methodName,
                            'method_token_index' => $j,
                            'start_parenthesis_index' => $k,
                            'end_parenthesis_index' => $l - 1,
                            'arguments' => trim($args),
                            'line' => $token[2]
                        ];
                        
                        $i = $l;
                    }
                }
            }
            
            // Проверяем статические вызовы (::)
            if ($token[0] === T_DOUBLE_COLON) {
                // Ищем имя метода после ::
                $j = $i + 1;
                while (isset($tokens[$j]) && 
                       ((is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) || 
                        (is_array($tokens[$j]) && $tokens[$j][0] === T_COMMENT) ||
                        (is_array($tokens[$j]) && $tokens[$j][0] === T_DOC_COMMENT))) {
                    $j++;
                }
                
                if (isset($tokens[$j]) && is_array($tokens[$j]) && 
                    ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_VARIABLE)) {
                    $methodName = $tokens[$j][1];
                    
                    // Проверяем следующий токен после имени метода
                    $k = $j + 1;
                    while (isset($tokens[$k]) && 
                           ((is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) || 
                            (is_array($tokens[$k]) && $tokens[$k][0] === T_COMMENT) ||
                            (is_array($tokens[$k]) && $tokens[$k][0] === T_DOC_COMMENT))) {
                        $k++;
                    }
                    
                    // Если следующий токен - открывающая скобка, это статический вызов
                    if (isset($tokens[$k]) && $tokens[$k] === '(') {
                        
                        // Находим соответствующую закрывающую скобку
                        $l = $k + 1;
                        $parenCount = 1;
                        
                        while (isset($tokens[$l]) && $parenCount > 0) {
                            if ($tokens[$l] === '(') {
                                $parenCount++;
                            } elseif ($tokens[$l] === ')') {
                                $parenCount--;
                            }
                            $l++;
                        }
                        
                        // Извлекаем аргументы
                        $argsTokens = array_slice($tokens, $k + 1, $l - $k - 2);
                        $args = '';
                        foreach ($argsTokens as $argToken) {
                            if (is_array($argToken)) {
                                $args .= $argToken[1];
                            } else {
                                $args .= $argToken;
                            }
                        }
                        
                        // Находим имя класса слева от ::
                        $className = 'unknown';
                        $classIndex = $i - 1;
                        while (isset($tokens[$classIndex]) && 
                               ((is_array($tokens[$classIndex]) && $tokens[$classIndex][0] === T_WHITESPACE) || 
                                (is_array($tokens[$classIndex]) && $tokens[$classIndex][0] === T_COMMENT) ||
                                (is_array($tokens[$classIndex]) && $tokens[$classIndex][0] === T_DOC_COMMENT))) {
                            $classIndex--;
                        }
                        
                        if (isset($tokens[$classIndex]) && is_array($tokens[$classIndex]) && 
                            ($tokens[$classIndex][0] === T_STRING || $tokens[$classIndex][0] === T_VARIABLE)) {
                            $className = $tokens[$classIndex][1];
                        }
                        
                        $calls[] = [
                            'type' => 'static_call',
                            'class_name' => $className,
                            'class_token_index' => $classIndex,
                            'double_colon_index' => $i,
                            'method_name' => $methodName,
                            'method_token_index' => $j,
                            'start_parenthesis_index' => $k,
                            'end_parenthesis_index' => $l - 1,
                            'arguments' => trim($args),
                            'line' => $token[2]
                        ];
                        
                        $i = $l;
                    }
                }
            }
        }
    }
    
    return $calls;
}



// Если скрипт вызывается напрямую
if (isset($argv[1])) {
    $file = $argv[1];
    
	$callsAnalysis = findFunctionCallsInRange($argv[1], $argv[2], $argv[3]);
	print_r( $callsAnalysis);

    }
?>
