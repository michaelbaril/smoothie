# Smoothie

Some fruity additions to Laravel's Eloquent:

* [Fuzzy dates](#fuzzy-dates)
* [Mutually-belongs-to-many-selves relationship](#mutually-belongs-to-many-selves-relationship)
* [Orderable behavior](#orderable-behavior)
* [Tree-like structures and closure tables](#tree-like-structures-and-closure-tables)

> :warning: Note: only MySQL is tested and actively supported.

## Fuzzy dates

The package provides a modified version of the `Carbon` class that can handle
SQL "fuzzy" dates (where the day, or month and day, are zero).

With the original version of Carbon, such dates wouldn't be interpreted
properly, for example `2010-10-00` would be interpreted as `2010-09-30`.

With this version, zeros are allowed. An additional method is provided to
determine if the date is fuzzy:

```php
$date = Baril\Smoothie\Carbon::createFromFormat('Y-m-d', '2010-10-00');
$date->day; // will return 0
$date->isFuzzy(); // will return true if month and/or day is zero
```

The `format` and `formatLocalized` methods now have two additional (optional)
arguments `$formatMonth` and `$formatYear`. If the date is fuzzy, the method
will automatically fallback to the appropriate format:

```php
$date = Baril\Smoothie\Carbon::createFromFormat('Y-m-d', '2010-10-00');
$date->format('d/m/Y', 'm/Y', 'Y'); // will display "10/2010"
```

> :warning: Note: because a fuzzy date can't convert to a timestamp, a date
> like `2010-10-00` is transformed to `2010-10-01` internally before conversion
> to timestamp. Thus, any method or getter that relies on the timestamp value
> might return an "unexpected" result:

```php
$date = Baril\Smoothie\Carbon::createFromFormat('Y-m-d', '2010-10-00');
$date->dayOfWeek; // will return 5, because October 1st 2010 was a friday
```

If you need fuzzy dates in your models, use the
`Baril\Smoothie\Concerns\HasFuzzyDates` trait. Then, fields cast as
`date` or `datetime` will use this modified version of Carbon:

```php
class Book extends \Illuminate\Database\Eloquent\Model
{
    use \Baril\Smoothie\Concerns\HasFuzzyDates;

    protected $casts = [
        'is_available' => 'boolean',
        'release_date' => 'date', // will allow fuzzy dates
    ];
}
```

Alternatively, you can extend the `Baril\Smoothie\Model` class to achieve the
same result. This class already uses the `HasFuzzyDates` trait (as well as some
other traits described in the subsequent sections):

```php
class Book extends \Baril\Smoothie\Model
{
    protected $casts = [
        'is_available' => 'boolean',
        'release_date' => 'date', // will allow fuzzy dates
    ];
}
```

> :warning: Note: you will need to disable MySQL strict mode in your
> `database.php` config file in order to use fuzzy dates:

```php
return [
    'connections' => [
        'mysql' => [
            'strict' => false,
            // ...
        ],
    ],
    // ...
];
```

## Mutually-belongs-to-many-selves relationship

### Usage

This new type of relationship defines a many-to-many, **mutual**
relationship to the same table/model. Laravel's native `BelongsToMany`
relationship can already handle self-referencing relationships, but with a
direction (for example `sellers`/`buyers`). The difference is that the
`MutuallyBelongsToManySelves` relationship is meant to handle "mutual"
relationships (such as `friends`):

```php
class User extends \Illuminate\Database\Eloquent\Model
{
    use \Baril\Smoothie\Concerns\HasMutualSelfRelationships;

    public function friends()
    {
        return $this->mutuallyBelongsToManySelves();
    }
}
```

With this type of relationship, attaching `$user1` to `$users2`'s `friends`
will implicitely attach `$user2` to `$user1`'s `friends` as well:

```php
$user1->friends()->attach($user2->id);
$user2->friends()->get(); // contains $user1
```

Similarly, detaching one side of the relation will detach the other as well:

```php
$user2->friends()->detach($user1->id);
$user1->friends()->get(); // doesn't contain $user2 any more
```

The full prototype for the `mutuallyBelongsToManySelves` method is similar to
`belongsToMany`, without the first argument (which we don't need since we
already know that the related class is the class itself):

```php
public function mutuallyBelongsToManySelves(

        // Name of the pivot table (defaults to the snake-cased model name,
        // concatenated to itself with an underscore separator,
        // eg. "user_user"):
        $table = null,

        // First pivot key (defaults to the model's default foreign key, with
        // an added number, eg. "user1_id"):
        $firstPivotKey = null,

        // Second pivot key (the pivot keys can be passed in any order since
        // the relationship is mutual):
        $secondPivotKey = null,

        // Parent key (defaults to the model's primary key):
        $parentKey = null,

        // Relation name (defaults to the name of the caller method):
        $relation = null)
```

In order to use the `mutuallyBelongsToManySelves` method, your model needs to
either use the `Baril\Smoothie\Concerns\HasMutualSelfRelationships`, or
extend the `Baril\Smoothie\Model` class.

### Cleanup command

In order to avoid duplicates, the `MutuallyBelongsToManySelves` class will
ensure that attaching `$model1` to `$model2` will insert the same pivot row as
attaching `$model2` to `$model1`: the key defined as the first pivot key of
the relationship will always receive the smaller id. In case you're working
with pre-existing data, and you're not sure that the content of your pivot table
follows this rule, you can use the following Artisan command that will check the
data and fix it if needed:

```bash
php artisan smoothie:fix-pivots "App\\YourModelClass" relationName
```

## Orderable behavior

Adds orderable behavior to Eloquent models (forked from
<https://github.com/boxfrommars/rutorika-sortable>).

### Setup

First, add a `position` field to your model (see below how to change this name):

```php
public function up()
{
    Schema::create('articles', function (Blueprint $table) {
        // ... other fields ...
        $table->unsignedInteger('position');
    });
}
```

Then, use the `\Baril\Smoothie\Concerns\Orderable` trait in your model. The
`position` field should be guarded as it won't be filled manually.

```php
class Article extends Model
{
    use \Baril\Smoothie\Concerns\Orderable;

    protected $guarded = ['position'];
}
```

You need to set the `$orderColumn` property if you want another name than
`position`:

```php
class Article extends Model
{
    use \Baril\Smoothie\Concerns\Orderable;

    protected $orderColumn = 'order';
    protected $guarded = ['order'];
}
```

### Basic usage

You can use the following method to change the model's position (no need to
save it afterwards, the method does it already):

* `moveToOffset($offset)` (`$offset` starts at 0 and can be negative, ie.
`$offset = -1` is the last position),
* `moveToStart()`,
* `moveToEnd()`,
* `moveToPosition($position)` (`$position` starts at 1 and must be a valid
position),
* `moveUp($positions = 1, $strict = true)`: moves the model up by `$positions`
positions (the `$strict` parameter controls what happens if you try to move the
model "out of bounds": if set to `false`, the model will simply be moved to the
first or last position, else it will throw a `PositionException`),
* `moveDown($positions = 1, $strict = true)`,
* `swapWith($anotherModel)`,
* `moveBefore($anotherModel)`,
* `moveAfter($anotherModel)`.

```php
$model = Article::find(1);
$anotherModel = Article::find(10)
$model->moveAfter($anotherModel);
// $model is now positioned after $anotherModel, and both have been saved
```

Also, this trait:
* automatically defines the model position on the `create` event, so you don't
need to set `position` manually,
* automatically decreases the position of subsequent models on the `delete`
event so that there's no "gap".

```php
$article = new Article();
$article->title = $request->input('title');
$article->body = $request->input('body');
$article->save();
```

This model will be positioned at `MAX(position) + 1`.

To get ordered models, use the `ordered` scope:

```php
$articles = Article::ordered()->get();
$articles = Article::ordered('desc')->get();
```

(You can cancel the effect of this scope by calling the `unordered` scope.)

Previous and next models can be queried using the `previous` and `next`
methods:

```php
$entity = Article::find(10);
$entity->next(10); // returns a QueryBuilder on the next 10 entities, ordered
$entity->previous(5)->get(); // returns a collection with the previous 5 entities, in reverse order
$entity->next()->first(); // returns the next entity
```

### Orderable groups / one-to-many relationships

Sometimes, the table's data is "grouped" by some column, and you need to order
each group individually instead of having a global order. To achieve this, you
just need to set the `$groupColumn` property:

```php
class Article extends Model
{
    use \Baril\Smoothie\Concerns\Orderable;

    protected $guarded = ['position'];
    protected $groupColumn = 'section_id';
}
```

If the group is defined by multiple columns, you can use an array:

```php
protected $groupColumn = ['field_name1', 'field_name2'];
```

Orderable groups can be used to handle ordered one-to-many relationships:

```php
class Section extends Model
{
    public function articles()
    {
        return $this->hasMany(Article::class)->ordered();
    }
}
```

### Ordered many-to-many relationships

If you need to order a many-to-many relationship, you will need a `position`
column (or some other name) in the pivot table.

Have your model use the `\Baril\Smoothie\Concerns\HasOrderedRelationships` trait
(or extend the `Baril\Smoothie\Model` class):

```php
class Post extends Model
{
    use \Baril\Smoothie\Concerns\HasOrderedRelationships;

    public function tags()
    {
        return $this->belongsToManyOrdered(Tag::class);
    }
}
```

The prototype of the `belongsToManyOrdered` method is similar as `belongsToMany`
with an added 2nd parameter `$orderColumn`:

```php
public function belongsToManyOrdered(
        $related,
        $orderColumn = 'position',
        $table = null,
        $foreignPivotKey = null,
        $relatedPivotKey = null,
        $parentKey = null,
        $relatedKey = null,
        $relation = null)
```

Now all the usual methods from the `BelongsToMany` class will set the proper
position to attached models:

```php
$post->tags()->attach($tag->id); // will attach $tag and give it the last position
$post->tags()->sync([$tag1->id, $tag2->id, $tag3->id]) // will keep the provided order
$post->tags()->detach($tag->id); // will decrement the position of subsequent $tags
```

When queried, the relation is sorted by default. If you want to order the
related models by some other field, you will need to use the `unordered` scope
first:

```php
$post->tags; // ordered by position
$post->tags()->ordered('desc')->get(); // reverse order
$post->tags()->unordered()->get(); // unordered

// Note that orderBy has no effect here since the tags are already ordered by position:
$post->tags()->orderBy('id')->get();

// This is the proper way to do it:
$post->tags()->unordered()->orderBy('id')->get();
```

Of course, you can also define the relation like this if you don't want it
ordered by default:

```php
class Post extends Model
{
    use \Baril\Smoothie\Concerns\HasOrderedRelationships;

    public function tags()
    {
        return $this->belongsToManyOrdered(Tag::class)->unordered();
    }
}

$article->tags; // unordered
$article->tags()->ordered()->get(); // ordered
```

The `BelongsToManyOrdered` class has all the same methods as the `Orderable`
trait, except that you will need to pass them a related $model to work with:

* `moveToOffset($model, $offset)`,
* `moveToStart($model)`,
* `moveToEnd($model)`,
* `moveToPosition($model, $position)`,
* `moveUp($model, $positions = 1, $strict = true)`,
* `moveDown($model, $positions = 1, $strict = true)`,
* `swap($model, $anotherModel)`,
* `moveBefore($model, $anotherModel)` (`$model` will be moved before
`$anotherModel`),
* `moveAfter($model, $anotherModel)` (`$model` will be moved after
`$anotherModel`),
* `before($model)` (similar as the `previous` method from the `Orderable` trait),
* `after($model)` (similar as `next`).

```php
$tag1 = $article->tags()->first();
$tag2 = $article->tags()->last();
$article->tags()->moveBefore($tag1, $tag2);
// now $tag1 is at the second to last position
```

Note that if `$model` doesn't belong to the relationship, any of these methods
will throw a `Baril\Smoothie\GroupException`.

### Ordered morph-to-many relationships

Similarly, the package defines a `MorphToManyOrdered` type of relationship.
The 3rd parameter of the `morphToManyOrdered` method is the name of the order
column (defaults to `position`):

```php
class Post extends Model
{
    use \Baril\Smoothie\Concerns\HasOrderedRelationships;

    public function tags()
    {
        return $this->morphToManyOrdered('App\Tag', 'taggable', 'tag_order');
    }
}
```

Same thing with the `morphedByManyOrdered` method:

```php
class Tag extends Model
{
    use \Baril\Smoothie\Concerns\HasOrderedRelationships;

    public function posts()
    {
        return $this->morphedByManyOrdered('App\Post', 'taggable', 'order');
    }

    public function videos()
    {
        return $this->morphedByManyOrdered('App\Video', 'taggable', 'order');
    }
}
```

## Tree-like structures and closure tables

This is an implementation of the "Closure Table" design pattern for Laravel
and SQL. This pattern allows for faster querying of tree-like structures stored
in a relational database.

### Setup

You will need to create a closure table in your database. For example, if your
main table is `tags`, you will need a closure table named `tag_tree` (you can
change this name if you want -- see below), with the following columns:

* `ancestor_id`: foreign key to your main table,
* `descendant_id`: foreign key to your main table,
* `depth`: unsigned integer.

Of course, you don't need to write the migration manually: the package provides
an Artisan command for that (see below).

Also, your main table will need a `parent_id` column with a self-referencing
foreign key (you can change this name too -- see below). This column is the one
that holds the actual hierarchical data: the closures are merely a duplication
of that information.

Once your database is ready, have your model implement the
`Baril\Smoothie\Concerns\BelongsToTree` trait.

You can use the following properties to configure the table and column names:

* `$parentForeignKey`: name of the self-referencing foreign key in the main
table (defaults to `parent_id`),
* `$closureTable`: name of the closure table (defaults to the snake-cased model
name suffixed with `_tree`, eg. `tag_tree`).

```php
class File extends \Illuminate\Database\Eloquent\Model
{
    use \Baril\Smoothie\Concerns\BelongsToTree;

    protected $parentForeignKey = 'folder_id';
    protected $closureTable = 'file_closures';
}
```

### Artisan command

> Note: you need to configure your model as described above before you use these
> commands.

The `grow-tree` command will generate the migration file for the closure table:

```bash
php artisan smoothie:grow-tree "App\\YourModel"
```

If you use the `--migrate` option, then the command will also run the migration.
If your main table already contains data, it will also insert the closures for
the existing data.

```bash
php artisan smoothie:grow-tree "App\\YourModel" --migrate
```

> :warning: Note: if you use the `--migrate` option, any other pending migrations
> will run too.

There are some additional options: use `--help` to learn more.

If you ever need to re-calculate the closures, you can use the following
command:

```bash
php artisan smoothie:fix-tree "App\\YourModel"
```

It will truncate the table and fill it again based on the data from the main
table.

### Basic usage

Just fill the model's `parent_id` and save the model: the closure table will
be updated accordingly.

```php
$tag = Tag::find($tagId);
$tag->parent_id = $parentTagId; // or: $tag->parent()->associate($parentTag);
$tag->save();
```

The `save` method will throw a `\Baril\Smoothie\TreeException` in
case of a redundancy error (ie. if the `parent_id` corresponds to the model
itself or one of its descendants).

When you delete a model, its closures will be automatically deleted. If the
model has descendants, the `delete` method will throw a `TreeException`. You
need to use the `deleteTree` method if you want to delete the model and all its
descendants.

```php
try {
    $tag->delete();
} catch (\Baril\Smoothie\TreeException $e) {
    // some specific treatment
    // ...
    $tag->deleteTree();
}
```

### Relationships

The trait defines the following relationships (which can't be renamed for now):

* `parent`: `BelongsTo` relation to the parent,
* `children`: `HasMany` relation to the children,
* `ancestors`: `BelongsToMany` relation to the ancestors,
* `descendants`: `BelongsToMany` relation to the descendants.

> :warning: Note: The `ancestors` and `descendants` relations are read-only!
> Trying to use the `attach` or `detach` method on them will corrupt the data.

The `ancestors` and `descendants` relations can be ordered by depth (ie. with
the direct parent/children first):

```php
$tags->descendants()->orderByDepth()->get();
```

Loading or eager-loading the `descendants` relation will automatically load the
`children` relation (with no additional query). Furthermore, it will load the
`children` relation recursively for all the eager-loaded descendants:

```php
$tags = Tag::with('descendants')->limit(10)->get();

// The following code won't execute any new query:
foreach ($tags as $tag) {
    dump($tag->name);
    foreach ($tag->children as $child) {
        dump('-' . $child->name);
        foreach ($child->children as $grandchild) {
            dump('--' . $grandchild->name);
        }
    }
}
```

Of course, same goes with the `ancestors` and `parent` relations.

### Methods

The trait defines the following methods (all of which return a boolean):

* `isRoot()`: returns `true` if the item's `parent_id` is `null`,
* `isLeaf()`: checks if the item is a leaf (ie. has no children),
* `hasChildren()`: `$tag->hasChildren()` is similar to `!$tag->isLeaf()`,
albeit more readable,
* `isChildOf($item)`,
* `isParentOf($item)`,
* `isDescendantOf($item)`,
* `isAncestorOf($item)`,
* `isSiblingOf($item)`.

### Query scopes

* `withAncestors($depth = null, $constraints = null)`: shortcut to
`with('ancestors')`, with the added ability to specify a `$depth` limit
(eg. `$query->withAncestors(1)` will only load the direct parent). Optionally,
you can pass additional `$constraints`.
* `withDescendants($depth = null, $constraints = null)`.
* `whereIsRoot($bool = true)`: limits the query to the items with no parent (the
behavior of the scope can be reversed by setting the `$bool` argument to
`false`).
* `whereIsLeaf($bool = true)`.
* `whereHasChildren($bool = true)`: is just the opposite of `whereIsLeaf`.
* `whereIsDescendantOf($ancestorId, $maxDepth = null, $includingSelf = false)`:
limits the query to the descendants of `$ancestorId`, with an optional
`$maxDepth`. If the `$includingSelf` parameter is set to `true`, the ancestor
will be included in the query results too.
* `whereIsAncestorOf($descendantId, $maxDepth = null, $includingSelf = false)`.
* `orderByDepth($direction = 'asc')`: this scope will work only when querying
the `ancestors` or `descendants` relationships (see examples below).

```php
$tag->ancestors()->orderByDepth();
Tag::with(['descendants' => function ($query) {
    $query->orderByDepth('desc');
}]);
```

### Ordered tree

In case you need each level of the tree to be explicitely ordered, you can use
the `Baril\Smoothie\Concerns\BelongsToOrderedTree` trait (instead of
`BelongsToTree`).

You will need a `position` column in your main table (the name of the column
can be configured with the `$orderColumn` property).

```php
class Tag extends \Illuminate\Database\Eloquent\Model
{
    use \Baril\Smoothie\Concerns\BelongsToOrderedTree;

    protected $orderColumn = 'order';
}
```

The `children` relation will now be ordered. In case you need to order it by
some other field (or don't need the children ordered at all), you can use
the `unordered` scope:

```php
$children = $this->children()->unordered()->orderBy('name');
```

Also, all methods defined by the `Orderable` trait described
[above](#orderable-behavior) will now be available:

```php
$lastChild->moveToPosition(1);
```
