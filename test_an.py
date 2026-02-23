
import sys
import re



php_filename = None

if len(sys.argv) > 1:
    php_filename = sys.argv[1]
    print('php filename', php_filename)

with open('file.txt', 'r', encoding='utf-8') as file:
    lines = file.readlines()
    print(lines)
    
    print(lines[0])
    sym = lines[0].split('=')
    print(sym, sym[0])
    
if php_filename:
    with open(php_filename, 'r', encoding='utf-8') as phpfile:
        content = phpfile.read()
else:
    print('php_filename is null')
    
print(content)

regex = re.compile(re.escape(sym[0]) + r'(?![0-9A-Za-z])')

positions = []

for match in regex.finditer(content):
        positions.append(match.start())
        print(f"Найдено на позиции {match.start()}: '{match.group()}'")
        
        # Показываем следующий символ для проверки
        next_pos = match.end()
        if next_pos < len(content):
            print(f"  следующий символ: '{content[next_pos]}' (OK - не цифра и не a-z)")
        else:
            print(f"  конец строки (OK)")


