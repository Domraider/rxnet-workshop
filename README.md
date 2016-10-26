# Workshop Forum PHP
You can play with reactiveX on [RxMarble.com](http://rxmarbles.com/), find all the available operators on the official [Reactivex.io](http://reactivex.io/documentation/operators.html) website and get some working examples on [rxnet](https://github.com/Domraider/rxnet)

## Exercice 1
Create an httpd server which listen to a GET request with a variable parameter.
When this route is called, it must :
 * call 4 api endpoints (see below)
 * transform the 4 results to a standard one
 * save it in redis
 * answer the client
  
  use the random_server provided :
  ```
  php exercises/servers/random_server.php
  ```
  
  you can call 4 endpoints :
  * http://127.0.0.1:24080/foo/{something}
  * http://127.0.0.1:24080/bar/{something}
  * http://127.0.0.1:24080/foobar/{something}
  * http://127.0.0.1:24080/barfoo/{something}
 
## Exercise 2
Let's tidy up the mess :
* httpd server route is a class
* each mapper is a class 

## Exercise 3
Now let's delegate the scraping work to a rabbit consumer and add some new functionality to our httpd.
