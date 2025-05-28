# Config
Publish the config file via `artisan vendor:publish --tag=secure-db-dump-config`.

## Anonymize fields
This package uses Faker to anonymize fields. You can find the available formatters/methods here:
https://fakerphp.org/formatters/

Per field you have to define a type. Possible values are: `faker` or `static`.
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
            'name' => [
                'type' => 'faker',
                'method' => 'name',
            ],
            
            # This will run $faker->email() for the 'email' field
            'email' => [
                'type' => 'faker',
                'method' => 'email',
            ],
            
            # This will set the 'password' field to a static value
            'password' => [
                'type' => 'static',
                'value' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            ],
        ],
        
        'cars' => [
        
            # This will run $faker->regexify('LG [A-Z]{2} [0-9]{2,4}') for the 'licence_plate' field
            'licence_plate' => [
                'type' => 'faker',
                'method' => 'regexify',
                'args' => ['LG [A-Z]{2} [0-9]{2,4}']
            ],
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

