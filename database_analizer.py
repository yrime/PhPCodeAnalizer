import os
from PhpRunner import PHP as phpy

from Tables import Table

class FilesFinder:
   def __init__(self, *base_dir):
      if len(base_dir) > 0:
         self.base_dir = os.path.expanduser(base_dir[0])
      else:
         self.base_dir = None
         
   def set_base_dir(self, base_dir):
      self.base_dir = os.path.expanduser(base_dir)
      
   def get_base_dir(self):
      return self.base_dir
      
   def find(self, extensions=None, exclude_dirs=None):

      if exclude_dirs is None:
          exclude_dirs = ['.git', '__pycache__', 'venv', '.idea']
    
      found_files = []
    
      for root, dirs, files in os.walk(self.base_dir):
      
          dirs[:] = [d for d in dirs if d not in exclude_dirs]
        
          for file in files:

              if extensions:
                  if any(file.endswith(ext) for ext in extensions):
                      full_path = os.path.join(root, file)
                      found_files.append(full_path)
              else:
                  full_path = os.path.join(root, file)
                  found_files.append(full_path)
    
      return found_files
      
class DatabaseCreater:
   
   def __init__(self):
      self.files = Table()
   
   def files_table(self, base_dir, extensions=None, exclude_dirs=None):
      files = FilesFinder(base_dir)
      found_files = files.find(extensions, exclude_dirs)
      for f in found_files:
         func_list = self.function_table(f)
         self.files.add(f, func_list)
      print(self.files.get_table(), self.files.size())
      self.files.print()
    
   def function_table(self, f):
      #for f in self.files.get_table():
      return self.__funcion_table(f)
    
   def __funcion_table(self, f):
      content = phpy.call("./parse_functions.php", f)
      return content

f = FilesFinder("~/Desktop/php_invasion")
python_files = f.find(extensions=['.py'])
print(f"Найдено {len(python_files)} Python файлов", python_files)

d = DatabaseCreater()
d.files_table("~/Desktop/php_invasion", extensions=['.php'])
#.function_table()
