# DDEV
In case you get a permission error when trying to CREATE or DROP a database, do the following in the laravel project:
```bash
ddev mysql
GRANT ALL ON *.* TO 'db'@'%';
FLUSH PRIVILEGES;
```

# Faker
https://fakerphp.org/formatters/
