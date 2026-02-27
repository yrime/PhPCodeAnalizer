<?php

/**
 * PHP File Analyzer
 * Анализирует структуру PHP файлов, собирает статистику, метрики и информацию о классах
 * 
 * @author AI Assistant
 * @version 2.1
 */

class PHPFileAnalyzer
{
    private $filePath;
    private $content;
    private $tokens;
    private $errors = [];
    private $warnings = [];
    private $statistics = [
        'lines' => 0,
        'functions' => 0,
        'classes' => 0,
        'methods' => 0,
        'variables' => 0,
        'constants' => 0,
        'interfaces' => 0,
        'traits' => 0,
        'namespaces' => 0,
        'includes' => 0,
        'comments' => 0,
        'empty_lines' => 0,
        'abstract_classes' => 0,
        'final_classes' => 0,
        'abstract_methods' => 0,
        'final_methods' => 0,
        'static_methods' => 0,
        'total_functions' => 0,
        'total_methods' => 0
    ];
    
    private $detailedInfo = [
        'functions' => [],
        'methods' => [],
        'classes' => [],
        'interfaces' => [],
        'traits' => [],
        'constants' => [],
        'variables' => [],
        'includes' => [],
        'namespaces' => [],
        'comments' => [],
        'uses' => []
    ];
    
    private $tempProperties = [];
    private $tempConstants = [];
    private $currentClassType = null;

