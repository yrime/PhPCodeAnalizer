## PhpRunner.py

import subprocess

class PHP:
   def call(script_path, f):
       p = subprocess.Popen(['php', script_path, f], stdout=subprocess.PIPE)
       result = p.communicate()[0]
       return result

