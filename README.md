s3thumb
=======

Простое создание миниатюр и хранение их на Amazin S3

<b>Структура:</b>
- `CS3Thumb.php` – основной класс
- `S3.php` – класс для работы с Amazon S3
- `CRC4Crypt.php` – класс для криптования в RC4
- `CThumb.php` – класс для создания миниатюр

<b>Как использовать:</b>
```php
$Thumb = new CS3Thumb($backets, $accessKey, $secretKey, $cryptpsw = 'password');
```
, где:
- `$backets` - массив я наименованиями бакетов (они же поддомены)
- `$accessKey` и `$secretKey` – доступы к амазону
- `$cryptpsw` – пароль для криптования ссылок

Для того, чтобы получить ссылку на изображение, используем:
```php
$Thumb -> url("http://blablabla.com/photo15.jpg", 100, 100)
```

Для того чтобы создать миниатюру, переместить ее на S3 и отобразить первому пользователю:
```php
$Thumb -> process("e2/PUuxR1p~D~Jgl5PrnPMLh4OA0sO899rjZgzgWFU_.jpg");
```