    public function __construct($filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("Файл не найден: $filePath");
        }
        $this->filePath = $filePath;
        $this->content = file_get_contents($filePath);
    }

    /**
     * Запуск полного анализа
     */
    public function analyze()
    {
        $this->checkSyntax();
        $this->tokenize();
        $this->analyzeStructure();
        $this->calculateStatistics();
        
        return [
            'file_info' => $this->getFileInfo(),
            'statistics' => $this->statistics,
            'detailed' => $this->detailedInfo,
            'structure' => $this->getStructure(),
            'metrics' => $this->calculateMetrics(),
            'issues' => [
                'errors' => $this->errors,
                'warnings' => $this->warnings
            ]
        ];
    }

    /**
     * Информация о файле
     */
    private function getFileInfo()
    {
        return [
            'path' => $this->filePath,
            'name' => basename($this->filePath),
            'size' => filesize($this->filePath),
            'size_formatted' => $this->formatBytes(filesize($this->filePath)),
            'modified' => date('Y-m-d H:i:s', filemtime($this->filePath)),
            'created' => date('Y-m-d H:i:s', filectime($this->filePath)),
            'permissions' => substr(sprintf('%o', fileperms($this->filePath)), -4),
            'extension' => pathinfo($this->filePath, PATHINFO_EXTENSION)
        ];
    }

    /**
     * Проверка синтаксиса
     */
    private function checkSyntax()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'php_analyzer_');
        file_put_contents($tempFile, $this->content);
        
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->errors[] = [
                'type' => 'syntax_error',
                'message' => implode("\n", $output)
            ];
        }
        
        unlink($tempFile);
    }

    /**
     * Токенизация кода
     */
    private function tokenize()
    {
        $this->tokens = token_get_all($this->content);
    }

    /**
     * Детальный анализ структуры
     */
    private function analyzeStructure()
    {
        $currentClass = null;
        $currentNamespace = '';
        $inClass = false;
        $braceLevel = 0;
        $classBraceLevel = 0;
        $classModifiers = [];
        $classExtends = null;
        $classImplements = [];
        $classUses = [];
        $classStartLine = 0;
        
        // Временные массивы
        $this->tempProperties = [];
        $this->tempConstants = [];
        
        // Собираем use statements
        $this->collectUseStatements();
        
        for ($i = 0; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];
            
            if (!is_array($token)) {
                // Отслеживаем уровень вложенности
                if ($token === '{') {
                    $braceLevel++;
                    if ($inClass && $classBraceLevel === 0) {
                        $classBraceLevel = $braceLevel;
                    }
                } elseif ($token === '}') {
                    $braceLevel--;
                    if ($inClass && $braceLevel < $classBraceLevel) {
                        // Выходим из класса
                        if ($currentClass) {
                            $this->finalizeClass($currentClass, $currentNamespace, $classModifiers, 
                                                $classExtends, $classImplements, $classUses, $classStartLine);
                        }
                        $inClass = false;
                        $currentClass = null;
                        $this->currentClassType = null;
                        $classBraceLevel = 0;
                        $classModifiers = [];
                        $classExtends = null;
                        $classImplements = [];
                        $classUses = [];
                        $classStartLine = 0;
                    }
                }
                continue;
            }

            switch ($token[0]) {
                case T_NAMESPACE:
                    // Настоящий namespace где объявлен класс
                    $namespace = $this->extractNamespace($i);
                    $currentNamespace = $namespace;
                    $this->detailedInfo['namespaces'][$namespace] = [
                        'name' => $namespace,
                        'line' => $token[2]
                    ];
                    $this->statistics['namespaces']++;
                    break;

                case T_ABSTRACT:
                case T_FINAL:
                    // Модификаторы класса
                    if (!$inClass) {
                        $modifier = $token[0] === T_ABSTRACT ? 'abstract' : 'final';
                        $classModifiers[] = $modifier;
                    }
                    break;

                case T_CLASS:
                case T_INTERFACE:
                case T_TRAIT:
                    $className = $this->extractName($i);
                    $currentClass = $className;
                    $inClass = true;
                    $classBraceLevel = $braceLevel + 1;
                    $classStartLine = $token[2];
                    
                    $type = token_name($token[0]);
                    $this->currentClassType = strtolower(str_replace('T_', '', $type));
                    
                    // Статистика по типам
                    if ($token[0] === T_CLASS) {
                        $this->statistics['classes']++;
                        if (in_array('abstract', $classModifiers)) {
                            $this->statistics['abstract_classes']++;
                        }
                        if (in_array('final', $classModifiers)) {
                            $this->statistics['final_classes']++;
                        }
                    } elseif ($token[0] === T_INTERFACE) {
                        $this->statistics['interfaces']++;
                    } elseif ($token[0] === T_TRAIT) {
                        $this->statistics['traits']++;
                    }
                  
                    // Ищем extends и implements
                    $classExtends = $this->findExtends($i);
                    $classImplements = $this->findImplements($i);

                    break;

                case T_EXTENDS:
                    // Дополнительная проверка extends
                    if ($inClass && !$classExtends) {
                        $classExtends = $this->extractExtendsFromToken($i);
                    }
                    break;

                case T_IMPLEMENTS:
                    // Дополнительная проверка implements
                    if ($inClass && empty($classImplements)) {
                        $classImplements = $this->extractImplementsFromToken($i);
                    }
                    break;

                case T_USE:
                    if ($inClass) {
                        // Use trait внутри класса
                        $traitInfo = $this->extractTraitInfo($i);
                        if ($traitInfo) {
                            $classUses[] = $traitInfo;
                        }
                    }
                    break;

                case T_FUNCTION:
                    $functionName = $this->extractName($i);
                    $params = $this->extractParameters($i);
                    $returnType = $this->extractReturnType($i);
                    
                    // Модификаторы метода
                    $methodModifiers = $this->getMethodModifiers($i);
                    
                    if ($inClass && $currentClass) {
                        // Метод класса
                        $visibility = $this->getVisibility($i);
                        
                        $methodInfo = [
                            'name' => $functionName,
                            'class' => $currentClass,
                            'line' => $token[2],
                            'params' => $params,
                            'return_type' => $returnType,
                            'visibility' => $visibility,
                            'static' => in_array('static', $methodModifiers),
                            'abstract' => in_array('abstract', $methodModifiers),
                            'final' => in_array('final', $methodModifiers),
                            'docblock' => $this->getDocBlock($i)
                        ];
                        
                        $this->detailedInfo['methods'][] = $methodInfo;
                        
                        // Статистика методов
                        if ($methodInfo['abstract']) {
                            $this->statistics['abstract_methods']++;
                        }
                        if ($methodInfo['final']) {
                            $this->statistics['final_methods']++;
                        }
                        if ($methodInfo['static']) {
                            $this->statistics['static_methods']++;
                        }
                        
                        $this->statistics['methods']++;
                    } else if (!$inClass) {
                        // Глобальная функция
                        $functionInfo = [
                            'name' => $functionName,
                            'namespace' => $currentNamespace,
                            'line' => $token[2],
                            'params' => $params,
                            'return_type' => $returnType,
                            'docblock' => $this->getDocBlock($i)
                        ];
                        
                        $this->detailedInfo['functions'][$functionName] = $functionInfo;
                        $this->statistics['functions']++;
                    }
                    break;

                case T_VARIABLE:
                    if ($inClass && $currentClass && !$this->isInsideMethod($i)) {
                        // Свойство класса
                        $propertyInfo = $this->analyzeProperty($i, $currentClass);
                        if ($propertyInfo) {
                            if (!isset($this->tempProperties[$currentClass])) {
                                $this->tempProperties[$currentClass] = [];
                            }
                            
                            // Проверка дубликатов
                            $found = false;
                            foreach ($this->tempProperties[$currentClass] as $existing) {
                                if ($existing['name'] === $propertyInfo['name']) {
                                    $found = true;
                                    break;
                                }
                            }
                            
                            if (!$found) {
                                $this->tempProperties[$currentClass][] = $propertyInfo;
                            }
                        }
                    }
                    $this->statistics['variables']++;
                    break;

                case T_CONST:
                    if ($inClass && $currentClass) {
                        // Константа класса
                        $constName = $this->extractName($i);
                        $constValue = $this->extractConstantValueSimple($i);
                        
                        if (!isset($this->tempConstants[$currentClass])) {
                            $this->tempConstants[$currentClass] = [];
                        }
                        
                        $this->tempConstants[$currentClass][] = [
                            'name' => $constName,
                            'value' => $constValue,
                            'line' => $token[2],
                            'visibility' => $this->getVisibility($i) ?: 'public'
                        ];
                    }
                    break;

                case T_STRING:
                    if ($token[1] === 'define') {
                        // Глобальная константа
                        $constName = $this->extractConstantName($i);
                        $constValue = $this->extractConstantValue($i);
                        $this->detailedInfo['constants'][] = [
                            'name' => $constName,
                            'value' => $constValue,
                            'line' => $token[2],
                            'namespace' => $currentNamespace
                        ];
                        $this->statistics['constants']++;
                    }
                    break;

                case T_INCLUDE:
                case T_INCLUDE_ONCE:
                case T_REQUIRE:
                case T_REQUIRE_ONCE:
                    $includedFile = $this->extractInclude($i);
                    if ($includedFile) {
                        $this->detailedInfo['includes'][] = [
                            'type' => token_name($token[0]),
                            'file' => $includedFile,
                            'line' => $token[2]
                        ];
                        $this->statistics['includes']++;
                    }
                    break;

                case T_COMMENT:
                case T_DOC_COMMENT:
                    $commentType = $token[0] === T_DOC_COMMENT ? 'docblock' : 'comment';
                    $this->detailedInfo['comments'][] = [
                        'type' => $commentType,
                        'content' => trim($token[1]),
                        'line' => $token[2]
                    ];
                    $this->statistics['comments']++;
                    $this->analyzeComment($token[1], $token[2]);
                    break;
            }
        }
        
        // Сохраняем последний класс если был
        if ($currentClass) {
            $this->finalizeClass($currentClass, $currentNamespace, $classModifiers, 
                                $classExtends, $classImplements, $classUses, $classStartLine);
        }
        
        $this->statistics['total_functions'] = $this->statistics['functions'] + $this->statistics['methods'];
    }

    /**
     * Извлечение extends из текущей позиции
     */
    private function extractExtendsFromToken($index)
    {
        $i = $index + 1;
        
        // Пропускаем пробелы
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        // Собираем имя класса
        $extends = '';
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && 
               ($this->tokens[$i][0] === T_STRING || $this->tokens[$i][0] === T_NS_SEPARATOR)) {
            $extends .= $this->tokens[$i][1];
            $i++;
        }
        
        return $extends ?: null;
    }

    /**
     * Извлечение implements из текущей позиции
     */
    private function extractImplementsFromToken($index)
    {
        $implements = [];
        $i = $index + 1;
        $currentInterface = '';
        
        while (isset($this->tokens[$i]) && $this->tokens[$i] !== '{') {
            if (is_array($this->tokens[$i])) {
                if ($this->tokens[$i][0] === T_STRING || $this->tokens[$i][0] === T_NS_SEPARATOR) {
                    $currentInterface .= $this->tokens[$i][1];
                } elseif ($this->tokens[$i][0] === T_WHITESPACE) {
                    if (!empty($currentInterface)) {
                        $implements[] = $currentInterface;
                        $currentInterface = '';
                    }
                }
            } else {
                if ($this->tokens[$i] === ',') {
                    if (!empty($currentInterface)) {
                        $implements[] = $currentInterface;
                        $currentInterface = '';
                    }
                }
            }
            $i++;
        }
        
        if (!empty($currentInterface)) {
            $implements[] = $currentInterface;
        }
        
        return $implements;
    }

    /**
     * Финализация класса - с гарантированным сохранением extends
     */
    private function finalizeClass($className, $namespace, $modifiers, $extends, $implements, $uses, $line)
    {
        // Определяем тип класса
        $type = 'T_CLASS';
        if ($this->currentClassType) {
            if ($this->currentClassType === 'interface') $type = 'T_INTERFACE';
            if ($this->currentClassType === 'trait') $type = 'T_TRAIT';
        }
        
        // Убеждаемся, что extends не пустой
        $finalExtends = $extends;
        
        $classInfo = [
            'name' => $className,
            'namespace' => $namespace,           // Где объявлен класс
            'type' => $type,
            'line' => $line,
            'modifiers' => $modifiers,
            'extends' => $finalExtends,          // От кого наследуется - гарантированно сохраняем
            'implements' => $implements,
            'uses' => $uses,
            'properties' => $this->tempProperties[$className] ?? [],
            'constants' => $this->tempConstants[$className] ?? [],
            'methods' => [],
            'abstract' => in_array('abstract', $modifiers),
            'final' => in_array('final', $modifiers)
        ];
        
        // Добавляем методы этого класса
        foreach ($this->detailedInfo['methods'] as $method) {
            if ($method['class'] === $className) {
                $classInfo['methods'][] = $method;
            }
        }
        
        // Сохраняем в соответствующую секцию
        if ($type === 'T_INTERFACE') {
            $this->detailedInfo['interfaces'][$className] = $classInfo;
        } elseif ($type === 'T_TRAIT') {
            $this->detailedInfo['traits'][$className] = $classInfo;
        } else {
            $this->detailedInfo['classes'][$className] = $classInfo;
        }
        
        // Очищаем временные данные
        unset($this->tempProperties[$className]);
        unset($this->tempConstants[$className]);
    }

    /**
     * Сбор use statements
     */
    private function collectUseStatements()
    {
        $inUse = false;
        $currentUse = '';
        
        for ($i = 0; $i < count($this->tokens); $i++) {
            $token = $this->tokens[$i];
            
            if (!is_array($token)) {
                if ($token === ';' && $inUse) {
                    $useInfo = $this->parseUseStatement($currentUse);
                    if ($useInfo) {
                        $this->detailedInfo['uses'][] = $useInfo;
                    }
                    $inUse = false;
                    $currentUse = '';
                } elseif ($token === ',' && $inUse) {
                    $useInfo = $this->parseUseStatement($currentUse);
                    if ($useInfo) {
                        $this->detailedInfo['uses'][] = $useInfo;
                    }
                    $currentUse = '';
                } elseif ($token === '{' && $inUse) {
                    $this->parseGroupUseStatement($i, $currentUse);
                    $inUse = false;
                    $currentUse = '';
                    while ($i < count($this->tokens) && $this->tokens[$i] !== '}') {
                        $i++;
                    }
                }
                continue;
            }
            
            if ($token[0] === T_USE && !$this->isInsideClass($i)) {
                $inUse = true;
                $currentUse = '';
            } elseif ($inUse) {
                if ($token[0] !== T_WHITESPACE) {
                    if ($token[0] === T_AS) {
                        $currentUse .= ' as ';
                    } elseif ($token[0] === T_STRING) {
                        $currentUse .= $token[1];
                    } elseif ($token[0] === T_NS_SEPARATOR) {
                        $currentUse .= '\\';
                    } else {
                        $currentUse .= $token[1];
                    }
                }
            }
        }
    }

    /**
     * Парсинг use statement
     */
    private function parseUseStatement($useString)
    {
        $useString = trim($useString);
        if (empty($useString)) {
            return null;
        }
        
        $result = [
            'full' => $useString,
            'class' => null,
            'alias' => null
        ];
        
        if (strpos($useString, ' as ') !== false) {
            $parts = explode(' as ', $useString);
            $result['class'] = trim($parts[0]);
            $result['alias'] = trim($parts[1]);
        } else {
            $result['class'] = $useString;
            if (strpos($useString, '\\') !== false) {
                $parts = explode('\\', $useString);
                $result['alias'] = end($parts);
            } else {
                $result['alias'] = $useString;
            }
        }
        
        return $result;
    }

    /**
     * Парсинг группового use statement
     */
    private function parseGroupUseStatement($startIndex, $prefix)
    {
        $i = $startIndex + 1;
        $current = '';
        
        while (isset($this->tokens[$i]) && $this->tokens[$i] !== '}') {
            $token = $this->tokens[$i];
            
            if (!is_array($token)) {
                if ($token === ',') {
                    if (!empty($current)) {
                        $fullUse = $prefix . $current;
                        $useInfo = $this->parseUseStatement($fullUse);
                        if ($useInfo) {
                            $this->detailedInfo['uses'][] = $useInfo;
                        }
                        $current = '';
                    }
                } elseif ($token === '}') {
                    break;
                }
            } else {
                if ($token[0] !== T_WHITESPACE) {
                    if ($token[0] === T_AS) {
                        $current .= ' as ';
                    } else {
                        $current .= $token[1];
                    }
                }
            }
            $i++;
        }
        
        if (!empty($current)) {
            $fullUse = $prefix . $current;
            $useInfo = $this->parseUseStatement($fullUse);
            if ($useInfo) {
                $this->detailedInfo['uses'][] = $useInfo;
            }
        }
    }

    /**
     * Извлечение информации о трейте
     */
    private function extractTraitInfo($index)
    {
        $i = $index + 1;
        $traitName = '';
        $aliases = [];
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && 
               ($this->tokens[$i][0] === T_STRING || $this->tokens[$i][0] === T_NS_SEPARATOR)) {
            $traitName .= $this->tokens[$i][1];
            $i++;
        }
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && $this->tokens[$i] === '{') {
            $i++;
            $currentAlias = '';
            
            while (isset($this->tokens[$i]) && $this->tokens[$i] !== '}') {
                if (!is_array($this->tokens[$i])) {
                    if ($this->tokens[$i] === ';') {
                        if (!empty($currentAlias)) {
                            $aliases[] = trim($currentAlias);
                            $currentAlias = '';
                        }
                    }
                } else {
                    if ($this->tokens[$i][0] !== T_WHITESPACE) {
                        $currentAlias .= $this->tokens[$i][1] . ' ';
                    }
                }
                $i++;
            }
            
            if (!empty($currentAlias)) {
                $aliases[] = trim($currentAlias);
            }
        }
        
        return [
            'name' => $traitName,
            'aliases' => $aliases
        ];
    }

    /**
     * Получение модификаторов метода
     */
    private function getMethodModifiers($index)
    {
        $modifiers = [];
        
        for ($i = $index - 1; $i >= max(0, $index - 20); $i--) {
            if (!isset($this->tokens[$i])) continue;
            
            if (!is_array($this->tokens[$i])) {
                if ($this->tokens[$i] === '{' || $this->tokens[$i] === ';') {
                    break;
                }
                continue;
            }
            
            if ($this->tokens[$i][0] === T_PUBLIC || 
                $this->tokens[$i][0] === T_PRIVATE || 
                $this->tokens[$i][0] === T_PROTECTED) {
                continue;
            }
            
            if ($this->tokens[$i][0] === T_STATIC) {
                $modifiers[] = 'static';
            }
            if ($this->tokens[$i][0] === T_ABSTRACT) {
                $modifiers[] = 'abstract';
            }
            if ($this->tokens[$i][0] === T_FINAL) {
                $modifiers[] = 'final';
            }
        }
        
        return $modifiers;
    }

    /**
     * Поиск extends
     */
