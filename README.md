Generic PHP
===========

This is a prototyping project for testing generics in PHP.

Its currently a complete mess, as most of the code is in bin/test.php and being slowing moved out to something
more comprehensible.

Class type parameters work currently, generic methods/functions are another can of worms, so those come later.
Template generation works, and hense so does type validation.

LOL
---

```
    A >> 1  
```

T_STRING  
T_SR  
T_CONST  

```
    A > > 1  
```

T_STRING  
'>'  
'>'  
T_CONST

```
    new A<B<C>>
```

T_NEW  
T_STRING  
T_START_TARG  
T_STRING  
T_START_TARG  
T_STRING  
T_CLOSE_TARG  
T_CLOSE_TARG    
