## PhpRunner.py

import subprocess
import json

class PHP:
   @staticmethod
   def call(script_path, f):
       p = subprocess.Popen(['php', script_path, f], stdout=subprocess.PIPE)
       result = p.communicate()[0]
       
       return PHP.__json_to_list(result)

   def __json_to_list(json_code):
      if isinstance(json_code, bytes):
         php_output = json_code.decode('utf-8')
    
      try:
        # Парсим JSON в Python объект
         python_list = json.loads(json_code)
        
        # Убеждаемся, что это список
         if isinstance(python_list, list):
            return python_list
         else:
            print(f"Ожидался список, получен {type(python_list)}")
            return []
      except json.JSONDecodeError as e:
         print(f"Ошибка парсинга JSON: {e}")
         print(f"Полученные данные: {json_code}")
         return []