private function findExtends($index)
{
    // Открываем файл для отладки
    $debug = fopen('debug_findextends.log', 'a');
    fwrite($debug, "\n" . date('Y-m-d H:i:s') . " - Поиск extends для позиции $index\n");
    
    $classToken = $this->tokens[$index];
    if (is_array($classToken)) {
        fwrite($debug, "Токен класса: " . token_name($classToken[0]) . " = '" . $classToken[1] . "'\n");
    }
    
    // Ищем T_EXTENDS после объявления класса
    for ($i = $index + 1; $i < count($this->tokens); $i++) {
        if (!isset($this->tokens[$i])) continue;
        
        // Если нашли открывающую скобку класса - дальше не ищем
        if (!is_array($this->tokens[$i]) && $this->tokens[$i] === '{') {
            fwrite($debug, "Дошли до открывающей скобки { на позиции $i, extends не найден\n");
            break;
        }
        
        if (is_array($this->tokens[$i])) {
            $tokenName = token_name($this->tokens[$i][0]);
            fwrite($debug, "  Токен $i: $tokenName = '" . $this->tokens[$i][1] . "'\n");
            
            if ($this->tokens[$i][0] === T_EXTENDS) {
                fwrite($debug, "  ✅ НАЙДЕН T_EXTENDS на позиции $i!\n");
                
                $j = $i + 1;
                
                // Пропускаем пробелы
                while (isset($this->tokens[$j]) && is_array($this->tokens[$j]) && $this->tokens[$j][0] === T_WHITESPACE) {
                    fwrite($debug, "    Пропускаем пробел: '" . $this->tokens[$j][1] . "'\n");
                    $j++;
                }
                
                // Собираем полное имя родительского класса
                $extends = '';
                $parentTokens = [];
                
                // В PHP 8+ fully qualified name может быть одним токеном T_NAME_FULLY_QUALIFIED
                if (isset($this->tokens[$j]) && is_array($this->tokens[$j])) {
                    $nextTokenType = $this->tokens[$j][0];
                    
                    fwrite($debug, "    Следующий токен тип: " . token_name($nextTokenType) . "\n");
                    
                    // Проверяем новые токены PHP 8
                    if ($nextTokenType === T_NAME_FULLY_QUALIFIED || 
                        $nextTokenType === T_NAME_QUALIFIED || 
                        $nextTokenType === T_NAME_RELATIVE) {
                        
                        $extends = $this->tokens[$j][1];
                        $parentTokens[] = [
                            'type' => token_name($nextTokenType),
                            'text' => $this->tokens[$j][1]
                        ];
                        fwrite($debug, "    Найден PHP 8 токен: '" . $this->tokens[$j][1] . "' (" . token_name($nextTokenType) . ")\n");
                        $j++;
                    } 
                    // Старый вариант с отдельными T_STRING и T_NS_SEPARATOR
                    else {
                        while (isset($this->tokens[$j]) && is_array($this->tokens[$j])) {
                            // Если встретили пробел - останавливаемся
                            if ($this->tokens[$j][0] === T_WHITESPACE) {
                                fwrite($debug, "    Встретили пробел, останавливаемся\n");
                                break;
                            }
                            
                            // Если встретили открывающую скобку - останавливаемся
                            if (!is_array($this->tokens[$j]) && $this->tokens[$j] === '{') {
                                fwrite($debug, "    Встретили {, останавливаемся\n");
                                break;
                            }
                            
                            // Добавляем T_STRING или T_NS_SEPARATOR
                            if ($this->tokens[$j][0] === T_STRING || $this->tokens[$j][0] === T_NS_SEPARATOR) {
                                $extends .= $this->tokens[$j][1];
                                $parentTokens[] = [
                                    'type' => token_name($this->tokens[$j][0]),
                                    'text' => $this->tokens[$j][1]
                                ];
                                fwrite($debug, "    Добавляем: '" . $this->tokens[$j][1] . "' (" . token_name($this->tokens[$j][0]) . ")\n");
                                $j++;
                            } else {
                                fwrite($debug, "    Неизвестный токен: " . token_name($this->tokens[$j][0]) . "\n");
                                break;
                            }
                        }
                    }
                }
                
                fwrite($debug, "  ИТОГО: extends = '$extends'\n");
                fwrite($debug, "  Детально: " . json_encode($parentTokens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
                fclose($debug);
                
                return $extends ?: null;
            }
        }
    }
    
    fwrite($debug, "  Extends не найден\n");
    fclose($debug);
    return null;
}
    /**
     * Поиск implements
     */
    private function findImplements($index)
    {
        $implements = [];
        
        for ($i = $index + 1; $i < min(count($this->tokens), $index + 30); $i++) {
            if (!isset($this->tokens[$i])) continue;
            
            if (is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_IMPLEMENTS) {
                $j = $i + 1;
                $currentInterface = '';
                
                while (isset($this->tokens[$j]) && $this->tokens[$j] !== '{') {
                    if (is_array($this->tokens[$j])) {
                        if ($this->tokens[$j][0] === T_STRING || $this->tokens[$j][0] === T_NS_SEPARATOR) {
                            $currentInterface .= $this->tokens[$j][1];
                        } elseif ($this->tokens[$j][0] === T_WHITESPACE) {
                            if (!empty($currentInterface)) {
                                $implements[] = $currentInterface;
                                $currentInterface = '';
                            }
                        }
                    } else {
                        if ($this->tokens[$j] === ',') {
                            if (!empty($currentInterface)) {
                                $implements[] = $currentInterface;
                                $currentInterface = '';
                            }
                        }
                    }
                    $j++;
                }
                
                if (!empty($currentInterface)) {
                    $implements[] = $currentInterface;
                }
                
                break;
            }
        }
        
        return $implements;
    }

    /**
     * Проверка, находится ли токен внутри класса
     */
    private function isInsideClass($index)
    {
        $braceLevel = 0;
        $classFound = false;
        
        for ($i = 0; $i < $index; $i++) {
            if (!isset($this->tokens[$i])) continue;
            
            if (!is_array($this->tokens[$i])) {
                if ($this->tokens[$i] === '{') $braceLevel++;
                if ($this->tokens[$i] === '}') $braceLevel--;
                continue;
            }
            
            if ($this->tokens[$i][0] === T_CLASS || 
                $this->tokens[$i][0] === T_INTERFACE || 
                $this->tokens[$i][0] === T_TRAIT) {
                $classFound = true;
            }
        }
        
        return $classFound && $braceLevel > 0;
    }

    /**
     * Проверка, находится ли переменная внутри метода
     */
    private function isInsideMethod($index)
    {
        for ($i = $index; $i >= 0; $i--) {
            if (!isset($this->tokens[$i])) continue;
            
            if (!is_array($this->tokens[$i])) {
                if ($this->tokens[$i] === '}') break;
                continue;
            }
            
            if ($this->tokens[$i][0] === T_FUNCTION) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Анализ свойства класса
     */
    private function analyzeProperty($index, $className)
    {
        $propertyName = $this->tokens[$index][1];
        
        $visibility = 'public';
        $static = false;
        $type = null;
        $default = null;
        
        for ($i = $index - 1; $i >= max(0, $index - 20); $i--) {
            if (!isset($this->tokens[$i])) continue;
            
            if (!is_array($this->tokens[$i])) {
                if ($this->tokens[$i] === ';' || $this->tokens[$i] === '{') {
                    break;
                }
                continue;
            }
            
            if ($this->tokens[$i][0] === T_PUBLIC) {
                $visibility = 'public';
            } elseif ($this->tokens[$i][0] === T_PRIVATE) {
                $visibility = 'private';
            } elseif ($this->tokens[$i][0] === T_PROTECTED) {
                $visibility = 'protected';
            } elseif ($this->tokens[$i][0] === T_STATIC) {
                $static = true;
            } elseif ($this->tokens[$i][0] === T_VAR) {
                $visibility = 'public';
            }
        }
        
        $default = $this->extractPropertyDefault($index);
        $docblock = $this->getPropertyDocBlock($index);
        
        if ($docblock && isset($docblock['tags']['var'])) {
            $type = $docblock['tags']['var']['type'];
        }
        
        return [
            'name' => $propertyName,
            'visibility' => $visibility,
            'static' => $static,
            'type' => $type,
            'default' => $default,
            'line' => $this->tokens[$index][2],
            'docblock' => $docblock
        ];
    }

    /**
     * Извлечение значения по умолчанию для свойства
     */
    private function extractPropertyDefault($index)
    {
        $i = $index + 1;
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && $this->tokens[$i] === '=') {
            $i++;
            
            while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }
            
            $value = '';
            $braceLevel = 0;
            
            while (isset($this->tokens[$i]) && 
                   !($this->tokens[$i] === ';' && $braceLevel === 0) && 
                   !($this->tokens[$i] === ',' && $braceLevel === 0)) {
                
                if ($this->tokens[$i] === '(' || $this->tokens[$i] === '[' || $this->tokens[$i] === '{') {
                    $braceLevel++;
                } elseif ($this->tokens[$i] === ')' || $this->tokens[$i] === ']' || $this->tokens[$i] === '}') {
                    $braceLevel--;
                }
                
                if (is_array($this->tokens[$i])) {
                    $value .= $this->tokens[$i][1];
                } else {
                    $value .= $this->tokens[$i];
                }
                $i++;
            }
            
            return trim($value);
        }
        
        return null;
    }

    /**
     * Получение docblock для свойства
     */
    private function getPropertyDocBlock($index)
    {
        for ($i = $index - 1; $i >= max(0, $index - 10); $i--) {
            if (!isset($this->tokens[$i])) continue;
            
            if (!is_array($this->tokens[$i])) {
                if ($this->tokens[$i] === '}') break;
                continue;
            }
            
            if ($this->tokens[$i][0] === T_DOC_COMMENT) {
                return [
                    'content' => $this->tokens[$i][1],
                    'line' => $this->tokens[$i][2],
                    'tags' => $this->parseDocTags($this->tokens[$i][1])
                ];
            }
            
            if ($this->tokens[$i][0] !== T_WHITESPACE && $this->tokens[$i][0] !== T_COMMENT) {
                break;
            }
        }
        
        return null;
    }

    /**
     * Извлечение параметров функции
     */
    private function extractParameters($index)
    {
        $params = [];
        $i = $index + 1;
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_STRING) {
            $i++;
        }
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && $this->tokens[$i] === '(') {
            $braceLevel = 1;
            $j = $i + 1;
            $paramString = '';
            
            while (isset($this->tokens[$j]) && $braceLevel > 0) {
                if ($this->tokens[$j] === '(') {
                    $braceLevel++;
                } elseif ($this->tokens[$j] === ')') {
                    $braceLevel--;
                    if ($braceLevel === 0) break;
                }
                
                if ($braceLevel > 0) {
                    if (is_array($this->tokens[$j])) {
                        $paramString .= $this->tokens[$j][1];
                    } else {
                        $paramString .= $this->tokens[$j];
                    }
                }
                $j++;
            }
            
            $params = $this->parseParameters($paramString);
        }
        
        return $params;
    }

    /**
     * Разбор строки параметров
     */
    private function parseParameters($paramString)
    {
        $params = [];
        $current = '';
        $braceLevel = 0;
        $len = strlen($paramString);
        
        for ($i = 0; $i < $len; $i++) {
            $char = $paramString[$i];
            
            if ($char === '(' || $char === '[' || $char === '{') {
                $braceLevel++;
                $current .= $char;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $braceLevel--;
                $current .= $char;
            } elseif ($char === ',' && $braceLevel === 0) {
                $params[] = $this->parseSingleParameter(trim($current));
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        if (!empty($current)) {
            $params[] = $this->parseSingleParameter(trim($current));
        }
        
        return $params;
    }

    /**
     * Разбор одного параметра
     */
    private function parseSingleParameter($param)
    {
        $result = [
            'type' => null,
            'name' => null,
            'default' => null,
            'reference' => false,
            'variadic' => false
        ];
        
        if (strpos($param, '&') !== false) {
            $result['reference'] = true;
            $param = str_replace('&', '', $param);
        }
        
        if (strpos($param, '...') !== false) {
            $result['variadic'] = true;
            $param = str_replace('...', '', $param);
        }
        
        if (preg_match('/^(\\??[a-zA-Z_\x7f-\xff\\\\][a-zA-Z0-9_\x7f-\xff\\\\]*)\s+/', $param, $matches)) {
            $result['type'] = $matches[1];
            $param = substr($param, strlen($matches[1]));
        }
        
        if (preg_match('/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $param, $matches)) {
            $result['name'] = $matches[1];
        }
        
        if (strpos($param, '=') !== false) {
            $parts = explode('=', $param, 2);
            $result['default'] = trim($parts[1]);
        }
        
        return $result;
    }

    /**
     * Извлечение типа возврата
     */
    private function extractReturnType($index)
    {
        $i = $index + 1;
        
        while (isset($this->tokens[$i]) && $this->tokens[$i] !== ')') {
            $i++;
        }
        
        while (isset($this->tokens[$i])) {
            if (!is_array($this->tokens[$i]) && $this->tokens[$i] === ':') {
                $j = $i + 1;
                while (isset($this->tokens[$j]) && is_array($this->tokens[$j]) && $this->tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                
                $returnType = '';
                while (isset($this->tokens[$j]) && is_array($this->tokens[$j]) && 
                       ($this->tokens[$j][0] === T_STRING || $this->tokens[$j][0] === T_NS_SEPARATOR)) {
                    $returnType .= $this->tokens[$j][1];
                    $j++;
                }
                
                return $returnType ?: null;
            }
            $i++;
        }
        
        return null;
    }

    /**
     * Получение docblock
     */
    private function getDocBlock($index)
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            if (!isset($this->tokens[$i])) continue;
            
            if (!is_array($this->tokens[$i])) {
                if ($this->tokens[$i] === '}') break;
                continue;
            }
            
            if ($this->tokens[$i][0] === T_DOC_COMMENT) {
                return [
                    'content' => $this->tokens[$i][1],
                    'line' => $this->tokens[$i][2],
                    'tags' => $this->parseDocTags($this->tokens[$i][1])
                ];
            }
            
            if ($this->tokens[$i][0] !== T_WHITESPACE && $this->tokens[$i][0] !== T_COMMENT) {
                break;
            }
        }
        
        return null;
    }

    /**
     * Парсинг тегов docblock
     */
    private function parseDocTags($docblock)
    {
        $tags = [];
        
        if (preg_match_all('/@param\s+([^\s]+)\s+\$([^\s]+)\s+([^\n]*)/', $docblock, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tags['param'][] = [
                    'type' => $match[1],
                    'name' => $match[2],
                    'description' => trim($match[3])
                ];
            }
        }
        
        if (preg_match('/@return\s+([^\s]+)\s+([^\n]*)/', $docblock, $match)) {
            $tags['return'] = [
                'type' => $match[1],
                'description' => trim($match[2] ?? '')
            ];
        }
        
        if (preg_match_all('/@throws\s+([^\s]+)\s+([^\n]*)/', $docblock, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tags['throws'][] = [
                    'type' => $match[1],
                    'description' => trim($match[2] ?? '')
                ];
            }
        }
        
        if (preg_match('/@var\s+([^\s]+)\s+([^\n]*)/', $docblock, $match)) {
            $tags['var'] = [
                'type' => $match[1],
                'description' => trim($match[2] ?? '')
            ];
        }
        
        return $tags;
    }

    /**
     * Получение видимости
     */
    private function getVisibility($index)
    {
        for ($i = $index - 1; $i >= max(0, $index - 10); $i--) {
            if (!isset($this->tokens[$i]) || !is_array($this->tokens[$i])) continue;
            
            if ($this->tokens[$i][0] === T_PUBLIC) {
                return 'public';
            }
            if ($this->tokens[$i][0] === T_PRIVATE) {
                return 'private';
            }
            if ($this->tokens[$i][0] === T_PROTECTED) {
                return 'protected';
            }
        }
        
        return 'public';
    }

    /**
     * Извлечение имени константы
     */
    private function extractConstantName($index)
    {
        $i = $index + 1;
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && $this->tokens[$i] === '(') {
            $i++;
        }
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
            return trim($this->tokens[$i][1], '\'"');
        }
        
        return null;
    }

    /**
     * Извлечение значения константы
     */
    private function extractConstantValue($index)
    {
        $i = $index + 1;
        $commaCount = 0;
        
        while (isset($this->tokens[$i]) && $commaCount < 2) {
            if (!is_array($this->tokens[$i]) && $this->tokens[$i] === ',') {
                $commaCount++;
            }
            $i++;
        }
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        $value = '';
        while (isset($this->tokens[$i]) && $this->tokens[$i] !== ')') {
            if (is_array($this->tokens[$i])) {
                $value .= $this->tokens[$i][1];
            } else {
                $value .= $this->tokens[$i];
            }
            $i++;
        }
        
        return trim($value, '\'"');
    }

    /**
     * Простое извлечение значения константы класса
     */
    private function extractConstantValueSimple($index)
    {
        $i = $index + 1;
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_STRING) {
            $i++;
        }
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && $this->tokens[$i] === '=') {
            $i++;
            
            while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
                $i++;
            }
            
            $value = '';
            $braceLevel = 0;
            
            while (isset($this->tokens[$i]) && $this->tokens[$i] !== ';' && $this->tokens[$i] !== ',') {
                if ($this->tokens[$i] === '(' || $this->tokens[$i] === '[' || $this->tokens[$i] === '{') {
                    $braceLevel++;
                } elseif ($this->tokens[$i] === ')' || $this->tokens[$i] === ']' || $this->tokens[$i] === '}') {
                    $braceLevel--;
                }
                
                if (is_array($this->tokens[$i])) {
                    $value .= $this->tokens[$i][1];
                } else {
                    $value .= $this->tokens[$i];
                }
                $i++;
            }
            
            return trim($value);
        }
        
        return null;
    }

    /**
     * Извлечение имени
     */
    private function extractName($index)
    {
        $i = $index + 1;
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_STRING) {
            return $this->tokens[$i][1];
        }
        
        return 'anonymous';
    }

    /**
     * Извлечение namespace
     */
    private function extractNamespace($index)
    {
        $namespace = '';
        $i = $index + 1;
        
        while (isset($this->tokens[$i])) {
            if (!is_array($this->tokens[$i])) {
                if ($this->tokens[$i] === ';' || $this->tokens[$i] === '{') break;
                $namespace .= $this->tokens[$i];
            } else {
                if ($this->tokens[$i][0] === T_WHITESPACE) {
                    $i++;
                    continue;
                }
                $namespace .= $this->tokens[$i][1];
            }
            $i++;
        }
        
        return trim($namespace);
    }

    /**
     * Извлечение include файла
     */
    private function extractInclude($index)
    {
        $i = $index + 1;
        
        while (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_WHITESPACE) {
            $i++;
        }
        
        if (isset($this->tokens[$i]) && is_array($this->tokens[$i]) && $this->tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
            return trim($this->tokens[$i][1], '\'"');
        }
        
        return null;
    }

    /**
     * Анализ комментариев
     */
    private function analyzeComment($comment, $line)
    {
        if (preg_match('/TODO|FIXME|XXX/i', $comment, $matches)) {
            $this->warnings[] = [
                'type' => 'todo_comment',
                'line' => $line,
                'message' => "Найден комментарий: {$matches[0]}"
            ];
        }
    }

    /**
     * Подсчет статистики
     */
    private function calculateStatistics()
    {
        $lines = explode("\n", $this->content);
        $this->statistics['lines'] = count($lines);
        
        foreach ($lines as $line) {
            if (trim($line) === '') {
                $this->statistics['empty_lines']++;
            }
        }
        
        $this->statistics['total_functions'] = count($this->detailedInfo['functions']);
        $this->statistics['total_methods'] = count($this->detailedInfo['methods']);
    }

    /**
     * Расчет метрик
     */
    private function calculateMetrics()
    {
        $metrics = [];
        
        $complexity = 0;
        $search = ['if', 'elseif', 'for', 'foreach', 'while', 'case', 'catch', '&&', '||', '? :'];
        foreach ($search as $keyword) {
            $complexity += substr_count($this->content, $keyword);
        }
        $metrics['cyclomatic_complexity'] = $complexity;
        
        if ($this->statistics['lines'] > 0) {
            $metrics['comment_ratio'] = round(
                ($this->statistics['comments'] / $this->statistics['lines']) * 100, 
                2
            ) . '%';
        } else {
            $metrics['comment_ratio'] = '0%';
        }
        
        $metrics['avg_function_length'] = $this->statistics['functions'] > 0 
            ? round($this->statistics['lines'] / $this->statistics['functions']) 
            : 0;
        
        $metrics['class_density'] = $this->statistics['lines'] > 0
            ? round(($this->statistics['classes'] / $this->statistics['lines']) * 100, 3) . '%'
            : '0%';
        
        $metrics['methods_per_class'] = $this->statistics['classes'] > 0
            ? round($this->statistics['methods'] / $this->statistics['classes'], 2)
            : 0;
        
        return $metrics;
    }

    /**
     * Получение структуры
     */
    private function getStructure()
    {
        return [
            'namespaces' => $this->detailedInfo['namespaces'],
            'uses' => $this->detailedInfo['uses'],
            'classes' => $this->detailedInfo['classes'],
            'interfaces' => $this->detailedInfo['interfaces'],
            'traits' => $this->detailedInfo['traits'],
            'functions' => $this->detailedInfo['functions'],
            'constants' => $this->detailedInfo['constants'],
            'includes' => $this->detailedInfo['includes']
        ];
    }

    /**
     * Получение короткого имени из полного
     */
    private function getShortName($fullName)
    {
        if (strpos($fullName, '\\') !== false) {
            $parts = explode('\\', $fullName);
            return end($parts);
        }
        return $fullName;
    }

    /**
     * Форматирование байт
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Получение отчета
     */
    public function getReport($format = 'text')
    {
        $analysis = $this->analyze();
        
        switch ($format) {
            case 'json':
                return json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                
            case 'html':
                return $this->generateHtmlReport($analysis);
                
            case 'text':
            default:
                return $this->generateTextReport($analysis);
        }
    }

    /**
     * Генерация текстового отчета - ИСПРАВЛЕННАЯ ВЕРСИЯ С EXTENDS
     */
    private function generateTextReport($analysis)
    {
        $report = [];
        $report[] = str_repeat('=', 80);
        $report[] = "PHP FILE ANALYZER - РАСШИРЕННЫЙ ОТЧЕТ";
        $report[] = str_repeat('=', 80);
        
        $report[] = "\n📁 ИНФОРМАЦИЯ О ФАЙЛЕ:";
        $report[] = str_repeat('-', 40);
        $info = $analysis['file_info'];
        $report[] = sprintf("  Файл: %s", $info['name']);
        $report[] = sprintf("  Путь: %s", $info['path']);
        $report[] = sprintf("  Размер: %s", $info['size_formatted']);
        $report[] = sprintf("  Изменен: %s", $info['modified']);
        $report[] = sprintf("  Права: %s", $info['permissions']);
        
        $report[] = "\n📊 СТАТИСТИКА:";
        $report[] = str_repeat('-', 40);
        $stats = $analysis['statistics'];
        $report[] = sprintf("  Всего строк: %d", $stats['lines']);
        $report[] = sprintf("  Пустых строк: %d", $stats['empty_lines']);
        $report[] = sprintf("  Комментариев: %d", $stats['comments']);
        $report[] = sprintf("  Функций: %d", $stats['functions']);
        $report[] = sprintf("  Методов: %d", $stats['methods']);
        $report[] = sprintf("  Классов: %d", $stats['classes']);
        $report[] = sprintf("  Интерфейсов: %d", $stats['interfaces']);
        $report[] = sprintf("  Трейтов: %d", $stats['traits']);
        $report[] = sprintf("  Абстрактных классов: %d", $stats['abstract_classes']);
        $report[] = sprintf("  Final классов: %d", $stats['final_classes']);
        $report[] = sprintf("  Абстрактных методов: %d", $stats['abstract_methods']);
        $report[] = sprintf("  Final методов: %d", $stats['final_methods']);
        $report[] = sprintf("  Static методов: %d", $stats['static_methods']);
        $report[] = sprintf("  Констант: %d", $stats['constants']);
        $report[] = sprintf("  Переменных: %d", $stats['variables']);
        $report[] = sprintf("  Include/Require: %d", $stats['includes']);
        $report[] = sprintf("  Namespace: %d", $stats['namespaces']);
        
        $report[] = "\n📈 МЕТРИКИ КОДА:";
        $report[] = str_repeat('-', 40);
        $metrics = $analysis['metrics'];
        $report[] = sprintf("  Цикломатическая сложность: %d", $metrics['cyclomatic_complexity']);
        $report[] = sprintf("  Отношение комментариев: %s", $metrics['comment_ratio']);
        $report[] = sprintf("  Методов на класс: %.2f", $metrics['methods_per_class']);
        
        if (!empty($analysis['detailed']['namespaces'])) {
            $report[] = "\n🌐 NAMESPACES:";
            $report[] = str_repeat('-', 40);
            foreach ($analysis['detailed']['namespaces'] as $ns) {
                $report[] = sprintf("  • %s (стр. %d)", $ns['name'], $ns['line']);
            }
        }
        
        if (!empty($analysis['detailed']['uses'])) {
            $report[] = "\n📦 USE STATEMENTS:";
            $report[] = str_repeat('-', 40);
            foreach ($analysis['detailed']['uses'] as $use) {
                if ($use['alias'] && $use['alias'] !== $this->getShortName($use['class'])) {
                    $report[] = sprintf("  • use %s as %s;", $use['class'], $use['alias']);
                } else {
                    $report[] = sprintf("  • use %s;", $use['class']);
                }
            }
        }
        
        // ============= КЛАССЫ С ГАРАНТИРОВАННЫМ ПОКАЗОМ EXTENDS =============
        if (!empty($analysis['detailed']['classes']) || !empty($analysis['detailed']['interfaces']) || !empty($analysis['detailed']['traits'])) {
            $report[] = "\n📚 КЛАССЫ, ИНТЕРФЕЙСЫ И ТРЕЙТЫ:";
            $report[] = str_repeat('-', 40);
            
            // Сначала классы
            foreach ($analysis['detailed']['classes'] as $name => $class) {
                $icon = '📦';
                $typeName = 'Class';
                
                $modifiersStr = '';
                if (!empty($class['modifiers'])) {
                    $modifiersStr = ' [' . implode(', ', $class['modifiers']) . ']';
                }
                
                $report[] = sprintf("  %s %s%s %s (line %d)", $icon, $typeName, $modifiersStr, $name, $class['line']);
                
                // Где объявлен (namespace)
                if (!empty($class['namespace'])) {
                    $report[] = sprintf("    📍 Namespace: %s", $class['namespace']);
                }
                
                // ========== ВАЖНО: EXTENDS ==========
                // Проверяем наличие extends и показываем
                if (!empty($class['extends'])) {
                    $report[] = sprintf("    ⬆️ Extends: %s", $class['extends']);
                }
                
                // Implements
                if (!empty($class['implements'])) {
                    $report[] = sprintf("    📋 Implements: %s", implode(', ', $class['implements']));
                }
                
                // Uses traits
                if (!empty($class['uses'])) {
                    $report[] = "    🔧 Uses traits:";
                    foreach ($class['uses'] as $trait) {
                        $report[] = sprintf("      • %s", $trait['name']);
                        if (!empty($trait['aliases'])) {
                            foreach ($trait['aliases'] as $alias) {
                                $report[] = sprintf("        ↳ %s", $alias);
                            }
                        }
                    }
                }
                
                // Properties
                if (!empty($class['properties'])) {
                    $report[] = "    📊 Properties:";
                    foreach ($class['properties'] as $prop) {
                        $static = $prop['static'] ? ' static' : '';
                        $type = $prop['type'] ? ': ' . $prop['type'] : '';
                        $default = $prop['default'] !== null ? ' = ' . $prop['default'] : '';
                        
                        // Обработка длинных значений
                        $propName = $prop['name'];
                        $propDisplay = sprintf("      • %s%s $%s%s%s", 
                                          $prop['visibility'], 
                                          $static, 
                                          $propName,
                                          $type,
                                          $default);
                        
                        // Если строка слишком длинная, обрезаем
                        if (strlen($propDisplay) > 100) {
                            $propDisplay = substr($propDisplay, 0, 97) . '...';
                        }
                        $report[] = $propDisplay;
                    }
                }
                
                // Constants
                if (!empty($class['constants'])) {
                    $report[] = "    🔧 Constants:";
                    foreach ($class['constants'] as $const) {
                        $report[] = sprintf("      • %s %s = %s", 
                                          $const['visibility'], 
                                          $const['name'], 
                                          $const['value']);
                    }
                }
                
                // Methods
                if (!empty($class['methods'])) {
                    $report[] = "    ⚙️ Methods:";
                    foreach ($class['methods'] as $method) {
                        $modifiers = [];
                        if ($method['static']) $modifiers[] = 'static';
                        if ($method['abstract']) $modifiers[] = 'abstract';
                        if ($method['final']) $modifiers[] = 'final';
                        
                        $modifiersStr = !empty($modifiers) ? ' [' . implode(', ', $modifiers) . ']' : '';
                        
                        $params = [];
                        foreach ($method['params'] as $param) {
                            $paramStr = '';
                            if ($param['type']) $paramStr .= $param['type'] . ' ';
                            if ($param['reference']) $paramStr .= '&';
                            if ($param['variadic']) $paramStr .= '...';
                            $paramStr .= '$' . $param['name'];
                            if ($param['default'] !== null) $paramStr .= ' = ' . $param['default'];
                            $params[] = $paramStr;
                        }
                        $paramsStr = implode(', ', $params);
                        $return = $method['return_type'] ? ': ' . $method['return_type'] : '';
                        
                        $report[] = sprintf("      • %s%s %s(%s)%s", 
                                          $method['visibility'],
                                          $modifiersStr,
                                          $method['name'],
                                          $paramsStr,
                                          $return);
                    }
                }
                $report[] = ""; // Пустая строка между классами
            }
            
            // Интерфейсы
            foreach ($analysis['detailed']['interfaces'] as $name => $interface) {
                $icon = '🔌';
                $typeName = 'Interface';
                
                $report[] = sprintf("  %s %s %s (line %d)", $icon, $typeName, $name, $interface['line']);
                
                if (!empty($interface['namespace'])) {
                    $report[] = sprintf("    📍 Namespace: %s", $interface['namespace']);
                }
                
                if (!empty($interface['extends'])) {
                    $report[] = sprintf("    ⬆️ Extends: %s", $interface['extends']);
                }
                
                if (!empty($interface['methods'])) {
                    $report[] = "    ⚙️ Methods:";
                    foreach ($interface['methods'] as $method) {
                        $params = [];
                        foreach ($method['params'] as $param) {
                            $paramStr = '';
                            if ($param['type']) $paramStr .= $param['type'] . ' ';
                            $paramStr .= '$' . $param['name'];
                            $params[] = $paramStr;
                        }
                        $paramsStr = implode(', ', $params);
                        $return = $method['return_type'] ? ': ' . $method['return_type'] : '';
                        
                        $report[] = sprintf("      • %s %s(%s)%s", 
                                          $method['visibility'],
                                          $method['name'],
                                          $paramsStr,
                                          $return);
                    }
                }
                $report[] = "";
            }
            
            // Трейты
            foreach ($analysis['detailed']['traits'] as $name => $trait) {
                $icon = '🧩';
                $typeName = 'Trait';
                
                $report[] = sprintf("  %s %s %s (line %d)", $icon, $typeName, $name, $trait['line']);
                
                if (!empty($trait['namespace'])) {
                    $report[] = sprintf("    📍 Namespace: %s", $trait['namespace']);
                }
                
                if (!empty($trait['properties'])) {
                    $report[] = "    📊 Properties:";
                    foreach ($trait['properties'] as $prop) {
                        $static = $prop['static'] ? ' static' : '';
                        $type = $prop['type'] ? ': ' . $prop['type'] : '';
                        $default = $prop['default'] !== null ? ' = ' . $prop['default'] : '';
                        $report[] = sprintf("      • %s%s $%s%s%s", 
                                          $prop['visibility'], 
                                          $static, 
                                          $prop['name'],
                                          $type,
                                          $default);
                    }
                }
                
                if (!empty($trait['methods'])) {
                    $report[] = "    ⚙️ Methods:";
                    foreach ($trait['methods'] as $method) {
                        $modifiers = [];
                        if ($method['static']) $modifiers[] = 'static';
                        
                        $modifiersStr = !empty($modifiers) ? ' [' . implode(', ', $modifiers) . ']' : '';
                        
                        $params = [];
                        foreach ($method['params'] as $param) {
                            $paramStr = '';
                            if ($param['type']) $paramStr .= $param['type'] . ' ';
                            if ($param['reference']) $paramStr .= '&';
                            if ($param['variadic']) $paramStr .= '...';
                            $paramStr .= '$' . $param['name'];
                            if ($param['default'] !== null) $paramStr .= ' = ' . $param['default'];
                            $params[] = $paramStr;
                        }
                        $paramsStr = implode(', ', $params);
                        $return = $method['return_type'] ? ': ' . $method['return_type'] : '';
                        
                        $report[] = sprintf("      • %s%s %s(%s)%s", 
                                          $method['visibility'],
                                          $modifiersStr,
                                          $method['name'],
                                          $paramsStr,
                                          $return);
                    }
                }
                $report[] = "";
            }
        }
        
        // Глобальные функции
        if (!empty($analysis['detailed']['functions'])) {
            $report[] = "\n🔧 ГЛОБАЛЬНЫЕ ФУНКЦИИ:";
            $report[] = str_repeat('-', 40);
            foreach ($analysis['detailed']['functions'] as $name => $func) {
                $ns = $func['namespace'] ? $func['namespace'] . '\\' : '';
                $report[] = sprintf("  • %s%s() (стр. %d)", $ns, $name, $func['line']);
                
                if (!empty($func['params'])) {
                    $params = [];
                    foreach ($func['params'] as $param) {
                        $paramStr = '';
                        if ($param['type']) $paramStr .= $param['type'] . ' ';
                        if ($param['reference']) $paramStr .= '&';
                        if ($param['variadic']) $paramStr .= '...';
                        $paramStr .= '$' . $param['name'];
                        if ($param['default'] !== null) $paramStr .= ' = ' . $param['default'];
                        $params[] = $paramStr;
                    }
                    $report[] = sprintf("    Параметры: %s", implode(', ', $params));
                }
                
                if ($func['return_type']) {
                    $report[] = sprintf("    Возвращает: %s", $func['return_type']);
                }
            }
        }
        
        // Константы
        if (!empty($analysis['detailed']['constants'])) {
            $report[] = "\n🔧 КОНСТАНТЫ:";
            $report[] = str_repeat('-', 40);
            foreach ($analysis['detailed']['constants'] as $const) {
                $ns = isset($const['namespace']) && $const['namespace'] ? $const['namespace'] . '\\' : '';
                $report[] = sprintf("  • define('%s', %s) (стр. %d)", 
                                   $const['name'], 
                                   $const['value'], 
                                   $const['line']);
            }
        }
        
        // Зависимости
        if (!empty($analysis['detailed']['includes'])) {
            $report[] = "\n📎 ЗАВИСИМОСТИ:";
            $report[] = str_repeat('-', 40);
            foreach ($analysis['detailed']['includes'] as $include) {
                $report[] = sprintf("  • [%s] %s (стр. %d)", 
                                   $include['type'], $include['file'], $include['line']);
            }
        }
        
        // Предупреждения
        if (!empty($analysis['issues']['warnings'])) {
            $report[] = "\n⚠️ ПРЕДУПРЕЖДЕНИЯ:";
            $report[] = str_repeat('-', 40);
            foreach ($analysis['issues']['warnings'] as $warning) {
                $report[] = sprintf("  • [стр. %d] %s", $warning['line'], $warning['message']);
            }
        }
        
        // Ошибки
        if (!empty($analysis['issues']['errors'])) {
            $report[] = "\n❌ ОШИБКИ:";
            $report[] = str_repeat('-', 40);
            foreach ($analysis['issues']['errors'] as $error) {
                $report[] = sprintf("  • %s", $error['message']);
            }
        }
        
        $report[] = "\n" . str_repeat('=', 80);
        
        return implode("\n", $report);
    }

    /**
     * Генерация HTML отчета
     */
    private function generateHtmlReport($analysis)
    {
        // Здесь можно добавить HTML версию с extends
        // Для краткости оставляю как есть, но можно расширить
        return "<html><body><pre>" . htmlspecialchars($this->generateTextReport($analysis)) . "</pre></body></html>";
    }
}

// Пример использования
try {
    if ($argc > 1) {
        $filePath = $argv[1];
        $analyzer = new PHPFileAnalyzer($filePath);
        
        if ($argc > 2 && $argv[2] === '--json') {
            echo $analyzer->getReport('json');
        } elseif ($argc > 2 && $argv[2] === '--html') {
            file_put_contents('extended_report.html', $analyzer->getReport('html'));
            echo "Расширенный HTML отчет сохранен в extended_report.html\n";
            echo "\n" . $analyzer->getReport('text') . "\n";
        } else {
            echo $analyzer->getReport('text');
        }
    } else {
        echo "Использование: php analyzer.php <файл.php> [--json|--html]\n";
        echo "Пример: php analyzer.php test.php --html\n";
        echo "Пример: php analyzer.php test.php --json\n";
    }
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}
