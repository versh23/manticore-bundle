# manticore-bundle
[![Build Status](https://travis-ci.com/versh23/manticore-bundle.svg?branch=master)](https://travis-ci.com/versh23/manticore-bundle)

# Installation

This bundle should to be installed with [Composer](https://getcomposer.org)
The process vary slightly depending on if your application uses Symfony Flex or not.

Following instructions assume you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.


## Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```bash
composer require versh23/manticore-bundle
```


## Applications that don't use Symfony Flex

### Step 1: Download the Bundle
Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
composer require versh23/manticore-bundle
```

### Step 2: Enable the Bundle
Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Versh23\ManticoreBundle\Versh23ManticoreBundle(),
        );

        // ...
    }

    // ...
}
```

# Configuration

Create configuration file `config/packages/manticore.yaml`

### Example
```yaml
versh23_manticore:
    host: 'manticore'
    port: 9306
    indexes:
        my_entity:
            class: App\Entity\MyEntity
            fields:
                description: ~ #short syntax
                name:          #full syntax
                    property: name

            attributes:
                free: bool #short syntax
                status:    #full syntax
                    property: status
                    type: string
```

In this example will be created a `manticore.index_manager.article` service that allow to manipulate index `manticore.index.article`
 * truncate index (`truncateIndex()`)
 * flush index (`flushIndex()`)
 * replace/insert/delete operations (`replace()`, `insert()`, `delete()`, `bulkReplace()`, `bulkInsert()` )
 * create a Pagerfanta object (`findPaginated()`) https://github.com/whiteoctober/Pagerfanta
 * simple find objects (`find()`)
 * create custom query by SphinxQL syntax (`createQuery`)
 
 For `App\Entity\MyEntity` will be created Event listener `manticore.listener.article` thats allow update your RT index `my_entity`
 
 There are two useful commands:
  * `manticore:index:config` - render config sample
  * `manticore:index:populate` - populate RT index 