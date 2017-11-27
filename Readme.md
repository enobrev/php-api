# enobrev/php-api

The API library is a set of classes meant to help quickly put together a rest-ish API.  If you’re using it along with `enobrev/php-orm`, it can be used to generate endpoints for all your database tables.  It also has some additional functionality to automatically generate SQL using `enobrev/php-orm` directly from the URL given.

The most complex (read: messy) class in this library is the `Route` class.  There is definitely a bit too much magic in this class.  Its purpose is to take the given URL and fire up the appropriate class and method.  I don’t recommend trying to understand it with a plain read as it is in need of a great deal of refactoring.

Much like `SQL` and `SQLBuilder` are the interface to `Enobrev\ORM`, `Route` is the interface to `Enobrev\API`.

The root script for your API only needs one specific call:  `Route::index()`.   `Route` will take over from there, reading the HTTP request, figuring out where to go, and eventually outputting the response, so we’ll start there, at `Route::index()`.

The very first thing `Route` does, is create an instance of the `Enobrev\API\Request` class.  This class encapsulates the functionality of `Zend\Diactoros`, which is the spec implementation for how to handle HTTP requests in PHP.  It’s the primary request parser that we’ll use while trying to figure out what the user wants.  The instance holds the requested path, any requested parameters, headers, etc.

Next up, is a seemingly inconspicuous call to `Route::_getResponse`.  The end result of this method is to create an instance of the `Enobrev\API\Response` class.   This class, like the Request class, encapsulates the functionality of `Zend\Diactoros`, including sending headers, outputting content, adding cookies, and so on.

Of course, to generate this response, `Route` needs to figure out what to run in order to generate the output.  For this, `Route` includes a few options:  Hardcoded Class Methods, Hardcoded Rest Classes, and `Enobrev\ORM` based Rest routes, which can be overridden with custom classes.

Before we get started, Route requires a couple things to initialize properly.

```php
    Route::init(
      string $sPathAPI,           // Parent Path to your API classes
      string $sNamespaceAPI,      // API Class Namespace
      string $sNamespaceTable,    // Enobrev\ORM Namespace
      $sRestClass = Rest::class,  // Base class for handling Rest (optional override)
      array $aVersions = ['v1']   // Array of version directories (optional override)
    );
```

Here’s an example:

```php
    Route::init(__DIR__ . '/lib/API', 'MyApp\\API', 'MyApp\\Table', MyApp\API\Rest::class, ['v1', 'v2']);
```

**Hardcoded Class Methods**

```php
    Route::addEndpointRoute('car/setColor',  API\v1\Car::class, 'setColor' );
```

Adding this to your Route initialization will ensure that any call, regardless of `GET` , `POST`, etc to `http://yourapi/car/setColor` will call the `API\v1\Car::setColor` method.

This class should extend `Enobrev\API\Base` in order to receive the required properties from the `Request` instance, and to add the output to `Response`, both of which can quite simply be accessed via `$this->Request` and `$this->Response`.

**Hardcoded Rest Clases**

```php
    Route::addRestRoute('cars', API\v1\Cars::class);
```

Adding this to your Route initialization will ensure that any call to `http://yourapi/cars` will be sent to the `API\v1\Cars` class to a class method named after the request method.  So a `GET` request will be sent to `API\v1\Cars::get()`, and a `POST` method will be sent to `API\v1\Cars::post()`.

This class should extend `Enobrev\API\Rest` in order to receive the required properties from the `Request` instance, and to add the output to `Response`, both of which can quite simply be accessed via `$this->Request` and `$this->Response`.

**ORM Routes**

There require a different sort of initialization, which is to generate a DataMap.json file.

If you’ve read the documentation for `Enobrev\php-orm`, you’ll recall there was a script for generating a file called `sql.json`.  We can use this file to generate a `DataMap.json` file, which will be used by `Route` as a switchboard to generate queries for your database, derived entirely from the request itself.

To generate the DataMap file, you can call this from your project:

```sh
    php vendor/bin/generate_data_map.php -j ./sql.json -o lib/API
```

This will parse the file at ./sql.json, and then output the file `lib/API/DataMap.json`.   Once generated, `Route` will use that file for potential routes.  This file also allows you to have private and public names for tables and columns in those tables.

There are two sections in `DataMap.json`.  The first is `_CLASSES_`.  This object maps the name of your ORM classes to url names.  For instance `users` would be mapped to the `User` class.   And so any requests to `http://yourapi/users` would try to run a query through the `User` class.

After the `_CLASSES_` section, you’ll find stanzas named after every endpoint.   For instance, you might find:

```json
    "users": {
        "id"               : "user_id",
        "creator_id"       : "creator_user_id",
        "name"             : "user_name",
        "email"            : "user_email",
        "date_added"       : "user_date_added"
    }
```

This allows you to differentiate from the names of your columns from what will be used by your public API.  So a request for the id, name, and email of a user via the API, will be converted to a request for `User::user_id`, `User::user_name`, and `User::user_email`.

With `DataMap.json` in place, you can begin making API requests to those API tables immediately.  A `GET` with an id in the url will call Table::getById.  A `POST` with an id in the url will call `Table::update` or `Table::insert` depending on whether that record exists.

