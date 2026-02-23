

class BaseElement:
   def __init__(self, full_name, list_of_elements):
      self.name = full_name
      if list_of_elements:
         self.list_el = list_of_elements
      else:
         self.list_el = []
      
   def name(self):
      return self.name
   
   def elements(self):
      return self.list_el
      
   def add(self, element):
      self.list_el.append(element)

class Table:
   def __init__(self):
      self.base_table = []
      
   def add(self, name, append_list=None):
      self.base_table.append(BaseElement(name, append_list))

   
   def add_to_list(self, index, el):
      self.base_table[index].add(el)
      
   def get(self, index, *args):
      if len(args) > 0 and args[0] == "name":
         return self.base_table[index].name
      elif len(args) > 0 and args[0] == "list":
         return self.base_table[index].list_el
      elif len(args) > 0 and args[0] == "obj":
         return [self.base_table[index].name, self.base_table[index].list_el]
      else:
         return self.base_table[index]
         
   def get_table(self):
      return self.base_table
      
   def size(self):
      return len(self.base_table)
   
   def find(self, *args):
      if len(args) > 0:
         if type(args[0]) == int:
            return 100
         elif type(args[0]) == str:
            return "String"
            
         else:
            print("type of not declared")
            return None
      else:
         print("args is empty")
         return None
            

      
a = Table()

a.add("el1", ['a'])
a.add("el2")

print(a.get(0), a.get(0, "name"), a.get(0, "list"), a.get(0, "obj"))
print(a.find(0), a.find("el1"))

