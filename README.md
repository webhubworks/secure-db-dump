# Usage
Call `artisan secure-db-dump:run` to export data based on the following config.

# Config
Publish the config file via `artisan vendor:publish --tag=secure-db-dump-config`.

## Anonymize fields
This package uses Faker to anonymize fields. You can find the available formatters/methods here:
https://fakerphp.org/formatters/

Per field you want to anonymize you have to define the `field` and a `type`. Possible values are: `faker` or `static`.
### Type: static
You will need to provide a `value` for the field.
### Type: faker
You will need to provide a `method` and optionally `args` (an array) for the Faker method.

### Examples
```php
...
'anonymize_fields' => [

        # Specify the table name
        'users' => [
        
            # This will run $faker->name() for the 'name' field
            AnonymizerConfig::make()
                ->field('name')
                ->type('faker')
                ->method('name'),
            
            # This will run $faker->email() for the 'email' field
            AnonymizerConfig::make()
                ->field('email')
                ->type('faker')
                ->method('email'),
            
            # This will set the 'password' field to a static value
            AnonymizerConfig::make()
                ->field('password')
                ->type('static')
                ->value('$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
        
            # You can also add (multiple) where conditions.
            AnonymizerConfig::make()
                ->field('some_field')
                ->type('faker')
                ->method('sentence')
                ->where('some_field', fn($value) => $value === 'some_value'),
                ->where('some_other_field', fn($value) => ! str($value)->endsWith('@webhub.de')),
        ],
        
        'cars' => [
            # This will run $faker->regexify('LG [A-Z]{2} [0-9]{2,4}') for the 'licence_plate' field
            AnonymizerConfig::make()
                ->field('licence_plate')
                ->type('faker')
                ->method('regexify')
                ->args(['LG [A-Z]{2} [0-9]{2,4}']),
        ],
    ],
...
```

# DDEV
In case you get a permission error when trying to CREATE or DROP a database, do the following in the laravel project:
```bash
ddev mysql
GRANT ALL ON *.* TO 'db'@'%';
FLUSH PRIVILEGES;
```