In order to override or extend this functionality, you can create a class in your API classes directory named after the Table you plan to override.  For instance, you can create a class called `lib/API/v1/User`, which extends `Enobrev\API\Rest`.  And then if you create a `get` method on that class, it would be called whenever a `GET` request comes in for `http://yourapi/users`.


## Multi-Endpoint Queries

Multiple endpoints can be queried in a single HTTP request using the `__query` parameter.  If you look at the HTTP POST request when you load the guide link above, you'll see something that looks like this:

```
__query: [
    '/config/,
    '/me/',
   ...
    '/users/{me.id}/favorites',
    '/users/{me.id}/places',
    ...
]
```

What it's doing is running those endpoints serially, and collecting the data cumulatively, while using the collected data to build further queries all within a single HTTP request.  So `me.id` refers to the id returned by `/me`.

## Cumulative Gathering

To explain what I mean by cumulative, take this query for example:

```
[
    /cities/1/guides
    /cities/2/guides
    /guides/mine   // custom endpoint
    /guides/{guides.id}/tips
]
```

This loads guides for two cities and then my guides, and then it will load the "tips" for All those guides that have been collected in the first three endpoints.

The `{guides.id}` part of the chain in a `__query` isn't meant to be filled in by the client, but is instead dynamically filled by the previous results in the chain.

## API Logic Walkthrough

I'll walk through the process the API goes through to demonstrate.

So, let's say I have a `__query` chain that looks like this:

```
/cities?search=state:CA
/cities/{cities.id}/guides
/guides/{guides.id}/tips
```

*We don't have all these cities in the database yet, but let's say we do for this example*

Notice that The ONLY thing the client ever defines is `search=state:CA`.  Everything else is like a template for a URI.

So, let's say the first line in that chain returns 4 cities:

```
ID    City
--    ----
210   San Francisco
256   Los Angeles
377   Sacramento
4225  Oakland
```

So the API returns (to itself):

```
{
    cities: {
        210:  {id: 210,  name: "San Francisco"},
        256:  {id: 256,  name: "Los Angeles"},
        377:  {id: 377,  name: "Sacramento"},
        4225: {id: 4225, name: "Oakland"}
    }
}
```

So now that the API has that data, it moves on to the next line in the __query chain:

```
/cities/{cities.id}/guides.
```

It parses that line and sees that we need to replace `{cities.id}` with all the ids in the "cities" table from the data we've collected thus far.

Just to be clear `cities.id` is specifically referring to

```
{
    cities: {
        {
            id: 210 // Me!
        },
        {
            id: 256 // And Me!
        },
        {
            id: 377 // And Me!
        },
        {
            id: 4225 // And Me!
        }
    }
}
```

Which becomes `[210, 256, 377, 4225]`

It could very well be `{cities.name}` or `{cities.state}`, any properties that can be gleaned from the responses of our previous lines in the chain.

So, now It has 4 those cities of data from the `__query` so far, and it fills in the template and calls this upon itself:

```
/cities/210/guides
/cities/256/guides
/cities/377/guides
/cities/4225/guides
```

*It actually literally calls this: `/cities/210,256,377,4225/guides` but the result is the same as calling them separately*

And it collects the data for all those guides:

```
{
  guides: {
    1: {id: 1, city_id: 210, name: 'SF Rocks'}
    2: {id: 2, city_id: 377, name: 'Meh, Sacramento'}
    ...
    40: {id: 40, city_id: 4225, name: 'Oakland Baby'}
  }
}
```

So, that {cities.id} in the query is specifically defined by all the data that's been collected thus far in the chain.  Let's say we end up with 40 guides for those 4 cities.  The next line of business would be to call guides/{guides.id}/tips 40 times!

```
guides/1/tips
guides/2/tips
guides/..../tips
guides/40/tips
```

Now, it COULD be something like: `/guides/{cities.id}/tips`, and it would fill in all the city ids form our data.  And the API would listen, and might even return some data.  But that wouldn't make any sense.  The important point is that it's a template for API calls which are filled by data previously collected during the __query call.

Anyways, for that last line - the tips, the API calls itself again like so: `/guides/1,2,3,4,5,..../tips`, and returns all the tips for all the guides in all the cities.

The only criteria we explicitly defined in any of those urls was `search=state:CA`, and we ended up with tons of data all in a single API call.

Your response to this simple query (with our fake data from above):

```
/cities?search=state:CA
…
/cities/{cities.id}/guides
/guides/{guides.id}/tips
```

Will look something like this:

```
{
  cities: {
    210:  {id: 210,  name: "San Francisco"},
    256:  {id: 256,  name: "Los Angeles"},
    377:  {id: 377,  name: "Sacramento"},
    4225: {id: 4225, name: "Oakland"}
  }
  guides: {
    1:  {id: 1,  city_id: 210,  name: 'SF Rocks'}
    2:  {id: 2,  city_id: 377,  name: 'Meh, Sacramento'}
    ...
    40: {id: 40, city_id: 4225, name: 'Oakland Baby'}
  },
  tips: {
    1:   {id: 1,   guide_id: 1,  tip: 'Go To The Mission!'}
    2:   {id: 2,   guide_id: 1,  tip: 'Alcatraz Rocks!'}
    ...
    200: {id: 200, guide_id: 40, tip: 'Something in Oakland!'}
  }
}
```