# Qpi
Qpi is a query language for APIs and a runtime for fulfilling those queries with your existing data. Qpi provides a complete and understandable description of the data in your API, gives clients the power to ask for exactly what they need and nothing more, makes it easier to evolve APIs over time.

>>>
First of all, you should know that Qpi is an idea and you can implement it in your own language. This project is just a Qpi implementation for Laravel.
>>>

## Usage

**1) Install it:**

```
composer require qpi/laravel
```

**2) Add to providers**

Open your Laravel config file `config/app.php` and add the following lines.

In the `$providers` array add the service provider for this package.
```php
Navac\Qpi\QpiServiceProvider::class,
```

**3) Update autoload:**
```
composer dumpautoload
```

## Configuration
First, you need to publish configuration file.
```
php artisan vendor:publish
```
The configuration file is copied to `config/qpi.php`.

## Registering models
To give access to the models, you have to register your models in `config/qpi.php`.

```php
return [

  /*
  |--------------------------------------------------------------------------
  | Qpi Models
  |--------------------------------------------------------------------------
  |
  | The models listed here will be available for Qpi to make responses.
  |
  */
  'models' => [
    'user' => App\User::class,
    'product' => App\Product::class,
  ]

];
```

## Quick Tutorial
This package will introduce two new routes to your app. `/query/{query}` and `/schema`.

If you want to make a query, just use the first endpoint like this:
```
/query/{ category{id, name} }
```
And if you want to see the manual of your models, use the second endpoint: `/schema`.

## The Query Language
Now, you just need to know how to use it. As I said, it's query language and you have to need to learn it to make your queries. It's a simple language with few tips. Please follow the next steps.

So let's start.
Each query must be between curly braces:
```
{
    ...the body of query
}
```

In the body of each query you have to define which model you want to fetch:
```
{
    book {
    }
}
```
In the above query, we want to fetch the `book` model. Someone may ask, what are the next curly braces? Those curly braces are just to tell Qpi which fields of that model you want to fetch.
If I want to complete my query,  It will be like this:
```
{
    book {
        title,
        created_at
    }
}
```
If you don't specify any field, it will return all of available fields.

**Nested queries:**
Imagine want to fetch the author of each book. So how can we do that?
```
{
    book {
        title,
        created_at,
        author {
            name,
            age
        }
    }
}
```
You see that Qpi supports making queries for nested models.

**Limiting results:**
If you want to specify how many records to fetch, you can do that like this:
```
{
    book (0:5) {
        title,
        created_at,
        author {
            name,
            age
        }
    }
}
```
I used above syntax to tell Qpi that I want to limit results to just 5 records. In other words, the parenthesis syntax is just this: `(offset, limit)`.

**Where statements:**
```
{
    book (0:5) [id=5] {
        title,
        created_at,
        author {
            name,
            age
        }
    }
}
```
You have to use brackets in order to write your where statements.

*Operators:*

| Operator     | Name         | Example        |
|--------------|--------------|----------------|
| =            | Equal        | id=5           |
| >            | Greater than | id>2           |
| <            | Less than    | id<6           |
| !            | Not equal    | id!6           |
| ~            | Like         | name~'john%'   |
| &#124;       | OR           | id<6&#124;id>3 |
| &            | AND          | id=6&id=3      |


**Order by:**
```
{
    book (0:5) [id=5] {
        title,
        created_at:1,
        author {
            name,
            age
        }
    }
}
```
You can add a colon after every field witch you want to order results by it. After colon you have to tell Qpi the direction. `1` for ASC and `0` for DESC.

**Query batching:**

With a single request, you can fetch all of your needs. Just put your queries into curly braces.
```
{
    book {
        title,
        created_at
    }
}
{
    category {
        id,
        name
    }
}
```
Above, you see two different queries but you can fetch it with a single request.

## Authorization
To enable authorization for models, you should add `qpiAccess` method to each model. Qpi will call this method in each request that want's to have access to that model and if the model throws an error, Qpi will reject the request.

```php
class Product extends Model {

    public function qpiAccess() {
        if(request()->header('Authorization') != 'the valid token') {
            throw new \Exception('Authorization failed.');
        }
    }

}
```

Note that Qpi will inject dependencies to this method.

# Schema (Comming soon...)
Your users can make request to `/schema` to see the schema of models. In order to do that, your models should have two optional static properties `$qpiProps` and `$qpiRelations`.
```php
class Product extends Model {
    public static $qpiProps = [
        'id' => 'The id',
        'name' => 'The name of the product',
        ...
    ];

    public static $qpiRelations = [
        'tags' => 'Related tags of product',
        ...
    ];
    
    ...
```